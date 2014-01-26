<?php

/**
 * @file
 * Contains \Drupal\github_drupalorg\IssueStatus.
 */

namespace Drupal\github_drupalorg;

/**
 * Defines drupal.org issue statuses.
 */
class IssueStatus extends IssueMetadata {

  /*
  1  Active
  13 Needs work
  8  Needs review
  14 Reviewed & tested by the community
  15 Patch (to be ported)
  2  Fixed
  4  Postponed
  16 Postponed (maintainer needs more info)
  3  Closed (duplicate)
  5  Closed (won't fix)
  6  Closed (works as designed)
  18 Closed (cannot reproduce)
  7  Closed (fixed)
  */

  const ACTIVE = 1;

  const NEEDS_WORK = 13;

  const NEEDS_REVIEW = 8;

  const RTBC = 14;

  const PATCH_TO_BE_PORTED = 15;

  const FIXED = 2;

  const POSTPONED = 4;

  const POSTPONED_MAINTAINER_INFO = 16;

  const CLOSED_DUPLICATE = 3;

  const CLOSED_WONT_FIX = 5;

  const CLOSED_WORKS_AS_DESIGNED = 6;

  const CLOSED_CANNOT_REPRODUCE = 18;

  const CLOSED_FIXED = 7;


  public static function getDefinition() {
    return array(
      IssueStatus::ACTIVE => array(
        'label' => 'Active',
        'aliases' => array(
          'active',
          'a',
        ),
      ),
      IssueStatus::NEEDS_WORK => array(
        'label' => 'Needs work',
        'aliases' => array(
          'needs work',
          'nw',
          'work',
        ),
      ),
      IssueStatus::NEEDS_REVIEW => array(
        'label' => 'Needs review',
        'aliases' => array(
          'needs review',
          'nr',
          'review',
        ),
      ),
      IssueStatus::RTBC => array(
        'label' => 'Reviewed & tested by the community',
        'aliases' => array(
          'rtbc',
          '+1',
        ),
      ),
      IssueStatus::PATCH_TO_BE_PORTED => array(
        'label' => 'Patch (to be ported)',
        'aliases' => array(
          'pp',
        ),
      ),
      IssueStatus::FIXED => array(
        'label' => 'Fixed',
        'aliases' => array(
          'fixed',
          'f',
        ),
      ),
      IssueStatus::POSTPONED => array(
        'label' => 'Postponed',
        'aliases' => array(
          'p',
        ),
      ),
      IssueStatus::POSTPONED_MAINTAINER_INFO => array(
        'label' => 'Postponed (maintainer needs more info)',
        'aliases' => array(
          'pmi',
        ),
      ),
      IssueStatus::CLOSED_DUPLICATE => array(
        'label' => 'Closed (duplicate)',
        'aliases' => array(
          'cd',
          'dup',
        ),
      ),
      IssueStatus::CLOSED_WONT_FIX => array(
        'label' => "Closed (won't fix)",
        'aliases' => array(
          'cwf',
        ),
      ),
      IssueStatus::CLOSED_WORKS_AS_DESIGNED => array(
        'label' => 'Closed (works as designed)',
        'aliases' => array(
          'cwad',
        ),
      ),
      IssueStatus::CLOSED_CANNOT_REPRODUCE => array(
        'label' => 'Closed (cannot reproduce)',
        'aliases' => array(
          'cnr',
        ),
      ),
      IssueStatus::CLOSED_FIXED => array(
        'label' => 'Closed (fixed)',
        'aliases' => array(
          'cf',
        ),
      ),
    );
  }

  /**
   * Get all "Open Issues" statuses.
   *
   * @return array
   */
  public static function getOpenIssues() {
    return array(
      self::ACTIVE,
      self::NEEDS_WORK,
      self::NEEDS_REVIEW,
      self::RTBC,
      self::PATCH_TO_BE_PORTED,
      self::FIXED,
      self::POSTPONED,
      self::POSTPONED_MAINTAINER_INFO,
    );
  }

}
