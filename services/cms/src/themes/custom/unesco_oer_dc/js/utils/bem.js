/** @module utils/bem */


/**
 * Generate BEM class(es) from given arguments.
 *
 * @param {string|*} block - Block name.
 * @param {string|Array|*} [element=''] - Element name or modifier name(s). If non-empty array is
 * provided `modifier` is ignored and this is used as list of modifier names.
 * @param {string|Array|*} [modifier=''] - Modifier name(s).
 * @returns {string} BEM class(es).
 */
export default function bem(block, element = '', modifier = '') {
    let e = element;
    let m = modifier;
    let isModifierArray;

    // If `element` is array, use it as modifiers and skip `element` altogether.
    if (Array.isArray(element)) {
        m = e;
        e = undefined;
        isModifierArray = true;
    } else {
        isModifierArray = Array.isArray(m);
    }

    const be = e ? `${block}__${e}` : `${block}`;

    // If single modifier is provided, append it to existing block-element class.
    if (!isModifierArray) {
        return m ? `${be}--${m}` : be;
    }

    // If list of modifiers is provided, start with block-element class
    // and generate new class for every modifier.
    const modifierLength = m.length;
    let result = be;

    for (let i = 0; i < modifierLength; i++) {
        const mi = m[i];

        if (mi) {
            result += ` ${be}--${mi}`;
        }
    }

    return result;
}
