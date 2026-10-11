<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Parametres;

use BlitzPHP\Parametres\Handlers\BaseHandler;
use BlitzPHP\Utilities\Iterable\Arr;
use InvalidArgumentException;
use RuntimeException;

/**
 * Permet aux développeurs de stocker et de récupérer en un seul endroit
 * les paramètres définis à l'origine dans les fichiers de configuration de
 * l'application principale ou d'un module tiers.
 */
class Parametres
{
    /**
     * Un tableau de gestionnaires permettant d'obtenir ou de définir les valeurs.
     *
     * @var list<BaseHandler>
     */
    private array $handlers = [];

    /**
     * Un tableau d'options de configuration pour chaque gestionnaire.
     *
     * @var array<string,array<string,mixed>>
     */
    private ?array $options = null;

    /**
     * Saisit les instances de nos gestionnaires.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        foreach ($config['handlers'] as $handler) {
            $class = $config[$handler]['class'] ?? null;

            if ($class === null) {
                continue;
            }

            $this->handlers[$handler] = new $class($config[$handler]);
            $this->options[$handler]  = $config[$handler];
        }
    }

    /**
     * Récupère une valeur de n'importe quel gestionnaire ou d'un fichier de configuration correspondant au nom file.arg.optionalArg
     */
    public function get(string $key, ?string $context = null, mixed $default = null): mixed
    {
        [$file, $property, $config, $dotProperty] = $this->prepareFileAndProperty($key);

        // Vérifier chacun de nos gestionnaires
        foreach ($this->handlers as $handler) {
            if ($handler->has($file, $property, $context)) {
                if (is_array($data = $handler->get($file, $property, $context)) && $property !== $dotProperty) {
                    return Arr::get($data, str_replace($property . '.', '', $dotProperty), $default);
                }

                return $data;
            }
        }

        // Si aucune valeur contextuelle n'a été trouvée, on revient à la valeur générale.
        if ($context !== null) {
            return $this->get($key, null, $default);
        }

        return Arr::get($config, $dotProperty, $default);
    }

    /**
     * Récupérer plusieurs valeurs en utilisant le même comportement que la fonction get().
     *
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMany(array $keys, ?string $context = null): array
    {
        $settings = [];

        foreach ($keys as $key) {
            $settings[$key] = $this->get($key, $context);
        }

        return $settings;
    }

    /**
     * Sauvegarde d'une valeur dans le gestionnaire d'écriture pour récupération ultérieure.
     */
    public function set(string $key, mixed $value = null, ?string $context = null): void
    {
        [$file, , , $dotProperty] = $this->prepareFileAndProperty($key);

        foreach ($this->getWriteHandlers() as $handler) {
            $handler->set($file, $dotProperty, $value, $context);
        }
    }

    /**
     * Enregistrez plusieurs valeurs dans le gestionnaire inscriptible afin de pouvoir les récupérer ultérieurement.
     *
     * @param array<string, mixed> $settings
     */
    public function setMany(array $settings, ?string $context = null): void
    {
        if ($settings === []) {
            return;
        }

        $prepared = [];

        foreach ($settings as $key => $value) {
            [$file, , , $dotProperty] = $this->prepareFileAndProperty($key);

            $prepared[$file . '::' . $dotProperty] = [
                'file'     => $file,
                'property' => $dotProperty,
                'value'    => $value,
            ];
        }

        $prepared = array_values($prepared);

        foreach ($this->getWriteHandlers() as $handler) {
            $handler->setMany($prepared, $context);
        }
    }

    /**
     * Supprime un paramètre de la mémoire persistante,
     * en ramenant la valeur à la valeur par défaut trouvée dans le fichier de configuration, s'il y en a un.
     */
    public function forget(string $key, ?string $context = null): void
    {
        [$file, , , $dotProperty] = $this->prepareFileAndProperty($key);

        foreach ($this->getWriteHandlers() as $handler) {
            $handler->forget($file, $dotProperty, $context);
        }
    }

    /**
     * Supprime plusieurs paramètres du stockage persistant,
     * ce qui revient en fait à rétablir les valeurs par défaut figurant dans le fichier de configuration, le cas échéant.
     *
     * @param list<string> $keys
     */
    public function forgetMany(array $keys, ?string $context = null): void
    {
        if ($keys === []) {
            return;
        }

        $prepared = [];

        foreach ($keys as $key) {
            [$file, , , $dotProperty] = $this->prepareFileAndProperty($key);

            $prepared[$file . '::' . $dotProperty] = [
                'file'     => $file,
                'property' => $dotProperty,
            ];
        }

        $prepared = array_values($prepared);

        foreach ($this->getWriteHandlers() as $handler) {
            $handler->forgetMany($prepared, $context);
        }
    }

    /**
     * Supprime tous les paramètres de la mémoire permanente, utile lors des tests.
     * A utiliser avec précaution.
     */
    public function flush(): void
    {
        foreach ($this->getWriteHandlers() as $handler) {
            $handler->flush();
        }
    }

    /**
     * Renvoie les gestionnaires qui ont été défini pour stocker les valeurs.
     *
     * @return list<BaseHandler>
     *
     * @throws RuntimeException
     */
    private function getWriteHandlers(): array
    {
        $handlers = [];

        foreach ($this->options as $handler => $options) {
            if (! empty($options['writeable'])) {
                $handlers[] = $this->handlers[$handler];
            }
        }

        if ($handlers === []) {
            throw new RuntimeException('Impossible de trouver un gestionnaire de paramètres capable de stocker des valeurs.');
        }

        return $handlers;
    }

    /**
     * Analyse la clé donnée et la décompose en parties fichier.champ.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private function parseDotSyntax(string $key): array
    {
        // Analyse le nom du champ pour fichier.champ
        $parts = explode('.', $key);

        if (count($parts) === 1) {
            throw new InvalidArgumentException('$key doit contenir à la fois le nom du fichier et celui du champ, exp. foo.bar');
        }

        return $parts;
    }

    /**
     * Étant donné une clé dans la syntaxe fichier.champ,
     * divise les valeurs et détermine le nom du fichier.
     *
     * @return list<mixed|string>
     */
    private function prepareFileAndProperty(string $key): array
    {
        $parts    = $this->parseDotSyntax($key);
        $file     = array_shift($parts);
        $property = $parts[0];

        $config = config($file);

        return [$file, $property, $config, implode('.', $parts)];
    }
}
