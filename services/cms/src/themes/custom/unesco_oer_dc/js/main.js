import Dropdown from './components/Dropdown';

import './misc/debug';

let timeout;

function initComponents() {
    console.log('Initializing components...');

    Dropdown.init();

    console.log('Components:', window._components);
}

function init() {
    initComponents();
    timeout = setInterval(() => {
        const prevCount = window._components ? window._components.size : 0;

        initComponents();

        if (window._components.size === prevCount) {
            clearInterval(timeout);
        }
    }, 1000);
}

window.addEventListener('load', init);
