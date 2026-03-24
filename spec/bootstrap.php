<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Initializer\Boot;

defined('HOME_PATH')   || define('HOME_PATH', realpath(rtrim(getcwd(), '\\/ ')) . DIRECTORY_SEPARATOR);
defined('VENDOR_PATH') || define('VENDOR_PATH', realpath(HOME_PATH . 'vendor') . DIRECTORY_SEPARATOR);
define('BLITZ_DEBUG', true);

if (! is_file($autoload_file = realpath(VENDOR_PATH . 'autoload.php')) ?: '') {
    echo 'Votre fichier autoload de Composer ne semble pas être défini correctement. ';
    echo 'Veuillez ouvrir le fichier suivant et pour corriger: "' . __FILE__ . '"';

    exit(3); // EXIT_CONFIG
}

define('APP_NAMESPACE', 'App');
define('APP_PATH', __DIR__ . '/_support/');
define('WEBROOT', APP_PATH);
define('ROOTPATH', HOME_PATH);
define('STORAGE_PATH', APP_PATH);
define('SYST_PATH', VENDOR_PATH . 'blitz-php/framework/src/');

require_once $autoload_file;
require_once SYST_PATH . 'Initializer' . DIRECTORY_SEPARATOR. 'Boot.php';

require_once SYST_PATH . 'Helpers/path.php';

$paths = ['app' => APP_PATH . 'app', 'storage' => APP_PATH . 'storage', 'composer' => VENDOR_PATH];
Boot::test($paths, __FILE__);

config()->load('parametres', __DIR__ . '/../src/Config/parametres.php');
config()->set('parametres.handlers', ['array']);

// Fakes configurations
config()->ghost('test')->set('test', [
    'site_name' => 'Parametres Test',
]);
