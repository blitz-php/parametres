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

use BlitzPHP\Parametres\Exceptions\ParametresException;
use BlitzPHP\Utilities\Iterable\Collection;
use RuntimeException;

/**
 * Fournit une persistance basée sur JSON pour les paramètres.
 * Utilise ArrayHandler pour le stockage afin de minimiser les opérations d'écriture.
 * Supporte les écritures différées pour améliorer les performances.
 */
class JsonHandler extends ArrayHandler
{
    /**
     * Chemin d'accès du fichier de stockage des paramètres
     */
    private string $file;

    /**
     * Tableau des contextes qui ont été chargés.
     *
     * @var list<null>|list<string>
     */
    private array $hydrated = [];

    private object $config;

    /**
     * @param array<string,mixed> $config
     *
     * @throws ParametresException
     */
    public function __construct(array $config = [])
    {
        if ($config === []) {
            $config = config('parametres.json', []);
        }

        $this->config = (object) $config;

        if ('' === $this->file = ($this->config->file ?? '')) {
            throw ParametresException::fileForStorageNotDefined();
        }
        if (! is_dir(pathinfo($this->file, PATHINFO_DIRNAME))) {
            throw ParametresException::directoryOfFileNotFound($this->file);
        }

        // Créer le fichier s'il n'existe pas
        if (! file_exists($this->file)) {
            file_put_contents($this->file, '[]');
        }

        // S'assurer que le fichier est accessible en lecture/écriture
        if (! is_readable($this->file) || ! is_writable($this->file)) {
            throw new RuntimeException('Le fichier JSON n\'est pas accessible en lecture/écriture : ' . $this->file);
        }

        $this->setupDeferredWrites($this->config->defer_writes ?? false);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $file, string $property, ?string $context = null): bool
    {
        $this->hydrate($context);

        return $this->hasStored($file, $property, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $file, string $property, ?string $context = null): mixed
    {
        $this->hydrate($context);

        return $this->getStored($file, $property, $context);
    }

    /**
     * Enregistre les valeurs dans le fichier JSON pour les retrouver ultérieurement.
     *
     * @throws RuntimeException En cas d'échec d'écriture
     */
    public function set(string $file, string $property, mixed $value = null, ?string $context = null): void
    {
        $this->hydrate($context);

        // Mise à jour du stockage en mémoire d'abord
        $this->setStored($file, $property, $value, $context);

        if ($this->deferWrites) {
            $this->markPending($file, $property, $value, $context);
        } else {
            // Pour les écritures immédiates, persister uniquement ce changement de propriété spécifique
            $this->persistChanges([[
                'file'     => $file,
                'property' => $property,
                'value'    => $value,
                'context'  => $context,
                'delete'   => false,
            ]]);
        }
    }

    /**
     * Supprime l'enregistrement du stockage persistant, s'il existe, et du cache local.
     *
     * @throws RuntimeException En cas d'échec d'écriture
     */
    public function forget(string $file, string $property, ?string $context = null): void
    {
        $this->hydrate($file);

        // Suppression du stockage local
        $this->forgetStored($file, $property, $context);

        if ($this->deferWrites) {
            $this->markPending($file, $property, null, $context, true);
        } else {
            // Pour les écritures immédiates, persister uniquement cette suppression de propriété spécifique
            $this->persistChanges([[
                'file'     => $file,
                'property' => $property,
                'value'    => null,
                'context'  => $context,
                'delete'   => true,
            ]]);
        }
    }

    /**
     * Supprime tous les enregistrements du stockage persistant et vide le cache local.
     *
     * @throws RuntimeException En cas d'échec d'écriture
     */
    public function flush(): void
    {
        if ($this->deferWrites) {
            // En mode écriture différée, on vide les modifications en attente
            $this->pendingProperties = [];
        }

        // Vider complètement le fichier JSON
        $this->saveData([]);

        parent::flush();
        $this->hydrated = [];
    }

    /**
     * Persiste toutes les propriétés en attente dans le fichier JSON.
     * Appelé automatiquement à la fin de la requête via l'événement post_system
     * lorsque deferWrites est activé.
     */
    public function persistPendingProperties(): void
    {
        if ($this->pendingProperties === []) {
            return;
        }

        // Grouper les propriétés en attente par fichier+contexte en utilisant l'helper parent
        $grouped = $this->getPendingPropertiesGrouped();

        // Persister chaque groupe fichier+contexte
        foreach ($grouped as $group) {
            try {
                $this->persistChanges($group['changes']);
            } catch (RuntimeException $e) {
                logger()->error('Échec de la persistance des propriétés en attente pour ' . $group['file'] . ' : ' . $e->getMessage());
            }
        }

        $this->pendingProperties = [];
    }

    /**
     * Récupère les valeurs du fichier JSON en masse pour minimiser les opérations d'entrée/sortie.
     * Charge toutes les propriétés pour un contexte spécifique.
     *
     * @throws RuntimeException En cas d'échec de lecture
     */
    private function hydrate(?string $context = null): void
    {
        // Vérification de l'achèvement des travaux
        if (in_array($context, $this->hydrated, true)) {
            return;
        }

        $data = $this->loadData();

        if ($context === null) {
            $this->hydrated[] = null;
            $items = $data->whereNull('context');
        } else {
            // Si le général n'a pas été hydraté, on l'hydrate donc.
            if (! in_array(null, $this->hydrated, true)) {
                $this->hydrated[] = null;
                $items = $data->whereNull('context')->merge($data->where('context', $context));
            } else {
                $items = $data->where('context', $context);
            }

            $this->hydrated[] = $context;
        }

        foreach ($items->all() as $row) {
            $this->setStored(
                $row['file'],
                $row['key'],
                $this->parseValue($row['value'], $row['type']),
                $row['context']
            );
        }
    }

    /**
     * Persiste les changements de propriétés spécifiques dans le fichier JSON.
     * Utilisé à la fois pour les écritures immédiates et différées.
     *
     * @param array<array{file: string, property: string, value: mixed, context: string|null, delete: bool}> $changes
     *
     * @throws RuntimeException En cas d'échec d'écriture
     */
    private function persistChanges(array $changes): void
    {
        // Acquérir un verrou exclusif pour éviter les conflits d'écriture
        $lockHandle = fopen($this->file, 'c+b');

        if ($lockHandle === false) {
            throw new RuntimeException('Impossible d\'ouvrir le fichier JSON pour le verrouillage : ' . $this->file);
        }

        try {
            // Acquérir un verrou exclusif
            if (! flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Impossible d\'acquérir le verrou sur le fichier JSON : ' . $this->file);
            }

            // Vider le cache de statut du fichier pour obtenir la taille actuelle
            clearstatcache(true, $this->file);

            // Charger les données actuelles
            $currentData = $this->loadDataFromHandle($lockHandle);

            // Appliquer tous les changements
            foreach ($changes as $change) {
                $this->applyChange($currentData, $change);
            }

            // Sauvegarder les données modifiées
            $this->saveDataToHandle($lockHandle, $currentData);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Applique un changement unique à la collection de données.
     *
     * @param array{file: string, property: string, value: mixed, context: string|null, delete: bool} $change
     */
    private function applyChange(Collection $data, array $change): void
    {
        $time = date('Y-m-d H:i:s');

        if ($change['delete']) {
            // Supprimer l'enregistrement correspondant
            $data = $data->reject(function ($item) use ($change) {
                return $item['file'] === $change['file']
                    && $item['key'] === $change['property']
                    && $item['context'] === $change['context'];
            });
        } else {
            $type = gettype($change['value']);
            $prepared = $this->prepareValue($change['value']);

            // Chercher si l'enregistrement existe déjà
            $existingIndex = null;
            $existing = $data->first(function ($item, $index) use ($change, &$existingIndex) {
                $exists = $item['file'] === $change['file']
                    && $item['key'] === $change['property']
                    && $item['context'] === $change['context'];

                if ($exists) {
                    $existingIndex = $index;
                }

                return $exists;
            });

            if ($existing) {
                // Mettre à jour l'enregistrement existant
                $data = $data->map(function ($item, $index) use ($existingIndex, $prepared, $type, $change, $time) {
                    if ($index !== $existingIndex) {
                        return $item;
                    }

                    return array_merge($item, [
                        'value'      => $prepared,
                        'type'       => $type,
                        'updated_at' => $time,
                    ]);
                });
            } else {
                // Créer un nouvel enregistrement
                $data = $data->add([
                    'id'         => uniqid('', true),
                    'file'       => $change['file'],
                    'key'        => $change['property'],
                    'value'      => $prepared,
                    'type'       => $type,
                    'context'    => $change['context'],
                    'created_at' => $time,
                    'updated_at' => $time,
                ]);
            }
        }
    }

    /**
     * Charge les données à partir du fichier JSON via un handle de fichier.
     *
     * @param resource $handle
     */
    private function loadDataFromHandle($handle): Collection
    {
        // Lire le contenu du fichier
        $content = '';
        rewind($handle);
        while (! feof($handle)) {
            $content .= fread($handle, 8192);
        }

        if (trim($content) === '') {
            return collect([]);
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            return collect([]);
        }

        return collect($data);
    }

    /**
     * Sauvegarde les données dans le fichier JSON via un handle de fichier.
     *
     * @param resource $handle
     */
    private function saveDataToHandle($handle, Collection $data): void
    {
        // Vider le fichier
        ftruncate($handle, 0);
        rewind($handle);

        // Écrire les nouvelles données
        $content = json_encode($data->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (fwrite($handle, $content) === false) {
            throw new RuntimeException('Impossible d\'écrire dans le fichier JSON : ' . $this->file);
        }

        fflush($handle);
    }

    /**
     * Charge toutes les données du fichier JSON.
     */
    private function loadData(): Collection
    {
        $content = file_get_contents($this->file);

        if ($content === false) {
            throw new RuntimeException('Impossible de lire le fichier JSON : ' . $this->file);
        }

        if (trim($content) === '') {
            return collect([]);
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            return collect([]);
        }

        return collect($data);
    }

    /**
     * Sauvegarde toutes les données dans le fichier JSON.
     */
    private function saveData(array $data): void
    {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($this->file, $content) === false) {
            throw new RuntimeException('Impossible d\'écrire dans le fichier JSON : ' . $this->file);
        }
    }
}
