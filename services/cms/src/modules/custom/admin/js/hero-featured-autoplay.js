/**
 * @file
 * Show hero slide fields when related media library widgets have a selection.
 *
 * Conditional Fields cannot evaluate media_library !empty reliably.
 */
((Drupal, once) => {
    /**
     * @param {HTMLElement} slide
     * @param {string} mediaSelector
     * @param {string} dependentSelector
     * @param {string} visibleClass
     */
    function syncMediaDependent(slide, mediaSelector, dependentSelector, visibleClass) {
        const media = slide.querySelector(mediaSelector);
        const dependent = slide.querySelector(dependentSelector);
        if (!media || !dependent) {
            return;
        }

        const hasMedia = Boolean(media.querySelector('.js-media-library-item'));
        dependent.hidden = !hasMedia;
        dependent.classList.toggle(visibleClass, hasMedia);
    }

    /**
     * @param {HTMLElement} slide
     */
    function syncSlide(slide) {
        syncMediaDependent(
            slide,
            '.js-hero-featured-media',
            '.js-hero-featured-autoplay',
            'js-hero-featured-autoplay--visible',
        );
        syncMediaDependent(
            slide,
            '.js-hero-background-media',
            '.js-hero-background-opacity',
            'js-hero-background-opacity--visible',
        );
    }

    Drupal.behaviors.adminHeroFeaturedAutoplay = {
        attach(context) {
            once('admin-hero-featured-autoplay', '.js-hero-slide-subform', context).forEach(
                slide => {
                    syncSlide(slide);
                    // Observe the whole slide: Media Library AJAX may replace the widget root.
                    const observer = new MutationObserver(() => syncSlide(slide));
                    observer.observe(slide, { childList: true, subtree: true });
                },
            );

            if (context.querySelectorAll) {
                context.querySelectorAll('.js-hero-slide-subform').forEach(syncSlide);
            }
        },
    };
})(Drupal, once);
