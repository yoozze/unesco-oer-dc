/** @module components/ObservatoryForm */

import Form from './Form';

class ObservatoryForm extends Form {
    /**
     * @type {HTMLSelectElement | null} View select.
     */
    viewSelect = null;

    /**
     * @type {HTMLSelectElement | null} Area of action select.
     */
    areaSelect = null;

    /**
     * @type {HTMLIFrameElement | null} Observatory iframe.
     */
    iframe = null;

    /**
     * @type {number} Area taxonomy term ID.
     */
    area = 0;

    /**
     * @type {string} View.
     */
    view = '';

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

        this.viewSelect = this.element.querySelector('[name="view"]');
        this.areaSelect = this.element.querySelector('[name="area"]');

        this.viewSelect.addEventListener('change', this.handleViewChange.bind(this));
        this.areaSelect.addEventListener('change', this.handleAreaChange.bind(this));

        this.iframe = document.querySelector('#observatory-iframe');

        this.view = this.viewSelect.value;
        this.area = Number(this.areaSelect.value);

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
     * Populate area select options for the current view.
     */
    populateAreaSelect() {
        const areas = this.options.views?.[this.view]?.areas ?? [];

        this.areaSelect.innerHTML = '';
        areas.forEach((area) => {
            const option = document.createElement('option');
            option.value = area.tid;
            option.textContent = area.name;
            this.areaSelect.appendChild(option);
        });

        if (areas.length > 0) {
            const currentStillValid = areas.some((area) => area.tid === this.area);
            this.area = currentStillValid ? this.area : Number(areas[0].tid);
            this.areaSelect.value = String(this.area);
        }
    }

    /**
     * Get precomputed iframe config for the current view and area.
     *
     * @returns {{ url: string, aspectRatio: string } | null}
     */
    getIframeConfig() {
        const viewConfig = this.options.views?.[this.view];
        const areaConfig = viewConfig?.areas?.find((area) => area.tid === this.area);

        if (!areaConfig?.url) {
            return null;
        }

        return {
            url: areaConfig.url,
            aspectRatio: viewConfig.aspectRatio,
        };
    }

    /**
     * Update iframe with new area and view.
     */
    updateIframe() {
        const config = this.getIframeConfig();

        if (!config?.url) {
            return;
        }

        if (!this.iframe) {
            this.iframe = document.createElement('iframe');
            this.iframe.id = 'observatory-iframe';
            this.iframe.setAttribute('width', '100%');
            this.iframe.setAttribute('frameborder', '0');
            document.querySelector('.c-article--observatory .c-article__body .c-article__content')?.appendChild(this.iframe);
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
        this.populateAreaSelect();
        this.updateIframe();
    }
}

export default ObservatoryForm;
