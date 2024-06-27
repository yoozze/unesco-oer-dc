/** @module components/Dialog */

/**
 * @typedef {Object} Props
 * @property {string} title - Dialog title.
 */

/**
 * @typedef {HTMLElement & Props} DialogProps
 */

import Component from './Component';

class Dialog extends Form {
    /**
     * Get component block name.
     *
     * @override
     * @returns {string} Component block name.
     */
    static get block() {
        return 'c-dialog';
    }

    /**
     * @param {DialogProps} [props={}] - Dialog component properties.
     * @param {Object} [options = {}] - Dialog options.
     * @returns {HTMLDialogElement | null} HTML Dialog element.
     */
    static create(props = {}, options = {}) {
        const {
            className = '',
            label = '',
            ...attributes
        } = props;
        const dialog = document.createElement('dialog');
        dialog.classList.add('c-dialog', `c-dialog--${variant}`, className || undefined);

        
    }

    /**
     * Dialog constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} [options = {}] - Dialog options.
     */
    constructor(element, options = {}) {
        super(element, options);
    }
}

export default Dialog;
