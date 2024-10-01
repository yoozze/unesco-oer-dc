/** @module components/MainNavigation */

import Component from './Component';

class MainNavigation extends Component {
    /**
     * @type {HTMLButtonElement | null} Menu toggle button element.
     */
    toggle = null;

    /**
     * @type {HTMLUListElement | null} Menu element.
     */
    menu = null;

    /**
     * Get component block name.
     *
     * @override
     * @returns {string} Component block name.
     */
    static get block() {
        return 'c-navigation';
    }

    /**
     * Get component modifier name.
     *
     * @override
     * @returns {string} Component modifier name.
     */
    static get modifier() {
        return 'main';
    }

    /**
     * MainNavigation constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} options - MainNavigation options.
     */
    constructor(element, options = {}) {
        super(element, options);

        this.toggle = this.element.querySelector(':scope > button');
        this.menu = this.element.querySelector(':scope > ul');

        this.element.addEventListener('focusout', this.handleFocusOut.bind(this));
        this.element.addEventListener('keyup', this.handleKeyUp.bind(this));
        this.toggle.addEventListener('click', this.handleToggleClick.bind(this));
    }

    /**
     * Toggles the menu visibility.
     *
     * @param {boolean} state - Menu visibility state.
     */
    toggleMenu(state) {
        const isVisible = state !== undefined ? !state : this.menu.classList.contains('is-visible');
        if (!isVisible) {
            this.menu.classList.add('is-visible');
            this.toggle.setAttribute('aria-expanded', 'true');
        } else {
            this.menu.classList.remove('is-visible');
            this.toggle.setAttribute('aria-expanded', 'false');
        }
    }

    /**
     * Handle toggle button click event.
     *
     * @param {Event} event - Button click event.
     */
    handleToggleClick(event) {
        event.preventDefault();
        this.toggleMenu();
    }

    /**
     * Handle focus out event.
     *
     * @param {FocusEvent} event - Focus out event.
     */
    handleFocusOut(event) {
        setTimeout(() => {
            if (
                !this.element.contains(document.activeElement) &&
                !this.menu.contains(document.activeElement)
            ) {
                this.toggleMenu(false);
            }
        }, 0);
    }

    /**
     * Handle key up event.
     *
     * @param {Event} event
     */
    handleKeyUp(event) {
        if (event.key === 'Escape') {
            this.toggleMenu(false);
            this.toggle.focus();
        }
    }
}

export default MainNavigation;
