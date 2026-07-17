/** @module components/MediaLibraryDialog */

/**
 * Media library dialog fixes:
 * - Ensure dialog footer exists for core selection count + submit action.
 * - Swap jQuery UI proxy buttons for real theme buttons in the footer.
 */
(function mediaLibraryDialog(Drupal, $) {
    function isMediaLibraryDialog($dialog) {
        return $dialog.find('.js-media-library-views-form, .js-media-library-add-form').length > 0;
    }

    function getDialogContent($dialog) {
        return $dialog.hasClass('ui-dialog-content') ? $dialog : $dialog.find('.ui-dialog-content');
    }

    function hasSubmitButtons($container) {
        return $container.find('button[type=submit], input[type=submit]').length > 0;
    }

    function findAddFormSaveButtons($scope) {
        return $scope.find(
            '[id^="edit-save-select"], [id^="edit-save-insert"], [name="save_select"], [name="save_insert"]',
        );
    }

    function isAddFormSaveButton($button) {
        const id = $button.attr('id') || '';
        const name = $button.attr('name') || '';

        return (
            id.startsWith('edit-save-select') ||
            id.startsWith('edit-save-insert') ||
            name === 'save_select' ||
            name === 'save_insert'
        );
    }

    function isAddFormSaveStateActive($content) {
        return findAddFormSaveButtons($content).length > 0;
    }

    function findAddFormActions($content) {
        const $saveButton = findAddFormSaveButtons($content).first();

        if (!$saveButton.length) {
            return $();
        }

        const $actions = $saveButton.closest('.form-actions, .c-form__container--form-actions');

        return $actions.length ? $actions : $();
    }

    function findViewsFormActions($content) {
        const $viewsForm = $content.find('.js-media-library-views-form').first();

        if (!$viewsForm.length) {
            return $();
        }

        const $viewsFormActions = $viewsForm
            .children('.form-actions, .c-form__container--form-actions')
            .first();

        if (hasSubmitButtons($viewsFormActions)) {
            return $viewsFormActions;
        }

        return $();
    }

    function findContentMediaLibraryActions($content) {
        if (isAddFormSaveStateActive($content)) {
            return findAddFormActions($content);
        }

        return findViewsFormActions($content);
    }

    function findMediaLibraryActions($content, $buttonSet) {
        const $contentActions = findContentMediaLibraryActions($content);

        if ($contentActions.length) {
            return $contentActions;
        }

        if (isAddFormSaveStateActive($content)) {
            return $();
        }

        if ($buttonSet?.length) {
            const $footerActions = getFooterActions($buttonSet)
                .filter(function filterFooterActions() {
                    return hasSubmitButtons($(this));
                })
                .first();

            if ($footerActions.length) {
                return $footerActions;
            }
        }

        return $();
    }

    function showActions($actions) {
        $actions.css('display', 'flex');
        $actions.find('button[type=submit], input[type=submit]').css('display', '');
    }

    function getFooterActions($buttonSet) {
        return $buttonSet.children('.form-actions, .c-form__container--form-actions');
    }

    function getButtonLabel($button) {
        const label = $button.find('.c-button__label').first().text().trim();

        return (
            label || $button.text().trim() || $button.attr('value') || $button.attr('aria-label')
        );
    }

    function updateSelectionCountText() {
        const currentSelection = Drupal.MediaLibrary?.currentSelection ?? [];
        const remaining = window.drupalSettings?.media_library?.selection_remaining ?? -1;
        const selectItemsText =
            remaining < 0
                ? Drupal.formatPlural(
                      currentSelection.length,
                      '1 item selected',
                      '@count items selected',
                  )
                : Drupal.formatPlural(
                      remaining,
                      '@selected of @count item selected',
                      '@selected of @count items selected',
                      {
                          '@selected': currentSelection.length,
                      },
                  );

        $('.media-library-widget-modal .js-media-library-selected-count').html(selectItemsText);
    }

    function preserveSelectionCount($modal) {
        const $selectedCount = $modal.find('.js-media-library-selected-count').first();

        if ($selectedCount.length) {
            $modal.data('unescoMediaLibrarySelectionCount', $selectedCount.detach());
        }
    }

    function ensureSelectionCount($modal, $buttonPane) {
        let $selectedCount = $modal.data('unescoMediaLibrarySelectionCount');

        if ($selectedCount?.length) {
            $modal.removeData('unescoMediaLibrarySelectionCount');
        } else {
            $selectedCount = $buttonPane.find('.js-media-library-selected-count').first();
        }

        if (!$selectedCount.length && Drupal.theme?.mediaLibrarySelectionCount) {
            $selectedCount = $(Drupal.theme('mediaLibrarySelectionCount'));
        }

        if ($selectedCount.length) {
            $buttonPane.prepend($selectedCount);
            updateSelectionCountText();
        }
    }

    /**
     * Core dialog.ajax.js only moves input[type=submit] into the button pane.
     * This theme renders submits as button elements.
     */
    function patchPrepareDialogButtons() {
        if (
            !Drupal.behaviors?.dialog?.prepareDialogButtons ||
            Drupal.behaviors.dialog.prepareDialogButtons._unescoPatched
        ) {
            return;
        }

        Drupal.behaviors.dialog.prepareDialogButtons = function prepareDialogButtons($dialog) {
            const buttons = [];
            const mediaLibraryDialog = isMediaLibraryDialog($dialog);
            const $searchRoot = getDialogContent($dialog);
            const addFormSaveState = findAddFormSaveButtons($searchRoot).length > 0;
            const $buttons = $searchRoot
                .find(
                    '.form-actions input[type=submit], .form-actions button[type=submit], .form-actions a.button, .form-actions a.action-link',
                )
                .filter(function filterContentActions() {
                    const $button = $(this);

                    if ($button.closest('.ui-dialog-buttonpane').length) {
                        return false;
                    }

                    if (addFormSaveState) {
                        return isAddFormSaveButton($button);
                    }

                    return true;
                });

            $buttons.each(function eachDialogButton() {
                const $originalButton = $(this);
                const text = getButtonLabel($originalButton);

                // Keep a proxy in the button pane so core can render the footer.
                this.style.display = 'none';
                buttons.push({
                    text,
                    class: mediaLibraryDialog
                        ? 'js-media-library-ui-button-proxy button'
                        : $originalButton.attr('class'),
                    'data-once': $originalButton.data('once'),
                    click(e) {
                        if ($originalButton[0].tagName === 'A') {
                            $originalButton[0].click();
                        } else {
                            $originalButton
                                .trigger('mousedown')
                                .trigger('mouseup')
                                .trigger('click');
                        }
                        e.preventDefault();
                    },
                });
            });

            return buttons;
        };
        Drupal.behaviors.dialog.prepareDialogButtons._unescoPatched = true;
    }

    function getStubDialogButton() {
        return {
            text: '\u200b',
            class: 'js-media-library-ui-button-proxy visually-hidden',
            click(e) {
                e.preventDefault();
            },
        };
    }

    /**
     * Keep the jQuery UI button pane alive without wiping footer actions that
     * were already moved out of the dialog content (e.g. after pagination AJAX).
     */
    function syncDialogButtonPane($modal) {
        const $content = getDialogContent($modal);
        const $buttonPane = $modal.find('.ui-dialog-buttonpane');
        const $buttonSet = $modal.find('.ui-dialog-buttonset');
        const $contentActions = findContentMediaLibraryActions($content);
        const hasFooterButtons = getFooterActions($buttonSet).filter(
            function filterFooterActions() {
                return hasSubmitButtons($(this));
            },
        ).length;

        if ($buttonPane.length) {
            if (!$contentActions.length || $buttonSet.has($contentActions[0]).length) {
                return;
            }
        }

        preserveSelectionCount($modal);

        const proxyButtons = Drupal.behaviors.dialog.prepareDialogButtons($content);

        if (proxyButtons.length) {
            $content.dialog('option', 'buttons', proxyButtons);
        } else if (!$buttonPane.length && hasFooterButtons) {
            $content.dialog('option', 'buttons', [getStubDialogButton()]);
        } else if (!$buttonPane.length) {
            $content.dialog('option', 'buttons', proxyButtons);
        }
    }

    function setupMediaLibraryFooter($modal) {
        const $content = getDialogContent($modal);

        if (!$content.length) {
            return;
        }

        syncDialogButtonPane($modal);

        const $buttonPane = $modal.find('.ui-dialog-buttonpane');
        const $buttonSet = $modal.find('.ui-dialog-buttonset');

        if (!$buttonPane.length || !$buttonSet.length) {
            return;
        }

        // Remove jQuery UI proxy buttons; use the real theme button instead.
        $buttonSet.find('.ui-button').remove();

        const $saveActions = findAddFormActions($content);

        if ($saveActions.length) {
            getFooterActions($buttonSet).remove();
            if (!$buttonSet.has($saveActions[0]).length) {
                $buttonSet.append($saveActions);
            }
            showActions($saveActions);
            ensureSelectionCount($modal, $buttonPane);
            return;
        }

        const $contentActions = findMediaLibraryActions($content, $buttonSet);
        const $footerActions = getFooterActions($buttonSet);
        const $footerActionsWithButtons = $footerActions.filter(function filterFooterActions() {
            return hasSubmitButtons($(this));
        });

        if ($contentActions.length && !$buttonSet.has($contentActions[0]).length) {
            $footerActions.remove();
            $buttonSet.append($contentActions);
            showActions($contentActions);
        } else if ($footerActionsWithButtons.length) {
            showActions($footerActionsWithButtons.first());
        } else if ($contentActions.length) {
            showActions($contentActions);
        } else {
            $footerActions
                .filter(function filterEmptyFooterActions() {
                    return !hasSubmitButtons($(this));
                })
                .remove();
        }

        ensureSelectionCount($modal, $buttonPane);
    }

    function handleDialogButtonsChange($modal) {
        window.requestAnimationFrame(() => {
            setupMediaLibraryFooter($modal);
        });
    }

    function bindMediaLibraryDialog($modal) {
        const $content = getDialogContent($modal);

        // Replace core's handler so an empty prepareDialogButtons result does
        // not remove the footer after actions were moved out of the content.
        $content
            .off('dialogButtonsChange.unescoMediaLibrary dialogButtonsChange')
            .on('dialogButtonsChange.unescoMediaLibrary', () => {
                handleDialogButtonsChange($modal);
            });
    }

    Drupal.behaviors.unescoMediaLibraryDialog = {
        attach() {
            patchPrepareDialogButtons();

            if (!once('unesco-media-library-dialog', 'html').length) {
                return;
            }

            window.addEventListener('dialog:aftercreate', event => {
                const $modal = $(event.target).closest('.media-library-widget-modal');
                if (!$modal.length) {
                    return;
                }

                bindMediaLibraryDialog($modal);

                // Run after core media library adds the selection count.
                window.requestAnimationFrame(() => {
                    setupMediaLibraryFooter($modal);
                });
            });

            $(document).on(
                'change.unescoMediaLibrarySelectionCount',
                '.media-library-widget-modal .js-media-library-item input[type="checkbox"]',
                () => {
                    window.requestAnimationFrame(updateSelectionCountText);
                },
            );

            $(document).on('ajaxComplete.unescoMediaLibrary', (event, xhr, settings) => {
                if (!settings?.url?.includes('media-library')) {
                    return;
                }

                $('.media-library-widget-modal').each(function eachMediaLibraryModal() {
                    window.requestAnimationFrame(() => {
                        setupMediaLibraryFooter($(this));
                    });
                });
            });
        },
    };

    Drupal.behaviors.unescoMediaLibraryDialog.attach(document, window.drupalSettings || {});
})(Drupal, jQuery);

export default {};
