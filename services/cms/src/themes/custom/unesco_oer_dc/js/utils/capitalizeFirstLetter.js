/** @module utils/capitalizeFirstLetter */

/**
 * Capitalize first letter of a given string.
 *
 * @param {string} string - String to be capitalized.
 * @returns {string} String with first letter capitalized.
 */
export default function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
