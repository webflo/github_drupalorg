<?php

/**
 * @file
 * Contains \Drupal\github_drupalorg\IssueMetadata.
 */

namespace Drupal\github_drupalorg;

class IssueMetadata {

  public static function getDefinition() {
    return array();
  }

  public static function aliasMapReverse() {
    $map = static::getDefinition();
    $aliases = array();
    foreach ($map as $status => $definition) {
      $aliases[$definition['label']] = $status;
      $aliases = array_merge($aliases, array_fill_keys($definition['aliases'], $status));
    }
    return $aliases;
  }

  public static function toValue($string) {
    $string = trim($string);
    if (is_numeric($string)) {
      return (string) $string;
    }
    $map = static::aliasMapReverse();
    return isset($map[$string]) ? (string) $map[$string] : FALSE;
  }
}
