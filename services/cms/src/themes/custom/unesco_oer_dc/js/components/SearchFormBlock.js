/** @module components/SearchFormBlock */

import Component from './Component';

class SearchFormBlock extends Component {
    /**
     * @type {HTMLFormElement | null} Form element.
     */
    form = null;

    /**
     * @type {HTMLButtonElement | null} Submit button element.
     */
    submit = null;

    /**
     * @type {HTMLInputElement | null} Search input element.
     */
    input = null;

    /**
     * Get component block name.
     *
     * @override
     * @returns {string} Component block name.
     */
    static get block() {
        return 'c-search-form-block';
    }

    /**
     * SearchFormBlock constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} options - SearchFormBlock options.
     */
    constructor(element, options = {}) {
        super(element, options);

        this.form = this.element.querySelector('form');
        this.submit = this.element.querySelector('button[type="submit"]');
        this.input = this.element.querySelector('input[type="search"]');

        this.submit.addEventListener('click', this.handleSubmitClick.bind(this));
        this.input.addEventListener('keydown', this.handleInputKeydown.bind(this));
    }

    /**
     * Handle form submit event.
     * @param {SubmitEvent} event - Form submit event.
     */
    handleFormSubmit(event) {}

    /**
     * Handle form submit button click event.
     * @param {PointerEvent} event - Pointer event.
     */
    handleSubmitClick(event) {
        const value = this.input.value.trim();
        let isActive = this.form.classList.contains('is-active');

        if (!isActive) {
            this.form.classList.add('is-active');
            isActive = true;
        } else if (value === '') {
            this.form.classList.remove('is-active');
            isActive = false;
        }

        if (value === '') {
            event.preventDefault();
            if (isActive) {
                this.input.focus();
            } else {
                this.submit.focus();
            }
        }
    }

    /**
     * Handle input keydown event.
     * @param {KeyboardEvent} event - Keyboard event.
     */
    handleInputKeydown(event) {
        if (event.key === 'Escape') {
            this.form.classList.remove('is-active');
            this.submit.focus();
        }
    }
}

export default SearchFormBlock;
