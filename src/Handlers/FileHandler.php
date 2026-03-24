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

use RuntimeException;

/**
 * Fournit une persistance basée sur les fichiers pour les paramètres.
 * Utilise ArrayHandler pour le stockage afin de minimiser les opérations d'entrée/sortie.
 */
class FileHandler extends ArrayHandler
{
    /**
     * Tableau des combinaisons fichier+contexte qui ont été chargées depuis le disque.
     * Format : ['fichier::contexte', 'fichier::null', ...]
     *
     * @var list<string>
     */
    private array $hydrated = [];

    /**
     * Chemin de base où les fichiers de paramètres sont stockés.
     */
    private string $path;

    private object $config;

    /**
     * Configure le chemin du fichier et s'assure qu'il existe.
     *
     * @param array<string,mixed> $config Configuration du gestionnaire de fichiers
     *
     * @throws RuntimeException Si le répertoire ne peut pas être créé ou n'est pas accessible en écriture
     */
    public function __construct(array $config = [])
    {
        if ($config === []) {
            $config = config('parametres.file', []);
        }

        $this->config = (object) $config;
        $this->path   = rtrim($this->config->path ?? storage_path('app/parametres') . DIRECTORY_SEPARATOR);

        if (! is_dir($this->path) && (! mkdir($this->path, 0755, true) && ! is_dir($this->path))) {
            throw new RuntimeException('Impossible de créer le répertoire des paramètres : ' . $this->path);
        }

        if (! is_writable($this->path)) {
            throw new RuntimeException('Le répertoire des paramètres n\'est pas accessible en écriture : ' . $this->path);
        }

        $this->setupDeferredWrites($this->config->defer_writes ?? false);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $file, string $property, ?string $context = null): bool
    {
        $this->hydrate($file, $context);

        return $this->hasStored($file, $property, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $file, string $property, ?string $context = null): mixed
    {
        $this->hydrate($file, $context);

        return $this->getStored($file, $property, $context);
    }

    /**
     * Enregistre les valeurs dans un fichier afin de pouvoir les récupérer ultérieurement.
     *
     * @throws RuntimeException En cas d'échec d'écriture dans un fichier
     */
    public function set(string $file, string $property, mixed $value = null, ?string $context = null): void
    {
        $this->hydrate($file, $context);

        // Mise à jour du stockage en mémoire d'abord
        $this->setStored($file, $property, $value, $context);

        if ($this->deferWrites) {
            $this->markPending($file, $property, $value, $context);
        } else {
            // Pour les écritures immédiates, persister uniquement ce changement de propriété spécifique
            $this->persist($file, $context, [[
                'property' => $property,
                'value'    => $value,
                'delete'   => false,
            ]]);
        }
    }

    /**
     * Supprime l'enregistrement du stockage persistant, s'il est trouvé, et du cache local.
     *
     * @throws RuntimeException En cas d'échec d'écriture dans un fichier
     */
    public function forget(string $file, string $property, ?string $context = null): void
    {
        $this->hydrate($file, $context);

        // Suppression du stockage local
        $this->forgetStored($file, $property, $context);

        if ($this->deferWrites) {
            $this->markPending($file, $property, null, $context, true);
        } else {
            // Pour les écritures immédiates, persister uniquement cette suppression de propriété spécifique
            $this->persist($file, $context, [[
                'property' => $property,
                'value'    => null,
                'delete'   => true,
            ]]);
        }
    }

    /**
     * Supprime tous les fichiers de paramètres du stockage persistant et vide le cache local.
     *
     * @throws RuntimeException En cas d'échec de suppression des fichiers
     */
    public function flush(): void
    {
        // Supprimer tous les fichiers .php dans le répertoire principal (fichiers de contexte null)
        $files = glob($this->path . '*.php', GLOB_NOSORT);

        if ($files === false) {
            throw new RuntimeException('Impossible de lire le répertoire des paramètres : ' . $this->path);
        }

        foreach ($files as $file) {
            if (! unlink($file)) {
                throw new RuntimeException('Impossible de supprimer le fichier de paramètres : ' . $file);
            }
        }

        // Supprimer tous les sous-répertoires de contexte et leur contenu
        $directories = glob($this->path . '*', GLOB_ONLYDIR | GLOB_NOSORT);

        if ($directories !== false) {
            foreach ($directories as $directory) {
                // Supprimer tous les fichiers dans le répertoire
                $contextFiles = glob($directory . '/*.php', GLOB_NOSORT);

                if ($contextFiles !== false) {
                    foreach ($contextFiles as $file) {
                        if (! unlink($file)) {
                            throw new RuntimeException('Impossible de supprimer le fichier de paramètres : ' . $file);
                        }
                    }
                }

                // Supprimer le répertoire vide
                if (! rmdir($directory)) {
                    throw new RuntimeException('Impossible de supprimer le répertoire : ' . $directory);
                }
            }
        }

        // Vider le stockage local et le suivi d'hydratation
        parent::flush();
        $this->hydrated = [];
    }

    /**
     * Récupère les valeurs des fichiers en masse pour minimiser les opérations d'entrée/sortie.
     * Charge toutes les propriétés pour une combinaison fichier+contexte spécifique.
     *
     * @throws RuntimeException En cas d'échec de lecture du fichier
     */
    private function hydrate(string $file, ?string $context): void
    {
        $key = $this->getHydrationKey($file, $context);

        // Vérifier si déjà chargé
        if (in_array($key, $this->hydrated, true)) {
            return;
        }

        // Charger le fichier spécifique fichier+contexte
        $this->loadFromFile($file, $context);
        $this->hydrated[] = $key;

        // Charger également le contexte général pour cette classe s'il n'est pas déjà chargé
        if ($context !== null) {
            $generalKey = $this->getHydrationKey($file, null);

            if (! in_array($generalKey, $this->hydrated, true)) {
                $this->loadFromFile($file, null);
                $this->hydrated[] = $generalKey;
            }
        }
    }

    /**
     * Charge les paramètres depuis un fichier pour une combinaison fichier+contexte donnée.
     *
     * @throws RuntimeException En cas d'échec de lecture du fichier
     */
    private function loadFromFile(string $file, ?string $context): void
    {
        $filePath = $this->getFilePath($file, $context);

        // Si le fichier n'existe pas, c'est normal - aucun paramètre stocké pour l'instant
        if (! file_exists($filePath)) {
            return;
        }

        // Utiliser include pour obtenir le tableau de données
        $data = include $filePath;

        if (! is_array($data)) {
            throw new RuntimeException('Le fichier de paramètres ne retourne pas un tableau : ' . $filePath);
        }

        // Charger les données dans le stockage en mémoire
        foreach ($data as $property => $valueData) {
            if (! is_array($valueData) || ! isset($valueData['value'], $valueData['type'])) {
                continue;
            }

            $this->setStored($file, $property, $this->parseValue($valueData['value'], $valueData['type']), $context);
        }
    }

    /**
     * Persiste les changements de propriétés spécifiques sur le disque.
     * Utilisé à la fois pour les écritures immédiates et différées.
     *
     * @throws RuntimeException En cas d'échec d'écriture du fichier
     */
    private function persist(string $file, ?string $context, array $changes): void
    {
        $filePath = $this->getFilePath($file, $context);

        // S'assurer que le répertoire existe (particulièrement pour les sous-répertoires de contexte)
        $directory = dirname($filePath);

        if (! is_dir($directory) && (! mkdir($directory, 0755, true) && ! is_dir($directory))) {
            throw new RuntimeException('Impossible de créer le répertoire : ' . $directory);
        }

		$currentData = [];
		if (file_exists($filePath)) {
			$currentData = include $filePath;

			if (! is_array($currentData)) {
				$currentData = [];
			}
		}

		// Appliquer tous les changements en attente
		foreach ($changes as $change) {
			if ($change['delete']) {
				// Supprimer explicitement cette propriété
				unset($currentData[$change['property']]);
			} else {
				// Définir ou mettre à jour cette propriété
				$currentData[$change['property']] = [
					'value' => $change['value'],
					'type'  => gettype($change['value']),
				];
			}
		}

		// Générer le contenu du fichier PHP
		$content = '<?php' . PHP_EOL . PHP_EOL;
		$content .= 'return ' . var_export($currentData, true) . ';' . PHP_EOL;

		// Écrire le fichier
		if (file_put_contents($filePath, $content, LOCK_EX) === false) {
			throw new RuntimeException('Impossible d\'écrire le fichier de paramètres : ' . $filePath);
		}

		@chmod($filePath, 0644);
    }

    /**
     * Persiste toutes les propriétés en attente sur le disque.
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
                $this->persist($group['file'], $group['context'], $group['changes']);
            } catch (RuntimeException $e) {
                logger()->error('Échec de la persistance des propriétés en attente pour ' . $group['file'] . ' : ' . $e->getMessage());
            }
        }

        $this->pendingProperties = [];
    }

    /**
     * Génère un chemin de fichier pour une combinaison fichier+contexte donnée.
     *
     * Structure :
     * - Contexte null : storage/app/parametres/nom_config.php
     * - Avec contexte : storage/app/parametres/{hash(contexte)}/nom_config.php
     *
     * @return string Chemin complet du fichier
     */
    private function getFilePath(string $file, ?string $context): string
    {
        if ($context === null) {
            return $this->path . $file . '.php';
        }

        $contextHash = hash('xxh128', $context);

        return $this->path . $contextHash . DIRECTORY_SEPARATOR . $file . '.php';
    }

    /**
     * Génère une clé d'hydratation pour une combinaison fichier+contexte.
     * Format : $file lorsque le contexte est null, $file::$contexte sinon.
     *
     * @return string Clé d'hydratation
     */
    private function getHydrationKey(string $file, ?string $context): string
    {
        return $context === null ? $file : $file . '::' . $context;
    }
}
