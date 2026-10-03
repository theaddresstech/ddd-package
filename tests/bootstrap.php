<?php

// PHPUnit's isolated-process runner needs the autoloader before it restores
// configuration. Pest's entrypoint does not set this PHPUnit constant.
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__).'/vendor/autoload.php');
}

require_once PHPUNIT_COMPOSER_INSTALL;
