<?php

/**
 * This script is globally prepended to all PHP scripts.
 * It is used to fix some issues with the way the server is configured.
 * 
 * Enable/disable it by setting the `auto_prepend_file` directive in the
 * `php.ini` file.
 */

// Fix `$_SERVER['HTTP_HOST']`
if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

// Fix `$_SERVER['SERVER_NAME']`
if (isset($_SERVER['HTTP_X_FORWARDED_SERVER'])) {
    $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_X_FORWARDED_SERVER'];
}
