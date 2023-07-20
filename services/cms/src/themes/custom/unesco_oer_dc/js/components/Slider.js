/** @module components/Slider */

import 'slick-carousel';
import PhotoSwipeLightbox from 'photoswipe/lightbox';

import Component from './Component';

class Slider extends Component {
    /**
     * @type {Object | null} Slick slider inastance.
     */
    slick = null;

    /**
     * Get component block name.
     *
     * @override
     * @returns {string} Component block name.
     */
    static get block() {
        return 'c-slider';
    }

    /**
     * Slider constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} options - Slider options.
     */
    constructor(element, options = {}) {
        super(element, options);
        const items = this.element.querySelectorAll(`.${Slider.bem('item')}`);
        if (items.length > 1) {
            this.slick = $(this.element).slick({
                slidesToShow: 1,
                slidesToScroll: 1,
                ...this.options,
            });
        }

        // if (this.options.gallery) {
        //     const lightbox = new PhotoSwipeLightbox({
        //         gallery: this.options.gallery,
        //         // gallery: this.element,
        //         children: '.c-slider__item a',
        //         pswpModule: () => import('photoswipe'),
        //     });
        //     lightbox.init();
        // }
    }
}

export default Slider;
