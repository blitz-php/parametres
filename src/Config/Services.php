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

use BlitzPHP\Container\Services as BaseService;
use BlitzPHP\Parametres\Parametres;

class Services extends BaseService
{
    /**
     * Renvoie la classe du gestionnaire de paramètres.
	 *
	 * @param array<mixed>|null $config
     */
    public static function parametres(?array $config = null, bool $shared = true): Parametres
    {
        if (true === $shared && isset(static::$instances[Parametres::class])) {
            return static::$instances[Parametres::class];
        }

        return static::$instances[Parametres::class] = new Parametres($config ?? config('parametres'));
    }
}
