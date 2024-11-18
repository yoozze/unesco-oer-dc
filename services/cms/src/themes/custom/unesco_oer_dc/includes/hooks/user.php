<?php

/**
 * Implements theme hooks for user.
 */

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Get display name for user.
 * @param Drupal\user\Entity\User $user
 */
function get_user_display_name($user) {
    $first_name = $user->get('field_first_name')->value;
    $last_name = $user->get('field_last_name')->value;
    $words = [];
    if (!empty($first_name)) {
        $words[] = $first_name;
    }

    if (!empty($last_name)) {
        $words[] = $last_name;
    }

    if (empty($words)) {
        $words[] = $user->getDisplayName();
    }

    return implode(' ', $words);
}

/**
 * Get initials for user.
 * @param Drupal\user\Entity\User $user
 */
function get_user_initials($user) {
    $display_name = get_user_display_name($user);
    $words = explode(' ', preg_replace('/\s+|\./', ' ', $display_name));
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper($word[0]);
    }

    if (strlen($initials) > 2) {
        $initials = $initials[0] . $initials[strlen($initials) - 1];
    }

    return $initials;
}

/**
 * Generate background color for given ID.
 * @param string $id
 */
function generate_background_color($id) {
    $colors = [
        'navy-blue' => '#001b59',
        'aqua' => '#00aaa0',
        'red' => '#f50019',
        'orange' => '#e66e23',
        'blue' => '#0069b4',
        'fuchsia' => '#b7265e',
        'green' => '#5aaa46',
        'abbey' => '#58595b',
        'chathams-blue' => '#174e86'
    ];
    $len = strlen($id);
    $hash = 0;
    $i = 0;
    while ($i < $len) {
        $hash = ord($id[$i]) + (($hash << 5) - $hash);
        $hash = $hash & $hash;
        $i++;
    }

    $color_name = array_keys($colors)[$hash % count($colors)];
    $color = $colors[$color_name];

    return $color;
}

/**
 * Generate avatar for user.
 * @param Drupal\user\Entity\User $user
 */
function generate_avatar($user, $size = 100) {
    $initials = get_user_initials($user);
    $color = generate_background_color($user->getDisplayName());
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '">';
    $svg .= '<rect x="0" y="0" width="' . $size . '" height="' . $size . '" fill="' . $color . '"/>';
    $svg .= '<text x="' . ($size / 2) .  '" y="' . ($size / 2) . '" text-anchor="middle" dy="0.35em" fill="#fff" font-size="' . ($size / 2) . '" font-family="\'Montserrat\', sans-serif">' . $initials . '</text>';
    $svg .= '</svg>';
    $avatar = 'data:image/svg+xml;base64,' . base64_encode($svg);
    return $avatar;
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
            '#alt' => $alt,
            '#attributes' => [
                'class' => ['c-user__picture'],
            ],
        ];
    }
}
