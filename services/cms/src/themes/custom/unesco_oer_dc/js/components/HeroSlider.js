/** @module components/HeroSlider */

import 'slick-carousel';

import Component from './Component';

class HeroSlider extends Component {
    /**
     * @type {Object | null} Slick slider instance.
     */
    slick = null;

    /**
     * @type {HTMLElement | null}
     */
    slider = null;

    /**
     * Whether Slick autoplay is enabled for this instance.
     */
    autoplayEnabled = false;

    /**
     * Whether hovering the hero should pause autoplay.
     */
    pauseOnHover = true;

    /**
     * Featured / aside video currently holding autoplay.
     */
    videoHoldingAutoplay = false;

    /**
     * @type {HTMLElement | null}
     */
    progressRoot = null;

    /**
     * @type {HTMLElement | null}
     */
    progressBar = null;

    /**
     * @override
     * @returns {string} Component block name.
     */
    static get block() {
        return 'c-hero';
    }

    /**
     * @param {HTMLElement} element
     * @param {Object} options
     */
    constructor(element, options = {}) {
        super(element, options);
        this.slider = this.element.querySelector(`.${HeroSlider.bem('slider')}`);
        if (!this.slider) {
            return;
        }

        const items = this.slider.querySelectorAll(`.${HeroSlider.bem('slide')}`);
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (items.length < 2) {
            this.syncBackgroundVideos(0);
            this.syncAsideVideos(0);
            return;
        }

        const arrowNext =
            this.options.nextArrow ||
            '<button type="button" class="c-icon-button c-icon-button--standard c-hero__arrow c-hero__arrow--next"><svg class="c-icon c-icon-button__icon" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="#arrow-forward"></use></svg></button>';
        const arrowPrev =
            this.options.prevArrow ||
            '<button type="button" class="c-icon-button c-icon-button--standard c-hero__arrow c-hero__arrow--prev"><svg class="c-icon c-icon-button__icon" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="#arrow-forward"></use></svg></button>';

        const autoplaySpeed = this.options.autoplaySpeed ?? 5000;
        // Delay 0 (or autoplay: false) disables autoplay; reduced motion always wins.
        this.autoplayEnabled =
            !reduceMotion && this.options.autoplay !== false && autoplaySpeed > 0;
        this.pauseOnHover = this.options.pauseOnHover !== false;

        this.slick = $(this.slider).slick({
            slidesToShow: 1,
            slidesToScroll: 1,
            fade: true,
            infinite: true,
            dots: true,
            arrows: true,
            adaptiveHeight: false,
            nextArrow: arrowNext,
            prevArrow: arrowPrev,
            ...this.options,
            autoplay: this.autoplayEnabled,
            autoplaySpeed,
            // Own hover/focus on `.c-hero` so arrows/dots stay in sync with the bar.
            pauseOnHover: false,
            pauseOnFocus: false,
        });

        this.syncBackgroundVideos(0);
        this.syncAsideVideos(0);
        this.bindVideoPause();
        this.bindAutoplayInteraction();
        this.initAutoplayProgress(autoplaySpeed);

        $(this.slider).on('beforeChange', () => {
            this.pauseFeaturedMedia();
            this.restartProgressKeepingInteractionPause();
        });

        $(this.slider).on('afterChange', (_event, _slick, currentSlide) => {
            this.syncBackgroundVideos(currentSlide);
            this.syncAsideVideos(currentSlide);
        });
    }

    /**
     * Show and drive the bottom-edge autoplay progress bar.
     *
     * @param {number} autoplaySpeed
     */
    initAutoplayProgress(autoplaySpeed) {
        this.progressRoot = this.element.querySelector(`.${HeroSlider.bem('autoplay-progress')}`);
        this.progressBar = this.element.querySelector(
            `.${HeroSlider.bem('autoplay-progress-bar')}`,
        );

        if (!this.autoplayEnabled || !this.progressRoot || !this.progressBar) {
            return;
        }

        this.element.classList.add(`${HeroSlider.bem()}--autoplay`);
        this.progressRoot.hidden = false;
        this.element.style.setProperty('--c-hero-autoplay-ms', `${autoplaySpeed}ms`);
        this.restartProgress();
    }

    /**
     * Pause/resume autoplay from the whole hero (including arrows and dots).
     *
     * Slick's own hover handler only covers `.slick-list` and restarts its
     * interval on leave; driving both here keeps the bar aligned.
     */
    bindAutoplayInteraction() {
        if (!this.autoplayEnabled) {
            return;
        }

        if (this.pauseOnHover) {
            this.element.addEventListener('mouseenter', () => this.pauseAutoplay());
            this.element.addEventListener('mouseleave', () => this.resumeAutoplay());
        }

        this.element.addEventListener('focusin', () => this.pauseAutoplay());
        this.element.addEventListener('focusout', event => {
            if (!this.element.contains(event.relatedTarget)) {
                this.resumeAutoplay();
            }
        });
    }

    /**
     * Whether hover, focus, or featured video should keep autoplay held.
     *
     * @returns {boolean}
     */
    shouldHoldAutoplay() {
        if (this.videoHoldingAutoplay) {
            return true;
        }

        if (this.pauseOnHover && this.element.matches(':hover')) {
            return true;
        }

        return this.element.contains(document.activeElement);
    }

    /**
     * Pause Slick and freeze the progress bar.
     */
    pauseAutoplay() {
        if (!this.autoplayEnabled) {
            return;
        }

        if (this.slick) {
            $(this.slider).slick('slickPause');
        }

        this.pauseProgress();
    }

