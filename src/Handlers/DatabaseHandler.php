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

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\DatabaseException;
use RuntimeException;

/**
 * Fournit une persistance de base de données pour les paramètres.
 * Utilise ArrayHandler pour le stockage afin de minimiser les appels à la base de données.
 */
class DatabaseHandler extends ArrayHandler
{
    /**
     * La connexion à la base de données pour les paramètres.
     */
    private BaseConnection $db;

    /**
     * Le générateur de requêtes pour la table Paramètres.
     */
    private BaseBuilder $builder;

    /**
     * Tableau des contextes qui ont été stockés.
     *
     * @var list<null>|list<string>
     */
    private array $hydrated = [];

    private object $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        if ($config === []) {
            $config = config('parametres.database', []);
        }

        $this->config = (object) $config;

        $this->db      = db($this->config->group);
        $this->builder = $this->db->table($this->config->table);

        $this->setupDeferredWrites($this->config->defer_writes ?? false);
    }

    /**
     * Vérifie si ce gestionnaire a une valeur définie.
     */
    public function has(string $file, string $property, ?string $context = null): bool
    {
        $this->hydrate($context);

        return $this->hasStored($file, $property, $context);
    }

    /**
     * Tentative d'extraction d'une valeur de la base de données.
     * Pour améliorer les performances, toutes les valeurs sont lues et stockées lors du premier appel
     * pour chaque contexte, puis récupérées dans la base de données.
     *
     * @return mixed|null
     */
    public function get(string $file, string $property, ?string $context = null): mixed
    {
        return $this->getStored($file, $property, $context);
    }

    /**
     * Enregistre les valeurs dans la base de données pour les retrouver ultérieurement.
     *
     * @throws RuntimeException En cas d'échec de la base de données
     */
    public function set(string $file, string $property, mixed $value = null, ?string $context = null): void
    {
        if ($this->deferWrites) {
            $this->hydrate($context);
            $this->markPending($file, $property, $value, $context);
        } else {
            $this->persist($file, $property, $value, $context);
        }

        // Mise à jour du stockage après la vérification de la persistance
        $this->setStored($file, $property, $value, $context);
    }

    /**
     * Enregistre plusieurs valeurs dans la base de données en vue d'une consultation ultérieure.
     *
     * @param list<array{file: string, property: string, value: mixed}> $settings
     *
     * @throws RuntimeException Pour les échecs de base de données
     */
    public function setMany(array $settings, ?string $context = null): void
    {
        if ($settings === []) {
            return;
        }

        if ($this->deferWrites) {
            $this->hydrate($context);

            foreach ($settings as $setting) {
                $this->markPending($setting['file'], $setting['property'], $setting['value'], $context);
                $this->setStored($setting['file'], $setting['property'], $setting['value'], $context);
            }

            return;
        }

        $this->persistRows($this->prepareUpsertRows($settings, $context), []);

        foreach ($settings as $setting) {
            $this->setStored($setting['file'], $setting['property'], $setting['value'], $context);
        }
    }

    /**
     * Enregistre une seule propriété dans la base de données.
     *
     * @throws RuntimeException En cas d'échec de la base de données
     */
    private function persist(string $file, string $property, mixed $value, ?string $context): void
    {
        $time     = date('Y-m-d H:i:s');
        $type     = gettype($value);
        $prepared = $this->prepareValue($value);

        // S'il a été stocké, nous devons le mettre à jour
        if ($this->has($file, $property, $context)) {
            $query = $this->builder()->where('file', $file)->where('key', $property);

            if ($context === null) {
                $query->whereNull('context');
            } else {
                $query->where('context', $context);
            }

            $result = $query->update([
                'value'      => $prepared,
                'type'       => $type,
                'context'    => $context,
                'updated_at' => $time,
            ]);
            // ...sinon l'insérer
        } else {
            $result = $this->builder()->insert([
                'file'       => $file,
                'key'        => $property,
                'value'      => $prepared,
                'type'       => $type,
                'context'    => $context,
                'created_at' => $time,
                'updated_at' => $time,
            ]);
        }

        if (! $result) {
            throw new RuntimeException($this->db->error()['message'] ?? 'Erreur d\'écriture dans la base de données.');
        }
    }

    /**
     * Supprime l'enregistrement du stockage permanent, s'il existe, et du cache local.
     */
    public function forget(string $file, string $property, ?string $context = null): void
    {
        $this->hydrate($context);

        if ($this->deferWrites) {
            $this->markPending($file, $property, null, $context, true);
        } else {
            $this->persistForget($file, $property, $context);
        }

        // Supprimer de la mémoire locale
        $this->forgetStored($file, $property, $context);
    }

    /**
     * Supprime plusieurs enregistrements du stockage persistant,
     * s'ils existent, ainsi que du cache local.
     *
     * @param list<array{file: string, property: string}> $settings
     */
    public function forgetMany(array $settings, ?string $context = null): void
    {
        if ($settings === []) {
            return;
        }

        $this->hydrate($context);

        if ($this->deferWrites) {
            foreach ($settings as $setting) {
                $this->markPending($setting['file'], $setting['property'], null, $context, true);
                $this->forgetStored($setting['file'], $setting['property'], $context);
            }

            return;
        }

        $this->persistRows([], $this->prepareDeleteRows($settings, $context));

        foreach ($settings as $setting) {
            $this->forgetStored($setting['file'], $setting['property'], $context);
        }
    }

    /**
     * Supprime une seule propriété de la base de données.
     *
     * @throws RuntimeException En cas d'échec de la base de données
     */
    private function persistForget(string $file, string $property, ?string $context): void
    {
        $builder = $this->builder()->where('file', $file)->where('key', $property);

        if (null === $context) {
            $builder->whereNull('context');
        } else {
            $builder->where('context', $context);
        }

        try {
            $builder->delete();
        } catch (DatabaseException $e) {
            throw new RuntimeException('Erreur d\'écriture dans la base de données: ' . $e->getMessage());
        }
    }

    /**
     * Supprime tous les enregistrements de la mémoire permanente, si elle existe, et du cache local.
     */
    public function flush(): void
    {
        $this->builder()->truncate();

        parent::flush();
    }

    /**
     * Récupère les valeurs de la base de données en vrac pour minimiser les appels.
     * Le général (null) est toujours récupéré une fois, les contextes sont récupérés dans leur intégralité pour chaque nouvelle requête.
     *
     * @throws RuntimeException En cas d'échec de la base de données
     */
    private function hydrate(?string $context = null): void
    {
        // Vérification de l'achèvement des travaux
        if (in_array($context, $this->hydrated, true)) {
            return;
        }

        if ($context === null) {
            $this->hydrated[] = null;
            $query            = $this->builder()->whereNull('context');
        } else {
            $query = $this->builder()->where('context', $context);

            // Si le général n'a pas été hydraté, nous le ferons en même temps.
            if (! in_array(null, $this->hydrated, true)) {
                $this->hydrated[] = null;
                $query->orWhereNull('context');
            }

            $this->hydrated[] = $context;
        }

        foreach ($query->result('object') as $row) {
            $this->setStored($row->file, $row->key, $this->parseValue($row->value, $row->type), $row->context);
        }
    }

    /**
     * Enregistre toutes les propriétés en attente dans la base de données.
     * Appelé automatiquement à la fin de la requête via l'événement post_system lorsque l'option deferWrites est activée.
     */
    public function persistPendingProperties(): void
    {
        if ($this->pendingProperties === []) {
            return;
        }

        $time = date('Y-m-d H:i:s');

        // Distinguer les suppressions des mises à jour avec insertion et préparer les opérations sur la base de données
        $deletes = [];
        $upserts = [];

        foreach ($this->pendingProperties as $info) {
            if ($info['delete']) {
                // Préparez la suppression de la ligne en indiquant les noms de colonnes corrects de la base de données
                $deletes[] = [
                    'file'    => $info['file'],
                    'key'     => $info['property'],
                    'context' => $info['context'],
                ];
            } else {
                // Préparez la ligne d'insertion/mise à jour avec les noms de colonnes corrects de la base de données
                $upserts[] = [
                    'file'       => $info['file'],
                    'key'        => $info['property'],
                    'value'      => $this->prepareValue($info['value']),
                    'type'       => gettype($info['value']),
                    'context'    => $info['context'],
                    'created_at' => $time,
                    'updated_at' => $time,
                ];
            }
        }

        try {
            $this->persistRows($upserts, $deletes);

            $this->pendingProperties = [];
        } catch (DatabaseException|RuntimeException $e) {
            logger()->error('Échec de la persistance des propriétés en attente : ' . $e->getMessage());

            $this->pendingProperties = [];
        }
    }

    /**
     * Prépare les lignes de la base de données en vue de leur persistance.
     *
     * @param list<array{file: string, property: string, value: mixed}> $settings
     *
     * @return list<array{file: string, key: string, value: mixed, type: string, context: string|null, created_at: string, updated_at: string}>
     */
    private function prepareUpsertRows(array $settings, ?string $context): array
    {
        $time = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($settings as $setting) {
            $rows[] = [
                'file'       => $setting['file'],
                'key'        => $setting['property'],
                'value'      => $this->prepareValue($setting['value']),
                'type'       => gettype($setting['value']),
                'context'    => $context,
                'created_at' => $time,
                'updated_at' => $time,
            ];
        }

        return $rows;
    }

    /**
     * Prépare les lignes de la base de données en vue de leur suppression définitive.
     *
     * @param list<array{file: string, property: string}> $settings
     *
     * @return list<array{file: string, key: string, context: string|null}>
     */
    private function prepareDeleteRows(array $settings, ?string $context): array
    {
        $rows = [];

        foreach ($settings as $setting) {
            $rows[] = [
                'file'    => $setting['file'],
                'key'     => $setting['property'],
                'context' => $context,
            ];
        }

        return $rows;
    }

    /**
     * Persiste les lignes préparées dans la base de données.
     *
     * @param list<array{file: string, key: string, value: mixed, type: string, context: string|null, created_at: string, updated_at: string}> $upserts
     * @param list<array{file: string, key: string, context: string|null}>                                                                     $deletes
     */
    private function persistRows(array $upserts, array $deletes): void
    {
        if ($upserts === [] && $deletes === []) {
            return;
        }

        $this->db->beginTransaction();

        // Gérer les mises à jour avec insertion : récupérer les enregistrements existants correspondant à nos données en attente
        if ($upserts !== []) {
            // Construire une requête pour récupérer uniquement les enregistrements dont nous avons besoin
            $builder = $this->buildOrWhereConditions($upserts, 'file', 'key', 'context');

            $existing = $builder->clone()->result('array');

            // Créez une carte des enregistrements existants pour faciliter la recherche
            $existingMap = [];

            foreach ($existing as $row) {
                $key               = $this->buildCompositeKey($row['file'], $row['key'], $row['context']);
                $existingMap[$key] = $row['id'];
            }

            // Distinguer les insertions des mises à jour
            $inserts = [];
            $updates = [];

            foreach ($upserts as $row) {
                $key = $this->buildCompositeKey($row['file'], $row['key'], $row['context']);

                if (isset($existingMap[$key])) {
                    // L'enregistrement existe - se préparer à la mise à jour
                    $updates[] = [
                        'id'         => $existingMap[$key],
                        'value'      => $row['value'],
                        'type'       => $row['type'],
                        'updated_at' => $row['updated_at'],
                    ];
                } else {
                    // Nouvel enregistrement - préparation à l'insertion
                    $inserts[] = $row;
                }
            }

            // Insérer de nouveaux enregistrements par lots
            if ($inserts !== []) {
                $builder->bulkInsert($inserts);
            }

            // Mise à jour groupée des enregistrements existants
            if ($updates !== []) {
                $builder->bulkUpdate($updates, 'id');
            }
        }

        // Supprimer en bloc toutes les opérations de suppression
        if ($deletes !== []) {
            $this->buildOrWhereConditions($deletes, 'file', 'key', 'context')->delete();
        }

        $this->db->commit();

        if ($this->db->transStatus() === false) {
            logger()->error("Impossible d'enregistrer les propriétés en attente dans la base de données.");
        }
    }

    /**
     * Crée une clé composite à des fins de recherche.
     */
    private function buildCompositeKey(string $file, string $key, ?string $context): string
    {
        return $file . '::' . $key . ($context === null ? '' : '::' . $context);
    }

    /**
     * Crée des conditions OR WHERE pour plusieurs lignes.
     */
    private function buildOrWhereConditions(array $rows, string $fileKey, string $keyKey, string $contextKey): BaseBuilder
    {
        $builder = $this->builder();

        foreach ($rows as $row) {
            $builder->orWhere(function ($q) use ($row, $fileKey, $keyKey, $contextKey) {
                $q->where([$fileKey => $row[$fileKey], $keyKey => $row[$keyKey]]);
                if (null === $row[$contextKey]) {
                    $q->whereNull($contextKey);
                } else {
                    $q->where($contextKey, $row[$contextKey]);
                }
            });
        }

        return $builder;
    }

    private function builder(): BaseBuilder
    {
        return $this->builder->reset()->table($this->config->table);
    }
}
