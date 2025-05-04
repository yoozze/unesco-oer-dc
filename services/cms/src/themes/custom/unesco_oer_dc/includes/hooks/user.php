<?php

/**
 * Implements theme hooks for user.
 */

use Drupal\Core\Datetime\DrupalDateTime;

function unesco_oer_dc_theme_suggestions_user_alter(&$suggestions, &$variables) {
    $view_mode = $variables['elements']['#view_mode'];
    if (!empty($view_mode)) {
        $suggestions[] = 'user__' . clean_suggetion($view_mode);
    }
}

function unesco_oer_dc_preprocess_user(&$variables) {
    $elements = &$variables['elements'];

    /**
     * @var Drupal\user\Entity\User $user
     */ 
    $user = &$variables['user'];
    $picture = $user->get('user_picture')->entity;

    // $timestamp1 = $user->getCreatedTime();
    // $timestamp2 = time();

    // $datetime1 = DrupalDateTime::createFromTimestamp($timestamp1);
    // $datetime2 = DrupalDateTime::createFromTimestamp($timestamp2);

    // $interval = $datetime1->diff($datetime2);
    // $variables['member_since'] = $interval;

    if (empty($picture)) {
        $uri = generate_avatar($user, 320);
        $alt = get_user_display_name($user);
        $elements['user_picture'] = [
            '#theme' => 'image',
            '#uri' => $uri,
            '#attributes' => [
                'class' => ['c-user__picture'],
            ],
        ];
    }

    $display_name = get_user_display_name($user);
    $elements['user_picture']['#alt'] = $display_name;
    $variables['display_name'] = $display_name;

    // Get user contact enabled status
    $database = \Drupal::database();
    $query = $database->query("SELECT `value` FROM `users_data` WHERE `uid` = :uid and `module` = 'contact' and `name` = 'enabled'", [':uid' => $user->id()]);
    $result = $query->fetchAll();
    $variables['contact_enabled'] = $result[0]->value === '1';

    // Check if this is current user
    $variables['is_current_user'] = $user->id() === \Drupal::currentUser()->id();
}
