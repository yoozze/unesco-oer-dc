/** @module components/Button */

/**
 * @typedef {Object} Props
 * @property {filled | outlined | text | tonal} variant - Button variant.
 * @property {string} leadingIcon - Leading icon name.
 * @property {string} trailingIcon - Trailing icon name.
 * @property {string} label - Button label.
 */

/**
 * @typedef {HTMLButtonElement & Props} ButtonProps
 */

import Component from './Component';

class Button extends Component {
    /**
     * @type {SVGElement | null} Leading icon SVG element.
     */
    leadingIcon = null;

    /**
     * @type {SVGElement | null} Trailing icon SVG element.
     */
    trailingIcon = null;

    /**
     * @type {HTMLSpanElement | null} DOM element to be initialized.
     */
    label = null;

    /**
     * Get component block name.
     *
     * @override
     * @returns {string} Component block name.
     */
    static get block() {
        return 'c-button';
    }

    /**
     * @param {ButtonProps} [props={}] - Button component properties.
     * @param {Object} [options = {}] - Button options.
     * @returns {HTMLButtonElement | null} HTML button element.
     */
    static create(props = {}, options = {}) {
        const {
            className = '',
            label = '',
            variant = 'filled',
            leadingIcon = '',
            trailingIcon = '',
            type = 'button',
            ...attributes
        } = props;
        const button = document.createElement('button');
        button.classList.add('c-button', `c-button--${variant}`, className || undefined);
        button.setAttribute('type', type)
        for (const attributeName of Object.keys(attributes)) {
            const attributeValue = attributes[attributeName];
            if (typeof attributeValue === 'function') {
                button.addEventListener(attributeValue);
            } else {
                button.setAttribute(attributeName, attributeValue);
            }
        }

        button.innerHTML = `
            ${leadingIcon && (
                `<svg class="c-icon c-icon--${leadingIcon} c-button__leading-icon" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <use xlink:href="#${leadingIcon}"></use>
                </svg>`
            )}
            <span class="c-button__label">${label}</span>
            ${trailingIcon && (
                `<svg class="c-icon c-icon--${trailingIcon} c-button__trailing-icon" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <use xlink:href="#${trailingIcon}"></use>
                </svg>`
            )}
        `;

        Button.init([button], options);

        return button;
    }

    /**
     * Button constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} [options = {}] - Button options.
     */
    constructor(element, options = {}) {
        super(element, options);
    }
}

export default Button;
