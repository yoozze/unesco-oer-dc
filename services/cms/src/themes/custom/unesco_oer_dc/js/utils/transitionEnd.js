/** @module utils/transitionEnd */


/**
 * Get `transitionend` event name if transitions are supported.
 *
 * @returns {string|boolean} `transitionend` end event name, or `false` if CSS transitions are not
 * supported.
 */
function transitionEnd() {
    const transitionEndMap = {
        transition: 'transitionend',
        WebkitTransition: 'webkitTransitionEnd',
        MozTransition: 'transitionend',
        msTransition: 'MSTransitionEnd',
        OTransition: 'oTransitionEnd otransitionend',
    };
    const transitionEndMapKeys = Object.keys(transitionEndMap);
    const el = document.createElement('fake');

    for (let i = 0; i < transitionEndMapKeys.length; i++) {
        const key = transitionEndMapKeys[i];

        if (el.style[key] !== undefined) {
            return transitionEndMap[key];
        }
    }

    return false;
}

export default transitionEnd();
