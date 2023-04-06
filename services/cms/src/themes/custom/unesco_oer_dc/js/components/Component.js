/** @module components/Component */

import bem from '../utils/bem';
import uniqueId from '../utils/uniqueId';

/**
 * Base class for all components.
 *
 * @global _components - Global map of all initialized components.
 */
class Component {
    /**
     * Get component block name.
     * This getter should be overridden by every "block" component that extends this class!
     *
     * @returns {string} Component block name.
     */
    static get block() {
        return 'component';
    }

    /**
     * Get component element name.
     * This getter should be overridden by every "element" component that extends this class!
     *
     * @returns {string} Component element name.
     */
    static get element() {
        return '';
    }

    /**
     * Get component modifier name.
     * This getter should be overridden by every "modifier" component that extends this class!
     *
     * @returns {string} Component modifier name.
     */
    static get modifier() {
        return '';
    }

    /**
     * Generate BEM class(es) from given arguments.
     * Block name is implicitly set by `block` getter.
     *
     * @param {string|string[]} [element=this.element] - Element name or modifier name(s). If non-empty
     * array is provided `modifier` is ignored and this is used as list of modifier names.
     * @param {string|string[]} [modifier=this.modifier] - Modifier name(s).
     * @returns {string} BEM class(es).
     */
    static bem(element = this.element, modifier = this.modifier) {
        return bem(this.block, element, modifier);
    }

    /**
     * Select elements corresponding to this component in given context.
     * This method should be overridden by components with specific selectors, e.g. components with
     * `id` attributes for optimization.
     *
     * @param {HTMLElement} [context=document] - A DOM element to be used as context.
     * @returns {HTMLElement[]} List of selected DOM elements.
     */
    static query(context = document) {
        return context.querySelectorAll(`.${this.bem()}`);
    }

    /**
     * Initialize selected elements.
     *
     * @param {HTMLElement[]|null} [elements=null] - Dom selector/DOM node/jQuery object to be initialized.
     * @param {Object} [options={}] - Component options.
     * @returns {Object[]} Array of component instances.
     */
    static init(elements = null, options = {}) {
        const allElements = elements || this.query();
        if (!window._components) {
            window._components = new Map();
        }

        const components = [];
        for (const element of allElements) {
            let component;
            if (element.dataset.id) {
                component = window._components.get(element.dataset.id);
            }

            if (!component) {
                component = new this(element, options);
                window._components.set(element.dataset.id, component);
            }

            components.push(component);
        }

        return components;
    }

    /**
     * Component constructor.
     *
     * @param {HTMLElement} element - DOM element to be initialized.
     * @param {Object} [options={}] - Component options.
     */
    constructor(element, options = {}) {
        this.element = element;
        this.options = {
            ...options,
            ...JSON.parse(this.element.dataset.options || '{}'),
        };
        this.element.dataset.id = uniqueId(`${this.constructor.bem()}#`);
    }
}

export default Component;
