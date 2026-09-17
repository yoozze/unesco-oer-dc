<?php

/**
 * @file
 * User display and avatar helper functions.
 */

/**
 * Get display name for user.
 *
 * @param \Drupal\user\Entity\User $user
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
 * Get initials from a display name string.
 */
function get_initials_from_name(string $display_name): string {
    // Prefer the first author when multiple are comma-separated.
    $primary = trim(explode(',', $display_name, 2)[0]);
    $words = array_values(array_filter(explode(' ', preg_replace('/\s+|\./', ' ', $primary) ?? '')));
    $initials = '';
    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }

    if (mb_strlen($initials) > 2) {
        $initials = mb_substr($initials, 0, 1) . mb_substr($initials, -1);
    }

    return $initials !== '' ? $initials : '?';
}

/**
 * Get initials for user.
 *
 * @param \Drupal\user\Entity\User $user
 */
function get_user_initials($user) {
    return get_initials_from_name(get_user_display_name($user));
}

/**
 * Generate background color for given ID.
 *
 * @param string $id
 */
function generate_background_color($id) {
    if (empty($id)) {
        throw new InvalidArgumentException("ID cannot be empty");
    }

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

    $color_keys = array_keys($colors);
    $color_values = array_values($colors);

    $hash = md5($id);
    $hash = hexdec(substr($hash, 0, 8));

    $color_index = $hash % count($color_keys);
    $color = $color_values[$color_index];

    return $color;
}

/**
 * Generate an initials avatar from a display name.
 */
function generate_avatar_from_name(string $display_name, $size = 100) {
    $initials = get_initials_from_name($display_name);
    $color = generate_background_color($display_name);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '">';
    $svg .= '<rect x="0" y="0" width="' . $size . '" height="' . $size . '" fill="' . $color . '"/>';
    $svg .= '<text x="' . ($size / 2) .  '" y="' . ($size / 2) . '" text-anchor="middle" dy="0.35em" fill="#fff" font-size="' . ($size / 2) . '" font-family="\'Montserrat\', sans-serif">' . htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</text>';
    $svg .= '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Generate avatar for user.
 *
 * @param \Drupal\user\Entity\User $user
 */
function generate_avatar($user, $size = 100) {
    return generate_avatar_from_name(get_user_display_name($user), $size);
}
