const mix = require('laravel-mix');

mix.disableSuccessNotifications()
    .webpackConfig({
        devtool: 'source-map',
        externals: {
            jquery: 'jQuery',
            drupal: 'Drupal',
        },
    })
    .autoload({
        jquery: ['$', 'jQuery', 'window.jQuery'],
        drupal: ['Drupal', 'window.Drupal'],
    })
    .js('js/main.js', 'dist')
    .sass('css/style.scss', 'dist')
    .sourceMaps()
    .browserSync({
        proxy: 'localhost:8080',
        port: 8081,
        notify: true,
        open: false,
        files: [
            'build/main.css',
            'css/**/*',
            'js/**/*',
            'images/**/*',
            'templates/**/*',
            'unesco_oer_dc.theme',
        ],
    });
