<?php

/**
 * @file
 * Contains \Drupal\github_drupalorg\IssuePriority.
 */

namespace Drupal\github_drupalorg;

class IssuePriority extends IssueMetadata {

  const CRITICAL = 400;
  const MAJOR = 300;
  const NORMAL = 200;
  const MINOR = 100;

  public static function getDefinition() {
    return array(
      static::CRITICAL => array(
        'label' => 'Critical',
        'aliases' => array(
          'critical',
          'crit',
          'c',
        ),
      ),
      static::MAJOR => array(
        'label' => 'Major',
        'aliases' => array(
          'major',
          'maj',
          'ma',
        ),
      ),
      static::NORMAL => array(
        'label' => 'Normal',
        'aliases' => array(
          'normal',
          'norm',
          'n',
        ),
      ),
      static::MINOR => array(
        'label' => 'Minro',
        'aliases' => array(
          'minor',
          'min',
          'mi',
        ),
      ),
    );
  }

} 