    /**
     * Resume Slick and restart the bar (Slick always resets its interval).
     */
    resumeAutoplay() {
        if (!this.autoplayEnabled) {
            return;
        }

        if (this.shouldHoldAutoplay()) {
            this.pauseAutoplay();
            return;
        }

        if (this.slick) {
            $(this.slider).slick('slickPlay');
        }

        this.restartProgress();
    }

    /**
     * Restart the progress animation from the beginning.
     */
    restartProgress() {
        if (!this.autoplayEnabled || !this.progressBar) {
            return;
        }

        this.clearProgressInlineStyles();
        this.progressBar.classList.remove('is-running', 'is-collapsing');
        // Force reflow so the CSS animation restarts.
        // eslint-disable-next-line no-unused-expressions
        void this.progressBar.offsetWidth;
        this.progressBar.classList.add('is-running');
    }

    /**
     * Restart progress, then reset to empty if hover/focus still holds autoplay.
     */
    restartProgressKeepingInteractionPause() {
        this.restartProgress();
        if (this.shouldHoldAutoplay()) {
            this.pauseAutoplay();
        }
    }

    /**
     * Animate progress back to empty while autoplay is held (hover / focus / video).
     */
    pauseProgress() {
        if (!this.autoplayEnabled || !this.progressBar) {
            return;
        }

        if (!this.progressBar.classList.contains('is-running')) {
            return;
        }

        const scaleX = this.getProgressScaleX();
        this.progressBar.style.transform = `scaleX(${scaleX})`;
        this.progressBar.classList.remove('is-running');
        this.progressBar.classList.add('is-collapsing');

        // Force reflow so the transition starts from the captured scale.
        // eslint-disable-next-line no-unused-expressions
        void this.progressBar.offsetWidth;
        this.progressBar.style.transform = 'scaleX(0)';

        const onCollapseEnd = event => {
            if (event.propertyName !== 'transform') {
                return;
            }

            this.progressBar.removeEventListener('transitionend', onCollapseEnd);
            if (this.progressBar.classList.contains('is-collapsing')) {
                this.clearProgressInlineStyles();
                this.progressBar.classList.remove('is-collapsing');
            }
        };
        this.progressBar.addEventListener('transitionend', onCollapseEnd);
    }

    /**
     * Current horizontal scale of the progress bar (0–1).
     *
     * @returns {number}
     */
    getProgressScaleX() {
        const { transform } = getComputedStyle(this.progressBar);
        if (!transform || transform === 'none') {
            return 0;
        }

        try {
            return new DOMMatrixReadOnly(transform).m11;
        } catch (e) {
            return 0;
        }
    }

    /**
     * Clear inline transform left over from the collapse animation.
     */
    clearProgressInlineStyles() {
        if (!this.progressBar) {
            return;
        }

        this.progressBar.style.transform = '';
    }

    /**
     * Play background video on the active slide only.
     *
     * @param {number} index
     */
    syncBackgroundVideos(index) {
        const slides = this.slider.querySelectorAll(`.${HeroSlider.bem('slide')}`);
        slides.forEach((slide, i) => {
            const video = slide.querySelector(`.${HeroSlider.bem('background-media')}`);
            if (!(video instanceof HTMLVideoElement)) {
                return;
            }

            if (i === index && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                video.play().catch(() => {});
            } else {
                video.pause();
                try {
                    video.currentTime = 0;
                } catch (e) {
                    // Ignore seek errors on unloaded media.
                }
            }
        });
    }

    /**
     * Play aside branding video once each time its slide becomes active.
     *
     * @param {number} index
     */
    syncAsideVideos(index) {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const slides = this.slider.querySelectorAll(`.${HeroSlider.bem('slide')}`);
        slides.forEach((slide, i) => {
            const video = slide.querySelector(`.${HeroSlider.bem('aside-media-video')}`);
            if (!(video instanceof HTMLVideoElement)) {
                return;
            }

            video.pause();
            try {
                video.currentTime = 0;
            } catch (e) {
                // Ignore seek errors on unloaded media.
            }

            if (i === index && !reduceMotion) {
                video.play().catch(() => {});
            }
        });
    }

    /**
     * Pause carousel autoplay while featured (user-controlled) videos play.
     * Aside branding clips are excluded so they do not hold the slider.
     */
    bindVideoPause() {
        if (!this.autoplayEnabled) {
            return;
        }

        const media = this.slider.querySelectorAll(`.${HeroSlider.bem('featured-media')} video`);
        media.forEach(el => {
            el.addEventListener('play', () => {
                this.videoHoldingAutoplay = true;
                this.pauseAutoplay();
            });
            el.addEventListener('pause', () => {
                this.videoHoldingAutoplay = false;
                this.resumeAutoplay();
            });
            el.addEventListener('ended', () => {
                this.videoHoldingAutoplay = false;
                this.resumeAutoplay();
            });
        });
    }

    /**
     * Pause featured HTML5 videos when changing slides.
     * Aside branding is reset via syncAsideVideos on afterChange.
     */
    pauseFeaturedMedia() {
        const media = this.slider.querySelectorAll(`.${HeroSlider.bem('featured-media')} video`);
        media.forEach(el => {
            if (el instanceof HTMLVideoElement && !el.paused) {
                el.pause();
            }
        });
    }
}

export default HeroSlider;
