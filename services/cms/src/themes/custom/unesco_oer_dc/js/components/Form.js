/** @module components/Form */

import Component from './Component';

class Form extends Component {
    /**
     * Get component block name.
     *
     * @override
     * @returns {string} Component block name.
     */
    static get block() {
        return 'c-form';
    }

    /**
     * Form constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} [options = {}] - Form options.
     */
    constructor(element, options = {}) {
        super(element, options);
    }
}

export default Form;
