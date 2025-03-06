/** @module components/Dropdown */

import { computePosition, flip, shift, offset, autoUpdate } from '@floating-ui/dom';

import Component from './Component';

class Dropdown extends Component {
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
        return 'c-dropdown';
    }

    /**
     * Dropdown constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} options - Dropdown options.
     */
    constructor(element, options = {}) {
        super(element, options);

        this.toggle = this.element.querySelector(`.${Dropdown.bem('toggle')}`);
        this.menu = this.element.querySelector(`.${Dropdown.bem('menu')}`);

        this.element.addEventListener('keyup', this.handleKeyUp.bind(this));
        this.element.addEventListener('focusout', this.handleFocusOut.bind(this));
        this.toggle.addEventListener('click', this.handleToggleClick.bind(this));
    }

    /**
     * Update menu position.
     */
    updatePosition() {
        computePosition(this.toggle, this.menu, {
            placement: 'bottom-start',
            middleware: [offset(4), flip(), shift({ padding: 8 })],
            ...(this.options.popper || {}),
        }).then(({ x, y }) => {
            Object.assign(this.menu.style, {
                left: `${x}px`,
                top: `${y}px`,
            });
        });
    }

    /**
     * Toggle menu visibility.
     *
     * @param {boolean} state - Menu visibility state.
     */
    toggleMenu(state) {
        const isVisible = state !== undefined ? !state : this.menu.classList.contains('is-visible');
        if (!isVisible) {
            this.menu.classList.add('is-visible');
            if (this.options.popper.global) {
                document.body.appendChild(this.menu);
            }

            this.cleanup = autoUpdate(this.toggle, this.menu, this.updatePosition.bind(this));
            this.toggle.setAttribute('aria-expanded', 'true');
        } else {
            this.menu.classList.remove('is-visible');
            if (this.options.popper.global) {
                this.element.appendChild(this.menu);
            }

            this.toggle.setAttribute('aria-expanded', 'false');

            if (this.cleanup) {
                this.cleanup();
            }
        }
    }

    /**
     * Handle dropdown focus out event.
     *
     * @param {FocusEvent} event
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
     * Handle toggle click event.
     *
     * @param {PointerEvent} event - Click event.
     */
    handleToggleClick(event) {
        event.preventDefault();
        this.toggleMenu();
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

export default Dropdown;
