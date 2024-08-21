/** @module utils/getPosition */

/**
 *
 * @param {Element} element
 * @returns {
 *    left: number,
 *    top: number
 * }
 */
export default function getPosition(element) {
    var clientRect = element.getBoundingClientRect();
    return {
        left: clientRect.left + document.body.scrollLeft,
        top: clientRect.top + document.body.scrollTop,
    };
}
