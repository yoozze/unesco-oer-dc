/** @module utils/animationEnd */


/**
 * Get `animationend` event name if animations are supported.
 *
 * @returns {string|boolean} `animationend` event name, or `false` if CSS animations are not
 * supported.
 */
function animationEnd() {
    const animationEndMap = {
        animation: 'animationend',
        WebkitAnimation: 'webkitAnimationEnd',
        MozAnimation: 'animationend',
        msAnimation: 'MSAnimationEnd',
        OAnimation: 'oAnimationEnd',
    };
    const animationEndMapKeys = Object.keys(animationEndMap);
    const el = document.createElement('fake');

    for (let i = 0; i < animationEndMapKeys.length; i++) {
        const key = animationEndMapKeys[i];

        if (el.style[key] !== undefined) {
            return animationEndMap[key];
        }
    }

    return false;
}

export default animationEnd();
