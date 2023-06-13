/** @module utils/convertToSlug */

/**
 * Converts a string to a slug.
 *
 * @param {string} text Text to convert.
 * @returns {string} Slug.
 */
export default function convertToSlug(text) {
    return text
        .toLowerCase()
        .replace(/ /g, '-')
        .replace(/[^\w-]+/g, '');
}
