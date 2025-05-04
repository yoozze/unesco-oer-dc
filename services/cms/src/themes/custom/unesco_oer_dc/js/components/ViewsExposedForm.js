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
     * Get component modifier name.
     *
     * @override
     * @returns {string} Component modifier name.
     */
    static get modifier() {
        return 'views-exposed-form';
    }

    /**
     * ViewsExposedForm constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} [options = {}] - Form options.
     */
    constructor(element, options = {}) {
        super(element, options);

        this.submit = this.element.querySelector('button[type="submit"]');
        this.reset = this.element.querySelector('button[type="reset"]');
        this.details = this.element.querySelector('details');

        this.reset.addEventListener('click', this.handleResetButtonClick.bind(this));
    }

    /**
     * Handle reset button click event.
     *
     * @param {Event} event - Click event.
     */
    handleResetButtonClick(event) {
        event.preventDefault();
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
