/**
 * @file
 * Show hero slide fields when related media / featured content is set.
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
     * Autocomplete values look like "Title (123)" when a node is selected.
     *
     * @param {HTMLElement} slide
     */
    function syncFeaturedContentMedia(slide) {
        const content = slide.querySelector('.js-hero-featured-content');
        const dependents = slide.querySelectorAll('.js-hero-featured-content-media');
        if (!content || !dependents.length) {
            return;
        }

        const input = content.querySelector('input[type="text"], input.form-autocomplete');
        const value = input ? String(input.value || '').trim() : '';
        const hasContent = value !== '' && /\(\d+\)\s*$/.test(value);
        dependents.forEach(dependent => {
            dependent.hidden = !hasContent;
            dependent.classList.toggle('js-hero-featured-content-media--visible', hasContent);
        });
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
        syncMediaDependent(
            slide,
            '.js-hero-background-media',
            '.js-hero-background-fit',
            'js-hero-background-fit--visible',
        );
        syncFeaturedContentMedia(slide);
    }

    /**
     * @param {HTMLElement} slide
     */
    function bindFeaturedContentEvents(slide) {
        const content = slide.querySelector('.js-hero-featured-content');
        if (!content || content.dataset.heroFeaturedContentBound === '1') {
            return;
        }

        const input = content.querySelector('input.form-autocomplete, input[type="text"]');
        if (!input) {
            return;
        }

        content.dataset.heroFeaturedContentBound = '1';
        const sync = () => syncFeaturedContentMedia(slide);
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);

        // Drupal entity autocomplete uses jQuery UI events (not native DOM events).
        if (typeof jQuery !== 'undefined') {
            jQuery(input).on(
                'autocompleteselect.heroFeatured autocompleteclose.heroFeatured',
                () => {
                    setTimeout(sync, 0);
                },
            );
        }
    }

    Drupal.behaviors.adminHeroSlideConditionalFields = {
        attach(context) {
            once('admin-hero-slide-conditional-fields', '.js-hero-slide-subform', context).forEach(
                slide => {
                    bindFeaturedContentEvents(slide);
                    syncSlide(slide);
                    // Observe the whole slide: Media Library AJAX may replace the widget root.
                    const observer = new MutationObserver(() => {
                        bindFeaturedContentEvents(slide);
                        syncSlide(slide);
                    });
                    observer.observe(slide, { childList: true, subtree: true });
                },
            );

            if (context.querySelectorAll) {
                context.querySelectorAll('.js-hero-slide-subform').forEach(syncSlide);
            }
        },
    };
})(Drupal, once);
