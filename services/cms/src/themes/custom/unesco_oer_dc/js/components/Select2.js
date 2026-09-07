/** @module components/Select2 */

/**
 * Site-wide Select2 tweaks.
 *
 * Removing a multiselect chip bubbles a click to the selection container, which
 * opens the dropdown. Stop that propagation without blocking the unselect itself.
 *
 * @see https://github.com/select2/select2/issues/3209
 */
(function select2Tweaks(Drupal, $) {
    /**
     * Prevent dropdown open when a choice chip remove control is clicked.
     */
    function bindUnselectCloseGuard() {
        $(document).on(
            'select2:unselect.unescoSelect2',
            'select.select2-widget, .select2-hidden-accessible',
            event => {
                event.params?.originalEvent?.stopPropagation();
            },
        );
    }

    Drupal.behaviors.unescoSelect2 = {
        attach() {
            if (!once('unesco-select2', 'html').length) {
                return;
            }

            bindUnselectCloseGuard();
        },
    };

    Drupal.behaviors.unescoSelect2.attach(document, window.drupalSettings || {});
})(Drupal, jQuery);

export default {};
