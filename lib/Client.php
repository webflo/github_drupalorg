<?php

/**
 * @file
 * Contains \Drupal\github_drupalorg\Client.
 */

namespace Drupal\github_drupalorg;

use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\FileCookieJar;

class Client {

  private $cookieFilepath;

  private $username;

  /**
   * @var \Goutte\Client;
   */
  private $client;

  function __construct($username) {
    $this->username = $username;
    $this->setCookieFilepath();
    $this->createClient();
  }

  private function createClient() {
    $this->client = new \Goutte\Client();

    // Create a new cookie plugin
    $cookie_file = sys_get_temp_dir() . '/github_drupalorg_cookie_' . $this->username;
    touch($cookie_file);
    $cookiePlugin = new CookiePlugin(new FileCookieJar($cookie_file));

    // Add the cookie plugin to the client
    $this->client->getClient()->addSubscriber($cookiePlugin);
  }

  /**
   * Perform login.
   *
   * @param $password
   * @return bool
   * @throws \Exception
   */
  public function login($password) {
    $crawler = $this->client->request('GET', 'https://drupal.org/user');
    $form = $crawler->selectButton('Log in')->form();
    $crawler = $this->client->submit($form, array(
      'name' => $this->username,
      'pass' => $password
    ));

    $login_errors = $crawler->filter('.messages-error');
    if ($login_errors->count() > 0) {
      throw new \Exception("Login to drupal.org failed.");
    }

    return TRUE;
  }

  /**
   * Post comment.
   */
  public function postComment($issue_id, $comment, array $files = array(), $issue_settings = array()) {
    $edit_page = $this->client->request('GET', "https://drupal.org/node/$issue_id/edit");
    $form = NULL;

    if ($files) {
      $last_file = end(array_keys($files));

      foreach ($files as $key => $file) {
        $button = ($last_file == $key) ? 'Save' : 'Upload';
        $form = $edit_page->selectButton($button)->form();

        // There can be uploaded files already, so we need to iterate to the most
        // recent file number.
        $file_nr = 0;
        while (!isset($form["files[field_issue_files_und_$file_nr]"])) {
          $file_nr++;
        }

        $form["files[field_issue_files_und_$file_nr]"]->setFilePath($file);

        if ($key != $last_file) {
          $edit_page = $this->client->submit($form);
        }
      }
    }

    if (!isset($form)) {
      $form = $edit_page->selectButton('Save')->form();
    }

    $form['nodechanges_comment_body[value]']->setValue($comment);

    if ($files) {
      // Update the issue status.
      if (!isset($issue_settings['status'])) {
        $status = $form['field_issue_status[und]']->getValue();

        // Set the issue to "needs review" if it is not alreay "needs review" or RTBC.
        if ($status != 8 && $status != 14) {
          $issue_settings['status'] = 8;
        }
      }
    }

    if (isset($issue_settings['status'])) {
      $form['field_issue_status[und]']->setValue($issue_settings['status']);
    }

    if (isset($issue_settings['tags'])) {
      $form['taxonomy_vocabulary_9[und]']->setValue($issue_settings['tags']);
    }

    $this->client->submit($form);
  }

  /**
   * Get node edit form.
   *
   * @param $nid
   * @return \Symfony\Component\DomCrawler\Form
   */
  public function getForm($nid) {
    $edit_page = $this->client->request('GET', "https://drupal.org/node/$nid/edit");
    return $edit_page->selectButton('Save')->form();
  }

  private function setCookieFilepath() {
    // Create a new cookie plugin
    $this->cookieFilepath = sys_get_temp_dir() . '/github_drupalorg_cookie_' . $this->username;
    if (!file_exists($this->cookieFilepath)) {
      touch($this->cookieFilepath);
    }
  }

}
