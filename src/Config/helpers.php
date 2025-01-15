<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Parametres\Parametres;

if (! function_exists('parametre')) {
    /**
     * Fournit une interface pratique au service Paramètres.
     *
     * @phpstan-return ($key is null ? Parametres : ($value is null ? array<mixed>|bool|float|int|object|string|null : void))
     *
     * @param mixed|null $value
     *
     * @return array<mixed>|bool|float|int|object|Parametres|string|void|null
     */
    function parametre(?string $key = null, $value = null)
    {
        /** @var Parametres $parametre */
        $parametre = service('parametres');

        if (empty($key)) {
            return $parametre;
        }

        // Obtenir la valeur?
        if (count(func_get_args()) === 1) {
            return $parametre->get($key);
        }

        // Definition de la valeur
        $parametre->set($key, $value);
    }
}
