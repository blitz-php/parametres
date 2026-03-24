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
     * @var array<string,array<string,list<mixed>>>
     */
    private array $general = [];

    /**
     * Stockage des paramètres contextuels.
     * Format: ['context' => ['file' => ['property' => ['value', 'type']]]]
     *
     * @var array<string,list<mixed>|null>
     */
    private array $contexts = [];

    /**
     * Déterminer s'il faut différer les écritures jusqu'à la fin de la requête.
     * Utilisé par les gestionnaires prenant en charge les écritures différées.
     */
    protected bool $deferWrites = false;

    /**
     * Tableau des propriétés qui ont été modifiées mais qui n'ont pas été enregistrées.
     * Utilisé par les gestionnaires prenant en charge les écritures différées.
     * Format: ['key' => ['file' => ..., 'property' => ..., 'value' => ..., 'context' => ..., 'delete' => ...]]
     *
     * @var array<string, array{file: string, property: string, value: mixed, context: string|null, delete: bool}>
     */
    protected array $pendingProperties = [];

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

    /**
     * Marque une propriété comme étant en attente (doit être enregistrée).
     * Utilisé par les gestionnaires prenant en charge les écritures différées.
     */
    protected function markPending(string $file, string $property, mixed $value, ?string $context, bool $isDelete = false): void
    {
        $key                           = $file . '::' . $property . ($context === null ? '' : '::' . $context);
        $this->pendingProperties[$key] = [
            'file'     => $file,
            'property' => $property,
            'value'    => $value,
            'context'  => $context,
            'delete'   => $isDelete,
        ];
    }

    /**
     * Regroupe les propriétés en attente selon la combinaison classe+contexte.
     * Utile pour les gestionnaires qui doivent enregistrer les modifications au niveau de chaque classe.
     * Format: ['key' => ['file' => ..., 'context' => ..., 'changes' => [...]]]
     *
     * @return array<string, array{file: string, context: string|null, changes: list<array{file: string, property: string, value: mixed, context: string|null, delete: bool}>}>
     */
    protected function getPendingPropertiesGrouped(): array
    {
        $grouped = [];

        foreach ($this->pendingProperties as $info) {
            $key = $info['file'] . ($info['context'] === null ? '' : '::' . $info['context']);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'file'    => $info['file'],
                    'context' => $info['context'],
                    'changes' => [],
                ];
            }

            $grouped[$key]['changes'][] = $info;
        }

        return $grouped;
    }

    /**
     * Configure les écritures différées pour les gestionnaires qui les prennent en charge.
     *
     * @param bool $enabled Indique si les écritures différées doivent être activées
     */
    protected function setupDeferredWrites(bool $enabled): void
    {
        $this->deferWrites = $enabled;

        if ($this->deferWrites) {
            service('event')->on('post_system', $this->persistPendingProperties(...));
        }
    }
}
