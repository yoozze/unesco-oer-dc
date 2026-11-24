<?php

/**
 * @file
 * Theme hooks for comments.
 */

use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\user\UserInterface;

/**
 * Implements hook_preprocess_HOOK() for comment.html.twig.
 */
function unesco_oer_dc_preprocess_comment(array &$variables): void {
    $comment = $variables['comment'];
    $author = $comment->getOwner();

    $display_name = $author instanceof UserInterface
        ? get_user_display_name($author)
        : (string) ($variables['author'] ?? t('Anonymous'));

    $variables['author_display_name'] = $display_name;
    $variables['author_url'] = NULL;

    if ($author instanceof UserInterface && !$author->isAnonymous()) {
        $share_profile = $author->hasField('field_share_profile')
            && (bool) $author->get('field_share_profile')->value;
        if ($share_profile) {
            $variables['author_url'] = Url::fromRoute('entity.user.canonical', [
                'user' => $author->id(),
            ])->toString();
        }
    }

    $picture_classes = ['c-comment__avatar-image'];
    $uri = NULL;

    if ($author instanceof UserInterface && !$author->isAnonymous()) {
        $picture = $author->get('user_picture')->entity;
        if ($picture) {
            $file_uri = $picture->getFileUri();
            $is_svg = str_ends_with(strtolower($file_uri), '.svg');
            if ($is_svg) {
                $picture_classes[] = 'c-comment__avatar-image--logo';
                $uri = \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);
            } else {
                $style = ImageStyle::load('thumbnail');
                $uri = $style ? $style->buildUrl($file_uri) : \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);
            }
        } else {
            $uri = generate_avatar($author, 80);
        }
    } else {
        $uri = generate_avatar_from_name($display_name, 80);
    }

    $variables['author_picture'] = [
        '#theme' => 'image',
        '#uri' => $uri,
        '#alt' => $display_name,
        '#attributes' => [
            'class' => $picture_classes,
        ],
    ];
}
