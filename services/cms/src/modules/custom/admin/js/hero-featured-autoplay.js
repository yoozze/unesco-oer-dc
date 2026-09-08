/**
 * @file
 * Show hero slide Autoplay when featured media is selected.
 *
 * Conditional Fields cannot evaluate media_library !empty reliably, so we
 * toggle visibility in the admin UI when .js-media-library-item is present.
 */
((Drupal, once) => {
    /**
     * @param {HTMLElement} slide
     */
    function syncFeaturedAutoplay(slide) {
        const media = slide.querySelector('.js-hero-featured-media');
        const autoplay = slide.querySelector('.js-hero-featured-autoplay');
        if (!media || !autoplay) {
            return;
        }

        const hasMedia = Boolean(media.querySelector('.js-media-library-item'));
        autoplay.hidden = !hasMedia;
        autoplay.classList.toggle('js-hero-featured-autoplay--visible', hasMedia);
    }

    Drupal.behaviors.adminHeroFeaturedAutoplay = {
        attach(context) {
            once('admin-hero-featured-autoplay', '.js-hero-slide-subform', context).forEach(
                slide => {
                    syncFeaturedAutoplay(slide);
                    // Observe the whole slide: Media Library AJAX may replace the widget root.
                    const observer = new MutationObserver(() => syncFeaturedAutoplay(slide));
                    observer.observe(slide, { childList: true, subtree: true });
                },
            );

            // Re-sync slides touched by AJAX replacements in this context.
            if (context.querySelectorAll) {
                context.querySelectorAll('.js-hero-slide-subform').forEach(syncFeaturedAutoplay);
            }
        },
    };
})(Drupal, once);
