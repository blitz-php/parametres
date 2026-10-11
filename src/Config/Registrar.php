<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Parametres\Config;

class Registrar
{
    /**
     * Enregistre les fichiers de configurations publiable
     */
    public static function config(): array
    {
        return ['parametres'];
    }
}
