/** @module components/ViewsExposedForm */

import Component from './Component';
import Form from './Form';

class ViewsExposedForm extends Form {
    /**
     * @type {HTMLButtonElement | null} Submit button element.
     */
    submit = null;

    /**
     * @type {HTMLButtonElement | null} Reset button element.
     */
    reset = null;

    /**
     * @type {HTMLDetailsElement | null} Details element.
     */
    details = null;

    /**
     * Whether document-level AJAX listeners are registered.
     *
     * @type {boolean}
     */
    static documentListenersBound = false;

    /**
     * Get component modifier name.
     *
     * @override
     * @returns {string} Component modifier name.
     */
    static get modifier() {
        return 'views-exposed-form';
    }

    /**
     * Session storage key for advanced-search open state.
     *
     * @param {HTMLFormElement} form - Exposed form element.
     * @returns {string} Storage key.
     */
    static storageKey(form) {
        return `unescoViewsExposedAdvancedOpen:${form.id || 'default'}`;
    }

    /**
     * Whether the AJAX request belongs to a views exposed form refresh.
     *
     * @param {Object} settings - jQuery AJAX settings.
     * @returns {boolean}
     */
    static isViewsExposedFormAjax(settings) {
        if (!settings?.url) {
            return false;
        }

        return settings.url.includes('views/ajax') && Boolean(settings.extraData?.view_name);
    }

    /**
     * Resolve the exposed form targeted by a views AJAX request.
     *
     * @param {Object} settings - jQuery AJAX settings.
     * @returns {HTMLFormElement|null}
     */
    static findFormFromAjaxSettings(settings) {
        const viewDomId = settings?.extraData?.view_dom_id;
        if (viewDomId) {
            const view = document.querySelector(`.js-view-dom-id-${viewDomId}`);
            const form = view?.querySelector('form.views-exposed-form');
            if (form) {
                return form;
            }
        }

        return document.querySelector('form.views-exposed-form');
    }

    /**
     * Close open Select2 widgets inside the form.
     *
     * Select2 renders its dropdown on `body`; if the form is replaced by AJAX
     * while a dropdown is open, the menu can be left behind as an orphan.
     *
     * @param {HTMLFormElement} form - Exposed form element.
     */
    static closeSelect2(form) {
        $(form)
            .find('select.select2-widget')
            .each(function closeWidget() {
                const $select = $(this);
                if ($select.data('select2')) {
                    $select.select2('close');
                }
            });
    }

    /**
     * Remove Select2 dropdown nodes that outlived an AJAX refresh.
     */
    static cleanupOrphanedSelect2Dropdowns() {
        $('body > .select2-dropdown').remove();
        $('.select2-container--open').removeClass('select2-container--open');
    }

    /**
     * Sync layout class used when Advanced search is expanded.
     *
     * News listing CSS uses this (with `:has` fallback) for the open-state
     * ~50/50 split against shared flex grow-swap rules.
     *
     * @param {HTMLFormElement} form - Exposed form element.
     */
    static syncAdvancedOpenClass(form) {
        const details = form.querySelector('details');
        form.classList.toggle('is-advanced-open', Boolean(details?.open));
    }

    /**
     * Remember whether BEF "Advanced search" was expanded.
     *
     * @param {HTMLFormElement} form - Exposed form element.
     */
    static persistAdvancedSearchState(form) {
        const details = form.querySelector('details');
        if (!details) {
            return;
        }

        sessionStorage.setItem(this.storageKey(form), details.open ? '1' : '0');
        this.syncAdvancedOpenClass(form);
    }

    /**
     * Restore advanced-search open state after an AJAX refresh.
     *
     * BEF only keeps the secondary details element open while a secondary filter
     * has a value. Once the last filter is cleared, it falls back to
     * `secondary_open: false`, which collapses the panel even if the user had
     * it open a moment ago.
     *
     * @param {HTMLFormElement} form - Exposed form element.
     */
    static restoreAdvancedSearchState(form) {
        const details = form.querySelector('details');
        if (!details) {
            return;
        }

        if (sessionStorage.getItem(this.storageKey(form)) === '1') {
            details.open = true;
        }

        this.syncAdvancedOpenClass(form);
    }

    /**
     * Register one-time AJAX hooks for all listing exposed forms.
     */
    static bindDocumentListeners() {
        if (this.documentListenersBound) {
            return;
        }

        this.documentListenersBound = true;

        $(document).on('ajaxSend.unescoViewsExposedForm', (event, xhr, settings) => {
            if (!this.isViewsExposedFormAjax(settings)) {
                return;
            }

            const form = this.findFormFromAjaxSettings(settings);
            if (!form) {
                return;
            }

            this.persistAdvancedSearchState(form);
            this.closeSelect2(form);
        });

        $(document).on('ajaxComplete.unescoViewsExposedForm', (event, xhr, settings) => {
            if (!this.isViewsExposedFormAjax(settings)) {
                return;
            }

            this.cleanupOrphanedSelect2Dropdowns();
        });
    }

    /**
     * ViewsExposedForm constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} [options = {}] - Form options.
     */
    constructor(element, options = {}) {
        super(element, options);

        this.constructor.bindDocumentListeners();

        this.submit = this.element.querySelector('button[type="submit"]');
        this.reset = this.element.querySelector('button[type="reset"]');
        this.details = this.element.querySelector('details');

        this.constructor.restoreAdvancedSearchState(this.element);
        this.constructor.syncAdvancedOpenClass(this.element);

        if (this.details) {
            this.details.addEventListener('toggle', () => {
                this.constructor.persistAdvancedSearchState(this.element);
            });
        }

        this.element.addEventListener('submit', () => {
            this.constructor.persistAdvancedSearchState(this.element);
            this.constructor.closeSelect2(this.element);
        });

        if (this.reset) {
            this.reset.addEventListener('click', this.handleResetButtonClick.bind(this));
        }
    }

    /**
     * Handle reset button click event.
     *
     * @param {Event} event - Click event.
     */
    handleResetButtonClick(event) {
        event.preventDefault();
        sessionStorage.setItem(this.constructor.storageKey(this.element), '0');
        this.constructor.closeSelect2(this.element);
        this.element.reset();
        setTimeout(() => {
            // Reset all select2 elements
            $(this.element)
                .find('select.select2-widget')
                .one('change', e => {
                    e.stopPropagation();
                })
                .trigger('change');

            // Submit the form
            this.submit.click();
        }, 0);
    }
}

export default ViewsExposedForm;
