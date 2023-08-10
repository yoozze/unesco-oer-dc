/** @module components/SearchForm */

import Component from './Component';

class SearchForm extends Component {
    /**
     * @type {HTMLInputElement | null} Search input element.
     */
    input = null;

    /**
     * @type {HTMLButtonElement | null} Submit button element.
     */
    submit = null;

    /**
     * @type {HTMLButtonElement | null} Submit button element.
     */
    xButton = null;

    /**
     * @type {HTMLDialogElement | null} Submit button element.
     */
    xDialog = null;

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

        this.input.addEventListener('keydown', this.handleInputKeydown.bind(this));
    }

    /**
     * Create x5gon search button.
     * @returns {HTMLButtonElement} x5gon search button.
     */
    createXButton() {
        console.log('Creating x5gon search...');
        const xButton = document.createElement('button');
        xButton.classList.add('c-form__x-button', 'c-button', 'c-button--outlined');
        xButton.setAttribute('type', 'button');
        if (this.input.value.trim() === '') {
            // xButton.classList.add('is-hidden');
            xButton.setAttribute('disabled', 'disabled');
        }

        const xButtonLabel = document.createElement('span');
        xButtonLabel.classList.add('c-button__label');
        xButtonLabel.innerText = 'Search x5gon';

        xButton.appendChild(xButtonLabel);
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
                    <h2 class="c-dialog__title">Search results for "<b>...</b>"</h2>
                    <button class="c-dialog__close c-icon-button c-icon-button--standard" type="button">
                        <svg class="c-icon c-icon--search c-icon-button__icon" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="#close"></use></svg>
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
     * Handle form submit event.
     * @param {SubmitEvent} event - Form submit event.
     */
    handleFormSubmit(event) {
        // console.log(event);
        // if (!this.form.classList.contains('is-active')) {
        //     this.form.classList.add('is-active');
        //     this.input.focus();
        //     console.log('preventDefault');
        //     event.preventDefault();
        //     return true;
        // }
        // return true;
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
     * @param {KeyboardEvent} event - Keyboard event.
     */
    handleInputKeydown(event) {
        // if (event.key === 'Escape') {
        //     this.form.classList.remove('is-active');
        //     this.submit.focus();
        // }
        if (this.input.value.trim() !== '') {
            this.xButton.removeAttribute('disabled');
        } else {
            this.xButton.setAttribute('disabled', 'disabled');
        }
    }
}

export default SearchForm;
