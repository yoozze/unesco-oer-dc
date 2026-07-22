<?php

function unesco_oer_dc_form_alter(&$form, &$form_state, $form_id) {
    if ($form_id === 'comment_resource_feedback_form') {
        // $form['actions']['submit']['#trailing_icon'] = 'arrow-right-alt';
        $form['actions']['submit']['#value'] = t('Submit');
        $form['field_comment_action_area_i']['#states'] = [
            'visible' => [
                ':input[name^="field_recommendations_i["]' => ['checked' => TRUE]
            ]
        ];
        $form['field_comment_action_area_ii']['#states'] = [
            'visible' => [
                ':input[name^="field_recommendations_ii["]' => ['checked' => TRUE]
            ]
        ];
        $form['field_comment_action_area_iii']['#states'] = [
            'visible' => [
                ':input[name^="field_recommendations_iii["]' => ['checked' => TRUE]
            ]
        ];
        $form['field_comment_action_area_iv']['#states'] = [
            'visible' => [
                ':input[name^="field_recommendations_iv["]' => ['checked' => TRUE]
            ]
        ];
        $form['field_comment_action_area_v']['#states'] = [
            'visible' => [
                ':input[name^="field_recommendations_v["]' => ['checked' => TRUE]
            ]
        ];
    } else if ($form_id === 'node_event_form') {
        // $field_area_of_action = $form['field_area_of_action'];
        // Set default value
        // $form['field_area_of_action']['widget']['#default_value'] = ['985'];
        // $default_value = $form['field_area_of_action']['widget']['#default_value'];
        // $field_area_of_action['widget']['#value'] = ['985'];
    }

    if ($form_id === 'views_exposed_form' && str_starts_with($form['#id'] ?? '', 'views-exposed-form-media-library')) {
        if (isset($form['name'])) {
            if (empty($form['name']['#title'])) {
                $form['name']['#title'] = t('Name');
            }
            $form['name']['#title_display'] = 'after';
            $form['name']['#attributes']['placeholder'] = ' ';
        }
        if (isset($form['sort_by'])) {
            $form['sort_by']['#title_display'] = 'after';
        }
    }

    if (
        !empty($form['#attributes']['class'])
        && is_array($form['#attributes']['class'])
        && in_array('js-media-library-add-form', $form['#attributes']['class'], TRUE)
    ) {
        if (isset($form['container']['upload'])) {
            $form['container']['upload']['#title_display'] = 'after';
        }
        if (isset($form['actions']['save_insert'])) {
            $form['actions']['save_insert']['#button_variant'] = 'outlined';
        }
    }
}

function unesco_oer_dc_theme_suggestions_form_alter(&$suggestions, &$variables) {
    $element = &$variables['element'];
    $suggestions[] = 'form__' . clean_suggetion($element['#form_id']);
    $suggestions[] = 'form__' . clean_suggetion($element['#id']);

    if (!empty($element['#id']) && str_starts_with($element['#id'], 'views-exposed-form-media-library')) {
        $suggestions[] = 'form__views_exposed_form_media_library';
    }
}

function unesco_oer_dc_preprocess_form(&$variables) {
    // $variables['test'] = 'test';
}

/**
 * Prepares variables for datetime wrapper templates.
 */
function unesco_oer_dc_preprocess_datetime_wrapper(&$variables) {
    $element = $variables['element'];

    if (!empty($element['date']['#id'])) {
        if ($variables['title_attributes'] instanceof \Drupal\Core\Template\Attribute) {
            $variables['title_attributes']->setAttribute('for', $element['date']['#id']);
        }
        else {
            $variables['title_attributes']['for'] = $element['date']['#id'];
        }
    }
}
