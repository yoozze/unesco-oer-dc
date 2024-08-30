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
    }
}

function unesco_oer_dc_theme_suggestions_form_alter(&$suggestions, &$variables) {
    $element = &$variables['element'];
    $suggestions[] = implode('__', [
        'form',
        $element['#form_id'],
        // $element['#bundle'],
        // $element['#view_mode']
    ]);
}

function unesco_oer_dc_preprocess_form(&$variables) {
    // $variables['test'] = 'test';
}
