<?php

namespace BlitzPHP\Parametres\Config;

use BlitzPHP\Container\Services as BaseService;
use BlitzPHP\Parametres\Parametres;

class Services extends BaseService
{
    /**
     * Renvoie la classe du gestionnaire de paramètres.
     */
    public static function parametres(?array $config = null, bool $shared = true): Parametres
    {
        if (true === $shared && isset(static::$instances[Parametres::class])) {
            return static::$instances[Parametres::class];
        }

        return new Parametres($config ?? config('parametres'));
    }
}
