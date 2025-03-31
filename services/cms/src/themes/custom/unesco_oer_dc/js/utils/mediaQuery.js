/** @module utils/mediaQuery */

/**
 * Breakpoint names.
 */
export const breakpoint = {
    xxs: 'xxs',
    xs: 'xs',
    sm: 'sm',
    md: 'md',
    lg: 'lg',
    xl: 'xl',
    xxl: 'xxl',
};

/**
 * Breakpoint sizes.
 */
export const breakpointSize = {
    [breakpoint.xxs]: 320,
    [breakpoint.xs]: 480,
    [breakpoint.sm]: 576,
    [breakpoint.md]: 768,
    [breakpoint.lg]: 992,
    [breakpoint.xl]: 1200,
    [breakpoint.xxl]: 1440,
};

/**
 * Media query cache.
 */
const mediaQueryCache = new Map();

class MediaQuery {
    breakpoint = null;

    constructor(breakpoint) {
        this.breakpoint = breakpoint;
    }

    /**
     * Get min-width media query list for the specified breakpoint.
     *
     * @returns {MediaQueryList}
     */
    getAbove() {
        const breakpointAbove = `${this.breakpoint}-above`;
        let mediaQuery = mediaQueryCache.get(breakpointAbove);
        if (mediaQuery === undefined) {
            mediaQuery = window.matchMedia(`(min-width: ${breakpointSize[this.breakpoint]}px)`);
            mediaQueryCache.set(breakpointAbove, mediaQuery);
        }

        return mediaQuery;
    }

    /**
     * Get max-width media query list for the specified breakpoint (-1).
     *
     * @returns {MediaQueryList}
     */
    getBelow() {
        const breakpointBelow = `${this.breakpoint}-below`;
        let mediaQuery = mediaQueryCache.get(breakpointBelow);
        if (mediaQuery === undefined) {
            mediaQuery = window.matchMedia(`(max-width: ${breakpointSize[this.breakpoint] - 1}px)`);
            mediaQueryCache.set(breakpointBelow, mediaQuery);
        }

        return mediaQuery;
    }

    /**
     * Check if the viewport is above the specified breakpoint.
     *
     * @returns {boolean}
     */
    isAbove() {
        return this.getAbove(this.breakpoint).matches;
    }

    /**
     * Check if the viewport is below the specified breakpoint.
     *
     * @returns {boolean}
     */
    isBelow() {
        return this.getBelow(this.breakpoint).matches;
    }
}

export default MediaQuery;
