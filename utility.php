<?php

use Goutte\Client;
use Guzzle\Http\Client as GuzzleClient;

require 'vendor/autoload.php';

/**
 * Process a pull request creation or update event.
 *
 * @param object $payload
 *   The received data.
 */
function handle_pull_request($payload) {
  $issue_number = get_issue_number($payload->pull_request);
  if (!$issue_number) {
    exit;
  }

  $pull_request_url = $payload->pull_request->html_url;

  switch ($payload->action) {
    case 'synchronize':
      $comment = 'Some commits were pushed to the <a href="' . $pull_request_url . '">pull request</a>.';
      break;

    case 'opened':
      $comment = 'A <a href="' . $pull_request_url . '">new pull request</a> was opened by <a href="' . $payload->pull_request->user->html_url . '">' . $payload->pull_request->user->login . '</a> for this issue.';
      break;

    default:
      // Unknown action, so we just exit.
      exit;
  }

  $diff_url = $payload->pull_request->diff_url;
  // Download the patch.
  $file = download_file($diff_url, "$branch_name.patch");

  post_comment($issue_number, $comment, $file);

  // Cleanup: remove the downloaded file and the temporary directory it is in.
  unlink($file);
  rmdir(dirname($file));
}

/**
 * Process a comment that has been created on a pull request.
 *
 * @param object $payload
 *   The received data.
 */
function handle_comment($payload) {
  global $oauth_token;
  $client = new GuzzleClient();

  // Use the OAuth token to access the API, to have a higher rate limit.
  $response = $client->get($payload->comment->pull_request_url . "?access_token=$oauth_token")->send();
  $pull_request = json_decode($response->getBody());
  $issue_number = get_issue_number($pull_request);

  if (!$issue_number) {
    exit;
  }

  $comment = 'A <a href="' . $payload->comment->html_url . '">new comment</a> has been posted by <a href="' . $payload->comment->user->html_url . '">' . $payload->comment->user->login . '</a> on ' . $payload->comment->html_url . "\n";
  $comment .= 'Path: ' . $payload->comment->path . "\n";
  $comment .= "<code>\n";
  $comment .= $payload->comment->diff_hunk;
  $comment .= "</code>\n";
  $comment .= $payload->comment->body;

  post_comment($issue_number, $comment);
}

/**
 * Post a comment to drupal.org.
 *
 * @param int $issue_id
 *   The drupal.org node ID to post the comment to.
 * @param string $comment
 *   Text for the comment field.
 * @param string $patch
 *   Optional, file name to attach as patch.
 */
function post_comment($issue_id, $comment, $patch = NULL) {
  static $client;
  if (!$client) {
    // Perform a user login.
    global $drupal_user, $drupal_password;
    $client = new Client();
    $crawler = $client->request('GET', 'https://drupal.org/user');
    $form = $crawler->selectButton('Log in')->form();
    // $user and $password must be set in user_password.php.
    $crawler = $client->submit($form, array('name' => $drupal_user, 'pass' => $drupal_password));

    $login_errors = $crawler->filter('.messages-error');
    if ($login_errors->count() > 0) {
      logger("Login to drupal.org failed.");
      exit;
    }
  }
  $edit_page = $client->request('GET', "https://drupal.org/node/$issue_id/edit");
  $form = $edit_page->selectButton('Save')->form();

  $form['nodechanges_comment_body[value]']->setValue($comment);
  // We need to HTML entity decode the issue summary here, otherwise we
  // would post back a double-encoded version, which would result in issue
  // summary changes that we don't want to touch.
  $form['body[und][0][value]']->setValue(html_entity_decode($form->get('body[und][0][value]')->getValue(), ENT_QUOTES, 'UTF-8'));

  if ($patch) {
    // There can be uploaded files already, so we need to iterate to the most
    // recent file number.
    $file_nr = 0;
    while (!isset($form["files[field_issue_files_und_$file_nr]"])) {
      $file_nr++;
    }
    $form["files[field_issue_files_und_$file_nr]"]->upload($patch);

    $status = $form['field_issue_status[und]']->getValue();

    // Set the issue to "needs review" if it is not alreay "needs review" or RTBC.
    if ($status != 8 && $status != 14) {
      $form['field_issue_status[und]']->setValue(8);
    }
  }

  $client->submit($form);
}

/**
 * Logs a message to a log file.
 *
 * @global string $logfile
 *   The file name defined in settings.php.
 * @param string $message
 *   The message to log.
 */
function logger($message) {
  global $logfile;
  file_put_contents($logfile, date("Y-m-d H:i:s") . "  $message\n", FILE_APPEND);
}

/**
 * Downloads a URL to a local temporary file.
 *
 * @param string $url
 *   The URL.
 * @param string $file_name
 *   The local file name where this should be stored, without path prefix.
 *
 * @return string
 *   Absolute path to the tempory file.
 */
function download_file($url, $file_name) {
  // Create a temporary directory.
  $temp_file = tempnam(sys_get_temp_dir(), 'github_drupalorg_');
  unlink($temp_file);
  mkdir($temp_file);

  $client = new GuzzleClient();
  $response = $client->get($url)->send();
  $path = "$temp_file/$file_name";
  file_put_contents($path, $response->getBody());
  return $path;
}

/**
 * Extracts a potential issue number from the received pull request object.
 *
 * @param object $pull_request
 *   The pull request object.
 *
 * @return int|false
 *   Returns the issue number or FALSE if no number could be extracted.
 */
function get_issue_number($pull_request) {
  $branch_name = $pull_request->head->ref;
  $matches = array();

  // Only look at branch names that end in an issue number with a least 4
  // digits.
  if (preg_match('/([0-9]{4}[0-9]*)$/', $branch_name, $matches)) {
    return $matches[0];
  }
  return FALSE;
}