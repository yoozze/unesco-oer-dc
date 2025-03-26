/** @module components/Article */

import convertToSlug from '../utils/convertToSlug';
import Component from './Component';

const HEADINGS_SELECTOR = 'h2, h3';

// TODO: get header height from component
const headerHeight = 105;

class Article extends Component {
    /**
     * @type {HTMLElement | null} Article title element.
     */
    title = null;

    /**
     * @type {HTMLElement | null} Article body element.
     */
    body = null;

    /**
     * @type {HTMLElement | null} Article body content element.
     */
    bodyContent = null;

    /**
     * @type {HTMLElement | null} Article main element.
     */
    main = null;

    /**
     * @type {HTMLElement | null} Article sidebar element.
     */
    sidebar = null;

    /**
     * @type {HTMLElement | null} Article navigation element.
     */
    nav = null;

    /**
     * @type {NodeListOf<Element>} List of article headings.
     */
    headings = [];

    /**
     * Get component block name.
     *
     * @override
     * @returns {string} Component block name.
     */
    static get block() {
        return 'c-article';
    }

    /**
     * Article constructor.
     *
     * @param {HtmlElement} element - DOM element to be initialized.
     * @param {Object} options - Article options.
     */
    constructor(element, options = {}) {
        super(element, options);

        this.title = this.element.querySelector(`.${Article.bem('title')}`);
        this.body = this.element.querySelector(`.${Article.bem('body')}`);
        this.bodyContent = this.body?.querySelector(`.${Article.bem('content')}`) || null;
        this.main = this.bodyContent?.querySelector(`.${Article.bem('main')}`) || null;
        this.sidebar = this.body?.querySelector(`.${Article.bem('sidebar')}`) || null;
        this.headings = this.main?.querySelectorAll('h2, h3') || [];

        if (this.sidebar !== null && this.headings.length > 0 && this.options.articleNavTitle) {
            this.nav = this.createArticleNav();
            this.observeContentHeadings();
        }

        if (this.nav !== null) {
            this.nav.addEventListener('click', this.hadleNavLinkClick.bind(this));
        }
    }

    /**
     * Create article navigation.
     */
    createArticleNav() {
        const articleNav = document.createElement('nav');
        articleNav.classList.add('c-article__nav');
        // articleNav.setAttribute('aria-label', 'Article navigation');

        const articleNavTitle = document.createElement('p');
        articleNavTitle.classList.add('c-article__nav-title');
        articleNavTitle.textContent = this.options.articleNavTitle;
        articleNav.appendChild(articleNavTitle);

        const articleNavList = document.createElement('ul');
        articleNavList.classList.add('c-article__nav-list');
        articleNav.appendChild(articleNavList);

        /** @type {Set<string>} */
        const headingSlugs = new Set();

        const headings = [this.title, ...this.headings];
        headings.forEach(heading => {
            let id = convertToSlug(heading.textContent);
            let i = 1;
            while (headingSlugs.has(id)) {
                id = `${id}-${i}`;
                i++;
            }

            headingSlugs.add(id);
            heading.setAttribute('id', id);

            const articleNavListItem = document.createElement('li');
            articleNavListItem.classList.add('c-article__nav-item');

            const articleNavListItemLink = document.createElement('a');
            const level = heading.tagName.toLowerCase();
            articleNavListItemLink.classList.add(
                'c-article__nav-link',
                `c-article__nav-link--${level}`,
            );
            articleNavListItemLink.setAttribute('href', `#${id}`);
            articleNavListItemLink.textContent = heading.textContent;

            articleNavListItem.appendChild(articleNavListItemLink);
            articleNavList.appendChild(articleNavListItem);
        });

        this.sidebar.appendChild(articleNav);
        return articleNav;
    }

    /**
     * Observe content headings.
     *
     * TODO: Improve this method.
     */
    observeContentHeadings() {
        if (!window.IntersectionObserver) {
            return;
        }

        const links = this.nav.querySelectorAll('a');
        if (links.length < 1) {
            return;
        }

        const navItems = {};
        links.forEach(link => {
            navItems[link.getAttribute('href').slice(1)] = link;
        });

        const contentOffset = headerHeight + 32;
        const headings = [this.title, ...this.headings];
        const observer = new IntersectionObserver(
            (entries, observer) => {
                let index = 0;
                let prevHeading = null;
                for (let i = 0; i < headings.length; i++) {
                    const heading = headings[i];
                    navItems[heading.id].classList.remove('is-active');
                    const navItem = navItems[prevHeading ? prevHeading.id : heading.id];
                    const offsetY = contentOffset + window.pageYOffset;
                    if (prevHeading === null) {
                        if (offsetY < heading.offsetTop) {
                            navItems[heading.id].classList.add('is-active');
                        }
                    } else {
                        if (offsetY >= prevHeading.offsetTop && offsetY < heading.offsetTop) {
                            navItems[prevHeading.id].classList.add('is-active');
                        } else if (i === headings.length - 1 && offsetY > heading.offsetTop) {
                            navItems[heading.id].classList.add('is-active');
                        }
                    }

                    prevHeading = heading;
                }
            },
            {
                root: null,
                rootMargin: -contentOffset + 'px 0px 0px 0px',
                threshold: [0, 1],
            },
        );

        headings.forEach(heading => {
            observer.observe(heading);
        });
    }

    /**
     * Handle nav link click event.
     * @param {PointerEvent} event
     * @returns
     */
    hadleNavLinkClick(event) {
        if (event.target.tagName !== 'A') {
            return;
        }

        event.preventDefault();
        window.history.pushState({}, '', event.target.href);
        const hash = event.target.getAttribute('href');
        const linkTarget = document.getElementById(hash.slice(1));
        // scroll({
        //     top: linkTarget.offsetTop - headerHeight - 24,
        //     // behavior: "smooth"
        // });
        $('html, body').animate(
            {
                scrollTop: $(linkTarget).offset().top - headerHeight - 24,
            },
            500,
        );
    }
}

export default Article;
