<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Parametres\Handlers\ArrayHandler;
use BlitzPHP\Parametres\Handlers\DatabaseHandler;

return [
    /**
     * Les gestionnaires disponibles.
     * L'alias doit correspondre à une clé disponible plus bas; avec le tableau des paramètres contenant 'class'.
     *
     * @var list<string>
     */
    'handlers' => ['array'],

    /**
     * Paramètres du gestionnaire "Array".
     */
    'array' => [
        'class'     => ArrayHandler::class,
        'writeable' => true,
    ],

    /**
     * Paramètres du gestionnaire "Database".
     */
    'database' => [
        'class'     => DatabaseHandler::class,
        'table'     => 'parametres',
        'group'     => null,
        'writeable' => true,
    ],
];
