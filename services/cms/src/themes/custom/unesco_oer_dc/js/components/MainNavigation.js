/** @module components/MainNavigation */

import Component from './Component';
import debounce from '../utils/debounce';
import MediaQuery, { breakpoint } from '../utils/mediaQuery';

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
     * @type {HTMLAnchorElement | null} Submenu toggle button element.
     */
    submenuToggle = null;

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
        this.submenuItems = this.menu.querySelectorAll(':scope > li.has-submenu');

        this.element.addEventListener('focusout', this.handleFocusOut.bind(this));
        this.element.addEventListener('keyup', this.handleKeyUp.bind(this));
        this.toggle.addEventListener('click', this.handleToggleClick.bind(this));
        this.submenuItems.forEach(item => {
            const submenuToggle = item.querySelector(':scope > a.is-toggle');
            submenuToggle.addEventListener('click', this.handleSubmenuToggleClick.bind(this));
            item.addEventListener('keyup', this.handleSubmenuKeyUp.bind(this));
            item.addEventListener('focusout', this.handleSubmenuFocusOut.bind(this));
        });
        // window.addEventListener('resize', debounce(this.handleResize.bind(this), 100));
    }

    /**
     * Toggles the menu visibility.
     *
     * @param {HTMLElement} menu - Menu element.
     * @param {boolean} state - Menu visibility state.
     * @returns {boolean} Menu visibility state.
     */
    toggleMenu(menu, state) {
        // TODO: Use popper.js for positioning.
        const toggle = menu.previousElementSibling;
        const isVisible = state !== undefined ? state : !menu.classList.contains('is-visible');
        if (isVisible) {
            menu.classList.add('is-visible');
            toggle.setAttribute('aria-expanded', 'true');
        } else {
            menu.classList.remove('is-visible');
            toggle.setAttribute('aria-expanded', 'false');
        }

        if (menu === this.menu) {
            if (isVisible) {
                const boundingBox = this.menu.getBoundingClientRect();
                this.menu.style.maxHeight = `calc(100vh - ${boundingBox.top}px)`;
                this.menu.style.overflowY = 'auto';
            } else {
                this.menu.style.maxHeight = '';
                this.menu.style.overflowY = '';
            }
        }

        return isVisible;
    }



    /**
     * Handle toggle button click event.
     *
     * @param {Event} event - Button click event.
     */
    handleToggleClick(event) {
        event.preventDefault();
        this.toggleMenu(this.menu);
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
                this.toggleMenu(this.menu, false);
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
            this.toggleMenu(this.menu, false);
            this.toggle.focus();
        }
    }

    /**
     * Handle submenu toggle button click event.
     *
     * @param {Event} event - Button click event.
     */
    handleSubmenuToggleClick(event) {
        event.preventDefault();
        const submenu = event.target.nextElementSibling;
        this.toggleMenu(submenu);
    }

    /**
     * Handle submenu key up event.
     *
     * @param {KeyboardEvent} event - Key up event.
     */
    handleSubmenuKeyUp(event) {
        if (event.key === 'Escape') {
            const submenuItem = event.currentTarget;
            const submenuToggle = submenuItem.querySelector(':scope > a.is-toggle');
            const submenu = submenuToggle.nextElementSibling;
            this.toggleMenu(submenu, false);
            submenuToggle.focus();
        }
    }

    /**
     * Handle submenu focus out event.
     *
     * @param {FocusEvent} event - Focus out event.
     */
    handleSubmenuFocusOut(event) {
        const submenuItem = event.currentTarget;
        const submenuToggle = submenuItem.querySelector(':scope > a.is-toggle');
        const submenu = submenuToggle.nextElementSibling;
        setTimeout(() => {
            if (!submenuItem.contains(document.activeElement)) {
                this.toggleMenu(submenu, false);
            }
        }, 0);
    }

    // handleResize() {
    //     console.log('resize');
    // }
}

export default MainNavigation;
