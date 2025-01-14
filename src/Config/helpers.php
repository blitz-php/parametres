<?php

use BlitzPHP\Parametres\Parametres;

if (! function_exists('parametre')) {
    /**
     * Fournit une interface pratique au service Paramètres.
     *
     * @return array|bool|float|int|object|Parametres|string|void|null
     * @phpstan-return ($key is null ? Parametres : ($value is null ? array|bool|float|int|object|string|null : void))
     */
    function parametre(?string $key = null, $value = null): mixed
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
