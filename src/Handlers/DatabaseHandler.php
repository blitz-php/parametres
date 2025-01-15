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
use BlitzPHP\Database\ConnectionResolver;
use BlitzPHP\Utilities\Date;
use RuntimeException;
use stdClass;

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

    private stdClass $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
		if ($config === []) {
			$config = config('parametres.database', []);
		}

        $this->config  = (object) $config;

        $this->db      = (new ConnectionResolver())->connect($this->config->group);
        $this->builder = $this->db->table($this->config->table);
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
        $time     = Date::now()->format('Y-m-d H:i:s');
        $type     = gettype($value);
        $prepared = $this->prepareValue($value);

        // S'il a été stocké, nous devons le mettre à jour
        if ($this->has($file, $property, $context)) {
            $result = $this->builder()->where('file', $file)->where('key', $property);

			if ($context === null) {
				$result = $result->whereNull('context');
			} else {
				$result = $result->where('context', $context);
			}

			$result = $result->update([
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

        // Mise à jour du stockage
        $this->setStored($file, $property, $value, $context);
    }

    /**
     * Supprime l'enregistrement du stockage permanent, s'il existe, et du cache local.
     */
    public function forget(string $file, string $property, ?string $context = null): void
    {
        $this->hydrate($context);

        // Supprimer de la base de données

		$builder = $this->builder()->where('file', $file)->where('key', $property);

	   	if (null === $context) {
			$builder->whereNull('context');
	   	} else {
			$builder->where('context', $context);
	   	}

		$result = $builder->delete();

        if (! $result) {
            throw new RuntimeException($this->db->error()['message'] ?? 'Erreur d\'écriture dans la base de données.');
        }

        // Supprimer de la mémoire locale
        $this->forgetStored($file, $property, $context);
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
            $query = $this->builder()->whereNull('context');
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

	private function builder(): BaseBuilder
	{
		return $this->builder->reset()->table($this->config->table);
	}
}
