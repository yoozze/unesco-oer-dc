/** @module components/SearchForm */

import Component from './Component';
import Form from './Form';
import Button from './Button';

class SearchForm extends Form {
    /**
     * @type {HTMLInputElement | null} Search input element.
     */
    input = null;

    /**
     * @type {HTMLButtonElement | null} Submit button element.
     */
    submit = null;

    /**
     * @type {HTMLButtonElement | null} X5GON button element.
     */
    xButton = null;

    /**
     * @type {HTMLDialogElement | null} X5GON dialog element.
     */
    xDialog = null;

    /**
     * Get component modifier name.
     *
     * @override
     * @returns {string} Component modifier name.
     */
    static get modifier() {
        return 'search-form';
    }

    /**
     * SearchForm constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} options - SearchForm options.
     */
    constructor(element, options = {}) {
        super(element, options);

        this.submit = this.element.querySelector('button[type="submit"]');
        this.input = this.element.querySelector('input[type="search"]');

        this.xButton = this.createXButton();
        this.xButton.addEventListener('click', this.handleXButtonClick.bind(this));

        this.xDialog = this.createXDialog();

        this.input.addEventListener('input', this.handleInputChange.bind(this));
    }

    /**
     * Create x5gon search button.
     * @returns {HTMLButtonElement} x5gon search button.
     */
    createXButton() {
        const xButton = Button.create({
            className: 'c-form__x-button',
            // TODO: translate
            label: 'Search X5GON',
            variant: 'outlined',
        });
        if (this.input.value.trim() === '') {
            xButton.setAttribute('disabled', 'disabled');
        }

        this.submit.insertAdjacentElement('afterend', xButton);

        return xButton;
    }

    /**
     * Create x5gon dialog.
     * @returns {HTMLDialogElement} x5gon dialog.
     */
    createXDialog() {
        const xDialog = document.createElement('dialog');
        xDialog.classList.add('c-dialog');
        xDialog.innerHTML = `
                <div class="c-dialog__header">
                    <h2 class="c-dialog__title">X5GON: Search results for "<b>...</b>"</h2>
                    <button class="c-dialog__close c-icon-button c-icon-button--standard" type="button">
                        <svg class="c-icon c-icon--close c-icon-button__icon" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <use xlink:href="#close"></use>
                        </svg>
                    </button>
                </div>
                <div class="c-dialog__body">
                    <div class="c-dialog__content">
                        <p>Searching...</p>
                    </div>
                </div>
            `;

        xDialog.querySelector('.c-dialog__close').addEventListener('click', () => {
            xDialog.close();
        });

        this.element.appendChild(xDialog);

        return xDialog;
    }

    async loadXResults(page = 1) {
        const value = this.input.value.trim();
        if (value !== '') {
            this.xDialog.querySelector('.c-dialog__title b').innerText = value;
            this.xDialog.querySelector('.c-dialog__content').innerTML = `<p>Searching...</p>`;
            this.xDialog.showModal();

            console.log(value);
            const response = await fetch(
                `https://platform.x5gon.org/api/v2/search?text=${value}&page=${page}`,
            );
            const results = await response.json();
            console.log(results);

            const xDialogContent = this.xDialog.querySelector('.c-dialog__content');
            if (results.rec_materials.length === 0) {
                xDialogContent.innerHTML = `
                    <p>No results found.</p>
                `;
            } else {
                xDialogContent.innerHTML = `
                    <ul class="c-list">
                        ${results.rec_materials
                            .map(
                                result => `
                            <li class="c-list__item">
                                <a class="c-link" href="${result.url}" target="_blank" rel="noopener noreferrer">${result.title}</a>
                                <br/>
                                <span>Provider: <a href="${result.provider.domain}">${result.provider.name}</a></span>
                                <br/>
                                <span>Language: ${result.language_full}</span>
                            </li>
                        `,
                            )
                            .join('')}
                    </ul>
                `;
            }
        }
    }

    /**
     * Handle form submit click event.
     * @param {PointerEvent} event - Pointer event.
     */
    async handleXButtonClick(event) {
        await this.loadXResults();
    }

    /**
     * Handle input keydown event.
     * @param {InputEvent} event - Input event.
     */
    handleInputChange(event) {
        console.log(event.target.value);
        if (event.target.value.trim() !== '') {
            this.xButton.removeAttribute('disabled');
        } else {
            this.xButton.setAttribute('disabled', 'disabled');
        }
    }
}

export default SearchForm;
