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
use BlitzPHP\Utilities\Date;
use BlitzPHP\Utilities\Iterable\Collection;

class FileHandler extends ArrayHandler
{
    /**
     * Chemin d'accès du fichier de stockage des paramètres
     */
    private string $path;

    /**
     * Tableau des contextes qui ont été stockés.
     *
     * @var list<null>|list<string>
     */
    private array $hydrated = [];

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        if ($config === []) {
            $config = config('parametres.file', []);
        }

        if ('' === $this->path = ($config['path'] ?? '')) {
            throw ParametresException::fileForStorageNotDefined();
        }
        if (! is_dir(pathinfo($this->path, PATHINFO_DIRNAME))) {
            throw ParametresException::directoryOfFileNotFound($this->path);
        }
        if (! file_exists($this->path)) {
            file_put_contents($this->path, '[]');
        }
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
    public function set(string $file, string $property, mixed $value = null, ?string $context = null): void
    {
        $time     = Date::now()->format('Y-m-d H:i:s');
        $type     = gettype($value);
        $prepared = $this->prepareValue($value);

        $data = $this->getData();

        // S'il a été stocké, nous devons le mettre à jour
        if ($this->has($file, $property, $context)) {
            $updated = $data->where('file', $file)->where('key', $property)->whereStrict('context', $context)->first();

            $data = $data->map(fn ($item) => $item['id'] !== $updated['id'] ? $item : array_merge($item, [
                'value'      => $prepared,
                'type'       => $type,
                'context'    => $context,
                'updated_at' => $time,
            ]));
            // ...sinon l'insérer
        } else {
            $data = $data->add([
                'id'         => uniqid(more_entropy: true),
                'file'       => $file,
                'key'        => $property,
                'value'      => $prepared,
                'type'       => $type,
                'context'    => $context,
                'created_at' => $time,
                'updated_at' => $time,
            ]);
        }

        $this->saveDate($data);

        // Modifier dans la memoire locale
        $this->setStored($file, $property, $value, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $file, string $property, ?string $context = null): void
    {
        $this->hydrate($context);

        $data = $this->getData();

        $deleted = $data->where('file', $file)->where('key', $property)->whereStrict('context', $context)->first();
        $data    = $data->filter(fn ($item) => $item['id'] !== $deleted['id']);

        $this->saveDate($data);

        // Supprimer dans la mémoire locale
        $this->forgetStored($file, $property, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): void
    {
        $this->saveDate(collect([]));

        parent::flush();
    }

    /**
     * Récupère les valeurs de la base de données en vrac pour minimiser les appels.
     * Le général (null) est toujours récupéré une fois, les contextes sont récupérés dans leur intégralité pour chaque nouvelle requête.
     */
    private function hydrate(?string $context = null): void
    {
        // Vérification de l'achèvement des travaux
        if (in_array($context, $this->hydrated, true)) {
            return;
        }

        $data = $this->getData();

        if ($context === null) {
            $this->hydrated[] = null;
            $data             = $data->whereNull('context');
        } else {
            // Si le général n'a pas été hydraté, on l'hydrate donc.
            if (! in_array(null, $this->hydrated, true)) {
                $this->hydrated[] = null;
            } else {
                $data = $data->where('context', $context);
            }

            $this->hydrated[] = $context;
        }

        foreach ($data->all() as $row) {
            $this->setStored($row['file'], $row['key'], $this->parseValue($row['value'], $row['type']), $row['context']);
        }
    }

    /**
     * Recupère les données à partir du fichier servant de source de données
     */
    private function getData(): Collection
    {
        $data = json_decode(file_get_contents($this->path), true) ?: [];

        return collect($data);
    }

    /**
     * Persiste les données dans le fichier servant de source de données
     */
    private function saveDate(Collection $data): void
    {
        $data = $data->toArray();

        file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT));
    }
}
