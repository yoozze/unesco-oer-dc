/** @module utils/camelCase */


/**
 * Convert given `string` to camel case.
 *
 * @param {string|*} string - The string to convert.
 * @returns {string} The camel cased string.
 */
export default function camelCase(string) {
    let camelCasedString = '';

    String(string).split(/[\W_]+/).forEach((word) => {
        if (word) {
            let w = word.toLowerCase();

            if (camelCasedString) {
                w = w.charAt(0).toUpperCase() + w.slice(1);
            }

            camelCasedString += w;
        }
    });

    return camelCasedString;
}
