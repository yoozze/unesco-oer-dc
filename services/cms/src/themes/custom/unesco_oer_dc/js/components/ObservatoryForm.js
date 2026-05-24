/** @module components/ObservatoryForm */

import Form from './Form';

class ObservatoryForm extends Form {
    /**
     * @type {HTMLInputElement | null} Area of action radios.
     */
    areaRadios = null;

    /**
     * @type {HTMLInputElement | null} View radios.
     */
    viewRadios = null;

    /**
     * @type {HTMLIFrameElement | null} Observatory iframe.
     */
    iframe = null;

    /**
     * @type {number} Area of action.
     */
    area = 1;

    /**
     * @type {string} View.
     */
    view = 'news';

    /**
     * Get component modifier name.
     *
     * @override
     * @returns {string} Component modifier name.
     */
    static get modifier() {
        return 'observatory';
    }

    /**
     * ObservatoryForm constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} options - ObservatoryForm options.
     */
    constructor(element, options = {}) {
        super(element, options);

        this.areaRadios = this.element.querySelector(`.${Form.bem('radios', 'area')}`);
        this.areaRadios.addEventListener('change', this.handleAreaChange.bind(this));

        this.viewRadios = this.element.querySelector(`.${Form.bem('radios', 'view')}`);
        this.viewRadios.addEventListener('change', this.handleViewChange.bind(this));

        this.iframe = document.querySelector('#observatory-iframe');

        this.view = this.viewRadios.querySelector('input:checked').value;
        this.area = Number(this.areaRadios.querySelector('input:checked').value);

        this.updateBrowserHistory();

        const { defaultView, defaultArea } = this.options;
        if (this.view !== defaultView || this.area !== defaultArea) {
            this.updateIframe();
        }
    }

    /**
     * Update browser history with new area and view.
     */
    updateBrowserHistory() {
        const url = new URL(window.location.href);
        url.searchParams.set('area', this.area);
        url.searchParams.set('view', this.view);
        window.history.pushState({}, '', url);
    }

    /**
     * Get precomputed iframe config for the current view and area.
     *
     * @returns {{ url: string, aspectRatio: string } | null}
     */
    getIframeConfig() {
        const viewConfig = this.options.views?.[this.view];

        if (!viewConfig?.embeds) {
            return null;
        }

        return {
            url: viewConfig.embeds[this.area - 1],
            aspectRatio: viewConfig.aspectRatio,
        };
    }

    /**
     * Update iframe with new area and view.
     */
    updateIframe() {
        const config = this.getIframeConfig();

        if (!config?.url || !this.iframe) {
            return;
        }

        this.iframe.src = config.url;
        this.iframe.style.aspectRatio = config.aspectRatio;

        this.updateViewDescription();
        this.updateBrowserHistory();
    }

    /**
     * Show the description for the active view.
     */
    updateViewDescription() {
        document.querySelectorAll('[data-observatory-view]').forEach((element) => {
            element.hidden = element.dataset.observatoryView !== this.view;
        });
    }

    /**
     * Handle area change event.
     * @param {Event} event - Area change event.
     */
    handleAreaChange(event) {
        this.area = Number(event.target.value);
        this.updateIframe();
    }

    /**
     * Handle view change event.
     * @param {Event} event - View change event.
     */
    handleViewChange(event) {
        this.view = event.target.value;
        this.updateIframe();
    }
}

export default ObservatoryForm;
