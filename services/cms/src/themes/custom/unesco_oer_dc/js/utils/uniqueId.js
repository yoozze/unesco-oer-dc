/** @module utils/uniqueId */

/** Counter used to generate unique IDs. */
let idCounter = 0;

/**
 * Generate a unique ID. If `prefix` is given, the ID is appended to it.
 *
 * @param {string|*} [prefix='js-id-'] - The value to prefix the ID with.
 * @returns {string} Unique ID.
 */
export default function uniqueId(prefix = 'js-id-') {
    idCounter += 1;

    return `${prefix}${idCounter}`;
}
