<?php

use Drupal\github_drupalorg\Client;
use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Http\Exception\ClientErrorResponseException;

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
      $comment = '<a href="' . $payload->pull_request->user->html_url . '">' . $payload->pull_request->user->login . '</a> opened a <a href="' . $pull_request_url . '">new pull request</a> for this issue.';
      break;

    default:
      // Unknown action, so we just exit.
      exit;
  }

  $diff_url = $payload->pull_request->diff_url;
  // Download the patch.
  $file = download_file($diff_url, "$issue_number.patch");

  post_comment($issue_number, $comment, array($file));

  // Cleanup: remove the downloaded file and the temporary directory it is in.
  unlink($file);
  rmdir(dirname($file));
}

/**
 * Process a comment that has been created on a pull request diff.
 *
 * @param object $payload
 *   The received data.
 */
function handle_pull_request_comment($payload) {
  $pull_request = get_pull_request($payload->comment->pull_request_url);
  $issue_number = get_issue_number($pull_request);

  if (!$issue_number) {
    exit;
  }

  $comment = '<a href="' . $payload->comment->user->html_url . '">' . $payload->comment->user->login . '</a> posted a <a href="' . $payload->comment->html_url . '">new comment</a> on ' . $payload->comment->html_url . "\n";
  $comment .= 'Path: ' . $payload->comment->path . "\n";
  $comment .= "<code>\n";
  $comment .= $payload->comment->diff_hunk;
  $comment .= "</code>\n";
  $comment .= $payload->comment->body;

  post_comment($issue_number, $comment);
}

/**
 * Fetches a pull request object from Github.
 *
 * @global string $oauth_token
 *
 * @param string $url
 *   The Github API URL to fetch the pull request from.
 *
 * @return object
 *   The pull request object.
 */
function get_pull_request($url) {
  global $oauth_token;
  $client = new GuzzleClient();

  // Use the OAuth token to access the API, to have a higher rate limit.
  $response = $client->get($url . "?access_token=$oauth_token")->send();
  return json_decode($response->getBody());
}

/**
 * Process a Github issue comment.
 *
 * @param object $payload
 *   The received data.
 */
function handle_issue_comment($payload) {
  // There is no pull request API URL in the issue, so we need to construct it
  // from the pattern.
  $pull_request_url = str_replace('{/number}', '/' . $payload->issue->number, $payload->repository->pulls_url);
  try {
    $pull_request = get_pull_request($pull_request_url);
  }
  catch (ClientErrorResponseException $e) {
    // Pull request does not exist, so we ignore this comment.
    exit;
  }
  $issue_number = get_issue_number($pull_request);

  if (!$issue_number) {
    exit;
  }

  $comment = '<a href="' . $payload->comment->user->html_url . '">' . $payload->comment->user->login . '</a> posted a <a href="' . $payload->comment->html_url . '">new comment</a> on ' . $payload->comment->html_url . ".\n\n";
  $comment .= $payload->comment->body;

  post_comment($issue_number, $comment);
}

/**
 * Returns the client for drupal.org using the singleton pattern.
 *
 * @return Drupal\github_drupalorg\Client
 */
function github_drupalorg_get_client() {
  static $client;
  if (!$client) {
    // Perform a user login.
    global $drupal_user, $drupal_password;
    $client = new Client($drupal_user);
    try {
      $client->login($drupal_password);
    } catch (Exception $e) {
      logger("Login to drupal.org failed.");
      exit;
    }
  }
  return $client;
}

/**
 * Post a comment to drupal.org.
 *
 * @param int $issue_id
 *   The drupal.org node ID to post the comment to.
 * @param string $comment
 *   Text for the comment field.
 * @param array $files
 *   Optional, file names to attach as patch.
 * @param array $issue_settings
 *   An array of settings that gets updated, with one of the following keys:
 *     - version (not implemented)
 *     - component (not implemented)
 *     - assigned (not implemented)
 *     - category (not implemented)
 *     - priority (not implemented)
 *     - status
 *     - tags The tags of the issue as comma-separated values.
 */
function post_comment($issue_id, $comment, array $files = array(), $issue_settings = array()) {
  $client = github_drupalorg_get_client();
  $client->postComment($issue_id, $comment, $files, $issue_settings);
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
  $message = is_scalar($message) ? $message : print_r($message, TRUE);
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
