<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Parametres\Handlers;

/**
 * Gestionnaire de paramètres via des tableaux
 *
 * Utilise le stockage local pour gérer les requêtes de paramètres non persistantes.
 * Utile principalement pour les tests ou l'extension par de vrais gestionnaires persistants.
 */
class ArrayHandler extends BaseHandler
{
    /**
     * Stockage pour les paramètres généraux.
     * Format: ['file' => ['property' => ['value', 'type']]]
     *
     * @var array<string,array<string,array<mixed>>>
     */
    private array $general = [];

    /**
     * Stockage des paramètres contextuels.
     * Format: ['context' => ['file' => ['property' => ['value', 'type']]]]
     *
     * @var array<string,array<mixed>|null>
     */
    private array $contexts = [];

    /**
     * {@inheritDoc}
     */
    public function has(string $file, string $property, ?string $context = null): bool
    {
        return $this->hasStored($file, $property, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $file, string $property, ?string $context = null): mixed
    {
        return $this->getStored($file, $property, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $file, string $property, mixed $value = null, ?string $context = null): void
    {
        $this->setStored($file, $property, $value, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $file, string $property, ?string $context = null): void
    {
        $this->forgetStored($file, $property, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): void
    {
        $this->general  = [];
        $this->contexts = [];
    }

    /**
     * Vérifie si cette valeur est stockée.
     */
    protected function hasStored(string $file, string $property, ?string $context = null): bool
    {
        if ($context === null) {
            return isset($this->general[$file]) && array_key_exists($property, $this->general[$file]);
        }

        return isset($this->contexts[$context][$file]) && array_key_exists($property, $this->contexts[$context][$file]);
    }

    /**
     * Récupère une valeur de la mémoire.
     *
     * @return mixed|null
     */
    protected function getStored(string $file, string $property, ?string $context = null): mixed
    {
        if (! $this->has($file, $property, $context)) {
            return null;
        }

        return $context === null
            ? $this->parseValue(...$this->general[$file][$property])
            : $this->parseValue(...$this->contexts[$context][$file][$property]);
    }

    /**
     * Ajoute des valeurs à la mémoire.
     */
    protected function setStored(string $file, string $property, mixed $value, ?string $context = null): void
    {
        $type  = gettype($value);
        $value = $this->prepareValue($value);

        if ($context === null) {
            $this->general[$file][$property] = [
                $value,
                $type,
            ];
        } else {
            $this->contexts[$context][$file][$property] = [
                $value,
                $type,
            ];
        }
    }

    /**
     * Supprime un élément de la mémoire.
     */
    protected function forgetStored(string $file, string $property, ?string $context): void
    {
        if ($context === null) {
            unset($this->general[$file][$property]);
        } else {
            unset($this->contexts[$context][$file][$property]);
        }
    }
}
