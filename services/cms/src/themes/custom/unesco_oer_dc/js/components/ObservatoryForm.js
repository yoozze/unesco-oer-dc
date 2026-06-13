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
     * @type {string} Area ID (taxonomy term ID or "all").
     */
    area = '';

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

        this.syncSelectionFromUrl();
        this.updateViewDescription();

        const config = this.getIframeConfig();
        const iframeMismatch =
            config && (!this.iframe || this.iframe.getAttribute('src') !== config.url);

        if (iframeMismatch) {
            this.updateIframe(false);
        } else {
            this.updateBrowserHistory(false);
        }
    }

    /**
     * Resolve view and area from the URL, falling back to the rendered selects.
     */
    syncSelectionFromUrl() {
        const url = new URL(window.location.href);
        const urlView = url.searchParams.get('view');
        const urlArea = url.searchParams.get('area');
        const views = this.options.views ?? {};

        if (urlView && views[urlView]) {
            this.view = urlView;
            this.viewSelect.value = urlView;
            this.populateAreaSelect();
        } else {
            this.view = this.viewSelect.value;
        }

        const areas = views[this.view]?.areas ?? [];
        if (urlArea && areas.some(area => area.id === urlArea)) {
            this.area = urlArea;
            this.areaSelect.value = urlArea;
        } else {
            this.area = this.areaSelect.value;
        }
    }

    /**
     * Update browser history with new area and view.
     *
     * @param {boolean} push - Use pushState when true, replaceState when false.
     */
    updateBrowserHistory(push = true) {
        const url = new URL(window.location.href);
        url.searchParams.set('area', this.area);
        url.searchParams.set('view', this.view);

        if (push) {
            window.history.pushState({}, '', url);
        } else {
            window.history.replaceState({}, '', url);
        }
    }

    /**
     * Populate area select options for the current view.
     */
    populateAreaSelect() {
        const areas = this.options.views?.[this.view]?.areas ?? [];

        this.areaSelect.innerHTML = '';
        areas.forEach(area => {
            const option = document.createElement('option');
            option.value = area.id;
            option.textContent = area.name;
            this.areaSelect.appendChild(option);
        });

        if (areas.length > 0) {
            const currentStillValid = areas.some(area => area.id === this.area);
            this.area = currentStillValid ? this.area : areas[0].id;
            this.areaSelect.value = this.area;
        }
    }

    /**
     * Get precomputed iframe config for the current view and area.
     *
     * @returns {{ url: string, aspectRatio: string } | null}
     */
    getIframeConfig() {
        const viewConfig = this.options.views?.[this.view];
        const areaConfig = viewConfig?.areas?.find(area => area.id === this.area);

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
     *
     * @param {boolean} push - Use pushState when true, replaceState when false.
     */
    updateIframe(push = true) {
        const config = this.getIframeConfig();

        if (!config?.url) {
            return;
        }

        if (!this.iframe) {
            this.iframe = document.createElement('iframe');
            this.iframe.id = 'observatory-iframe';
            this.iframe.setAttribute('width', '100%');
            this.iframe.setAttribute('frameborder', '0');
            document
                .querySelector('.c-article--observatory .c-article__body .c-article__content')
                ?.appendChild(this.iframe);
        }

        this.iframe.src = config.url;
        this.iframe.style.aspectRatio = config.aspectRatio;

        this.updateViewDescription();
        this.updateBrowserHistory(push);
    }

    /**
     * Show the description for the active view.
     */
    updateViewDescription() {
        document.querySelectorAll('[data-observatory-view]').forEach(element => {
            element.hidden = element.dataset.observatoryView !== this.view;
        });
    }

    /**
     * Handle area change event.
     * @param {Event} event - Area change event.
     */
    handleAreaChange(event) {
        this.area = event.target.value;
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
