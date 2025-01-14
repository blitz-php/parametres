<?php

namespace BlitzPHP\Parametres\Handlers;

use RuntimeException;

abstract class BaseHandler
{
    /**
     * Vérifie si ce gestionnaire a une valeur définie.
     */
    abstract public function has(string $file, string $property, ?string $context = null): bool;

    /**
     * Renvoie une seule valeur du gestionnaire, si elle est stockée.
     */
    abstract public function get(string $file, string $property, ?string $context = null): mixed;

    /**
     * Si le gestionnaire prend en charge l'enregistrement des valeurs, il DOIT surcharger cette méthode pour fournir cette fonctionnalité.
     * Tous les gestionnaires ne prennent pas en charge l'écriture des valeurs.
     * Doit lancer une RuntimeException en cas d'échec.
     *
     * @throws RuntimeException
     */
    public function set(string $file, string $property, mixed $value = null, ?string $context = null): void
    {
        throw new RuntimeException('La méthode "set" n\'est pas implémentée pour le gestionnaire de paramètres actuel.');
    }

    /**
     * Si le gestionnaire prend en charge l'oubli de valeurs, il DOIT surcharger cette méthode pour fournir cette fonctionnalité.
     * Tous les gestionnaires ne prennent pas en charge l'écriture de valeurs.
     * Doit lancer une RuntimeException en cas d'échec.
     *
     * @throws RuntimeException
     */
    public function forget(string $file, string $property, ?string $context = null): void
    {
        throw new RuntimeException('La méthode "forget" n\'est pas implémentée pour le gestionnaire de paramètres actuel.');
    }

    /**
     * Tous les gestionnaires DOIVENT prendre en charge l'effacement de toutes les valeurs.
     *
     * @throws RuntimeException
     */
    public function flush(): void
    {
        throw new RuntimeException('La méthode "flush" n\'est pas implémentée pour le gestionnaire de paramètres actuel.');
    }

    /**
     * Prend en charge la conversion de certains types d'objets afin qu'ils puissent
	 * être stockés en toute sécurité et réhydratés dans les fichiers de configuration.
     *
     * @return mixed|string
     */
    protected function prepareValue(mixed $value)
    {
        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }

        return $value;
    }

    /**
     * Gère certaines conversions spéciales que les données peuvent avoir été enregistrées,
	 * telles que les booléens et les données sérialisées.
     *
     * @return bool|mixed
     */
    protected function parseValue(mixed $value, string $type)
    {
        // Sérialisé?
        if ($this->isSerialized($value)) {
            $value = unserialize($value);
        }

        settype($value, $type);

        return $value;
    }

    /**
     * Vérifie si un objet est sérialisé et correctement formaté.
     *
     * Tiré des fonctions de base de Wordpress.
     *
     * @param bool $strict S'il faut être strict sur la fin de la chaîne.
     */
    protected function isSerialized(mixed $data, bool $strict = true): bool
    {
        // Si ce n'est pas une chaîne, elle n'est pas sérialisée.
        if (! is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' === $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        if ($strict) {
            $lastc = substr($data, -1);
            if (';' !== $lastc && '}' !== $lastc) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace     = strpos($data, '}');
            // L'un ou l'autre ; ou } doit exister.
            if (false === $semicolon && false === $brace) {
                return false;
            }
            // Mais aucun des deux ne doit se trouver dans les X premiers caractères.
            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }
            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];

        switch ($token) {
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (false === strpos($data, '"')) {
                    return false;
                }

                // Ou bien tomber dans le vide.
                // Pas de pause
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);

            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';

                return (bool) preg_match("/^{$token}:[0-9.E+-]+;{$end}/", $data);
        }

        return false;
    }
}
