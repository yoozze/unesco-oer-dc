/** @module utils/debounce */

/**
 * Debounce function.
 *
 * @param {Function} fn - Function to be debounced.
 * @param {number} delay - Debounce delay.
 * @returns {Function} Debounced function.
 */
export default function debounce(fn, delay) {
    let timeoutId;

    return function debounced(...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            fn(...args);
        }, delay);
    };
}
