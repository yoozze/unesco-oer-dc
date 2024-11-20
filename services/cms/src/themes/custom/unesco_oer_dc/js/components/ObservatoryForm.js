/** @module components/ObservatoryForm */

import Component from './Component';
import Form from './Form';

import $ from 'jquery';

const VIEWS = {
    news: 'news',
    dashboard: 'dashboard',
};

const DEFAULT_VIEW = VIEWS.news;
const DEFAULT_AREA = 1;

class ObservatoryForm extends Form {
    /**
     * @type {HTMLInputElement | null} Area of action radios.
     */
    areaRadios = null;

    /**
     * @type {HTMLInputElement | null} Area of action radios.
     */
    viewRadios = null;

    /**
     * @type {HTMLIFrameElement | null} Observatory iframe.
     */
    iframe = null;

    /**
     * @type {number} Area of action.
     */
    area = DEFAULT_AREA;

    /**
     * @type {string} View.
     * @default VIEWS.news
     */
    view = DEFAULT_VIEW;

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
     * SearchForm constructor.
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

        // Query the iframe element by observatory-iframe id.
        this.iframe = document.querySelector('#observatory-iframe');

        // Set the initial view vrom selected radio button.
        this.view = this.viewRadios.querySelector('input:checked').value;

        // Set the initial area from selected radio button.
        this.area = Number(this.areaRadios.querySelector('input:checked').value);

        // Update browser history with initial area and view.
        this.updateBrowserHistory();

        // Update iframe with initial area and view if necessary.
        if (this.view !== DEFAULT_VIEW || this.area !== DEFAULT_AREA) {
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
     * Update iframe with new area and view
     * @returns {void}
     */
    updateIframe() {
        const keys = this.view === VIEWS.news ? [
            'f23b14f7-60d1-4786-9ca4-f8fdd56682ec',
            '9365e3e0-4914-41b5-822a-9e373e2fd3fa',
            '1ebf4034-deab-452e-a0e4-edf356eb2139',
            '50c9b223-8eec-49ee-b6cb-46631ed37dcf',
            'f9d34ef5-0d04-4e2e-8fc7-ade59aef9801',,
        ] : [
            '2d2a5660-a46b-11ef-bb8e-094d83929987',
            '0dbc27c0-a46d-11ef-bb8e-094d83929987',
            '37487260-a46d-11ef-bb8e-094d83929987',
            '5dab4270-a46d-11ef-bb8e-094d83929987',
            '7f6bb390-a46d-11ef-bb8e-094d83929987',
        ];

        const url = this.view === VIEWS.news
            ? `https://news-widget.pages.dev/news?sdg=4&topicKey=${keys[this.area - 1]}`
            : `https://public.midas.ijs.si/kibana-sgd/app/dashboards#/view/${keys[this.area - 1]}?embed=true&_g=(refreshInterval:(pause:!t,value:60000),time:(from:now-150y,to:now))&_a=()`;

        const aspectRatio = this.view === VIEWS.news ? '1440 / 1280' : '1440 / 900';

        // create new iframe element
        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.id = 'observatory-iframe';
        iframe.setAttribute('width', '100%');
        iframe.setAttribute('frameborder', '0');
        iframe.style.aspectRatio = aspectRatio;

        // replace old iframe with new iframe
        this.iframe.replaceWith(iframe);
        this.iframe = iframe;

        // update browser history with new area and view
        this.updateBrowserHistory();
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
