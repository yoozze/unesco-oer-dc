/**
 * @file
 * Show Source / publisher on Content overview only for News / Resource.
 */
((Drupal, once, drupalSettings) => {
    const settings = () => drupalSettings.adminContentSourceFilter || {};

    /**
     * @param {string} type
     * @returns {boolean}
     */
    function isSourceBundle(type) {
        const bundles = settings().bundles || [];
        return bundles.includes(type);
    }

    /**
     * @param {string} type
     * @returns {string}
     */
    function autocompletePathForType(type) {
        const base = settings().autocompletePath || '';
        if (!base || !type) {
            return base;
        }

        const separator = base.includes('?') ? '&' : '?';
        return `${base}${separator}type=${encodeURIComponent(type)}`;
    }

    /**
     * @param {HTMLElement} input
     * @returns {HTMLElement|null}
     */
    function sourceWrapper(input) {
        return (
            input.closest('.js-content-source-filter') ||
            input.closest('.form-item') ||
            input.parentElement
        );
    }

    /**
     * Clear Drupal autocomplete cache for an input so type switches refresh.
     *
     * @param {HTMLElement} input
     */
    function clearAutocompleteCache(input) {
        if (!window.jQuery || !Drupal.autocomplete || !Drupal.autocomplete.cache) {
            return;
        }

        const id = window.jQuery(input).attr('id');
        if (id && Drupal.autocomplete.cache[id]) {
            Drupal.autocomplete.cache[id] = {};
        }
    }

    /**
     * @param {HTMLFormElement} form
     */
    function syncSourceFilter(form) {
        const typeSelect = form.querySelector(
            'select.js-content-type-filter, .js-content-type-filter',
        );
        const input = form.querySelector(
            '.js-content-source-input, input[name="field_source_value"]',
        );
        if (!typeSelect || !input) {
            return;
        }

        const wrapper = sourceWrapper(input);
        if (!wrapper) {
            return;
        }

        wrapper.classList.add('js-content-source-filter');

        const type = String(typeSelect.value || '');
        const visible = isSourceBundle(type);
        wrapper.classList.toggle('js-content-source-filter--visible', visible);

        if (!visible) {
            if (input.value) {
                input.value = '';
            }

            clearAutocompleteCache(input);
            return;
        }

        input.setAttribute('data-autocomplete-path', autocompletePathForType(type));
        clearAutocompleteCache(input);
    }

    Drupal.behaviors.adminContentSourceFilter = {
        attach(context) {
            once('admin-content-source-filter', 'form.js-content-exposed-form', context).forEach(
                form => {
                    const typeSelect = form.querySelector(
                        'select.js-content-type-filter, .js-content-type-filter',
                    );
                    if (typeSelect) {
                        typeSelect.addEventListener('change', () => {
                            syncSourceFilter(form);
                        });
                    }

                    syncSourceFilter(form);
                },
            );
        },
    };
})(Drupal, once, drupalSettings);
