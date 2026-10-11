<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Parametres\Exceptions\ParametresException;
use BlitzPHP\Parametres\Parametres;
use BlitzPHP\Spec\ReflectionHelper;
use BlitzPHP\Utilities\DateTime\Date;
use BlitzPHP\Utilities\Iterable\Arr;

use function Kahlan\expect;

describe('Parametres / JsonHandler', function () {
    beforeAll(function () {
        config()->set('parametres.json.file', $path = storage_path('.parametres.json'));
        $this->path = $path;

        $this->seeInFile = function (array $where) {
            $data = json_decode(file_get_contents($this->path), true) ?: [];
            $data = collect($data);

            foreach ($where as $k => $v) {
                if ($v === null) {
                    $data = $data->whereNull($k);
                } else {
                    $data = $data->where($k, '=', $v);
                }
            }

            return $data->isNotEmpty();
        };

        $this->insertFakeData = function (array $data) {
            $base = [
                'type'       => 'string',
                'context'    => null,
                'created_at' => Date::now()->format('Y-m-d H:i:s'),
                'updated_at' => Date::now()->format('Y-m-d H:i:s'),
            ];
            if (Arr::dimensions($data) === 1) {
                $data = [$data];
            }

            $data = array_map(fn ($item) => array_merge($base, $item + [
                'id' => uniqid(more_entropy: true),
            ]), $data);

            file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT));
        };
    });

    beforeEach(function () {
        $config             = config('parametres');
        $config['handlers'] = ['json'];

        $this->parametres = new Parametres($config);
    });

    afterEach(function () {
        @unlink($this->path);
    });

    it('Lève une exception si le chemin d\'accès du fichier de stockage n\'est pas specifié', function () {
        $config                 = config('parametres');
        $config['handlers']     = ['json'];
        $config['json']['file'] = '';

        expect(fn () => new Parametres($config))->toThrow(ParametresException::fileForStorageNotDefined());
    });

    it('Lève une exception si le dossier du fichier de stockage n\'existe pas', function () {
        $config                 = config('parametres');
        $config['handlers']     = ['json'];
        $config['json']['file'] = $path = __DIR__ . '/app/parametres.json';

        expect(fn () => new Parametres($config))->toThrow(ParametresException::directoryOfFileNotFound($path));
    });

    it('Insert bien les donnees dans le fichier de stockage', function () {
        $this->parametres->set('test.site_name', 'Foo');

        expect($this->seeInFile([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => 'Foo',
            'type'  => 'string',
        ]))->toBeTruthy();
    });

    it('Peut definir une valeur booleenne `true`', function () {
        $this->parametres->set('test.site_name', true);

        expect($this->seeInFile([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => 1,
            'type'  => 'boolean',
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeTruthy();
    });

    it('Peut definir une valeur booleenne `false`', function () {
        $this->parametres->set('test.site_name', false);

        expect($this->seeInFile([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => 0,
            'type'  => 'boolean',
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeFalsy();
    });

    it('Peut definir une valeur à `null`', function () {
        $this->parametres->set('test.site_name', null);

        expect($this->seeInFile([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => null,
            'type'  => 'NULL',
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeNull();
    });

    it('Peut inserer un tableau de donnees', function () {
        $data = ['foo' => 'bar'];
        $this->parametres->set('test.site_name', $data);

        expect($this->seeInFile([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => serialize($data),
            'type'  => 'array',
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBe($data);
    });

    it('Peut inserer un object', function () {
        $data = (object) ['foo' => 'bar'];
        $this->parametres->set('test.site_name', $data);

        expect($this->seeInFile([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => serialize($data),
            'type'  => 'object',
        ]))->toBeTruthy();

        expect((array) $this->parametres->get('test.site_name'))->toBe((array) $data);
    });

    it('Peut modifier une entree existante dans le fichier de stockage', function () {
        $this->insertFakeData([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => 'foo',
        ]);

        $this->parametres->set('test.site_name', 'Bar');

        expect($this->seeInFile([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => 'Bar',
        ]))->toBeTruthy();
    });

    it('Peut modifier une entree existante dans le fichier de stockage et laisser les autres intacte', function () {
        $this->insertFakeData([
            $t1 = [
                'file'  => 'test',
                'key'   => 'site_name',
                'value' => 'foo',
            ],
            $t2 = [
                'file'  => 'test',
                'key'   => 'site_lang',
                'value' => 'fr',
            ],
            $t3 = [
                'file'  => 'fake',
                'key'   => 'site_name',
                'value' => 'foo',
            ],
        ]);

        $this->parametres->set('test.site_name', 'Bar');

        expect($this->seeInFile([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => 'Bar',
        ]))->toBeTruthy();

        expect($this->seeInFile($t1))->toBeFalsy();

        expect($this->seeInFile($t2))->toBeTruthy();

        expect($this->seeInFile($t3))->toBeTruthy();
    });

    it('Peut fonctionner sans le fichier de configuration', function () {
        $this->parametres->set('nada.site_name', 'Bar');

        expect($this->seeInFile([
            'file'  => 'nada',
            'key'   => 'site_name',
            'value' => 'Bar',
        ]))->toBeTruthy();

        expect($this->parametres->get('nada.site_name'))->toBe('Bar');
    });

    it('Peut supprimer les donnees dans le fichier de stockage', function () {
        $this->insertFakeData([
            'file'  => 'test',
            'key'   => 'site_name',
            'value' => 'foo',
        ]);

        $this->parametres->forget('test.site_name');

        expect($this->seeInFile([
            'file' => 'test',
            'key'  => 'site_name',
        ]))->toBeFalsy();
    });

    it("Peut supprimer une donnee meme si elle n'est pas deja presente dans le fichier de stockage", function () {
        $this->parametres->forget('test.site_name');

        expect($this->seeInFile([
            'file' => 'test',
            'key'  => 'site_name',
        ]))->toBeFalsy();
    });

    it('Peut vider toutes les donnees en db, et continuer a utiliser les donnees du fichier de configuration', function () {
        // Valeur par defaut issue du fichier de config
        expect('Parametres Test')->toBe($this->parametres->get('test.site_name'));

        $this->parametres->set('test.site_name', 'Foo');

        // Doit etre la derniere valeur definie
        expect('Foo')->toBe($this->parametres->get('test.site_name'));

        $this->parametres->flush();

        expect($this->seeInFile([
            'file' => 'test',
            'key'  => 'site_name',
        ]))->toBeFalsy();

        // Doit rentrer à la valeur par défaut
        expect('Parametres Test')->toBe($this->parametres->get('test.site_name'));
    });

    it('Peut definir une donnee avec le contexte', function () {
        $this->parametres->set('test.site_name', 'Banana', 'environment:test');

        expect($this->seeInFile([
            'file'    => 'test',
            'key'     => 'site_name',
            'value'   => 'Banana',
            'type'    => 'string',
            'context' => 'environment:test',
        ]))->toBeTruthy();
    });

    it("Peut modifier les donnees d'un context uniquement", function () {
        $this->parametres->set('test.site_name', 'Humpty');
        $this->parametres->set('test.site_name', 'Jack', 'context:male');
        $this->parametres->set('test.site_name', 'Jill', 'context:female');
        $this->parametres->set('test.site_name', 'Jane', 'context:female');

        expect($this->seeInFile([
            'file'    => 'test',
            'key'     => 'site_name',
            'value'   => 'Jane',
            'type'    => 'string',
            'context' => 'context:female',
        ]))->toBeTruthy();

        expect($this->seeInFile([
            'file'    => 'test',
            'key'     => 'site_name',
            'value'   => 'Humpty',
            'type'    => 'string',
            'context' => null,
        ]))->toBeTruthy();

        expect($this->seeInFile([
            'file'    => 'test',
            'key'     => 'site_name',
            'value'   => 'Jack',
            'type'    => 'string',
            'context' => 'context:male',
        ]))->toBeTruthy();
    });

    it("Peut supprimer les donnees d'un context uniquement", function () {
        $this->parametres->set('test.site_name', 'Humpty');
        $this->parametres->set('test.site_name', 'Jack', 'context:male');
        $this->parametres->set('test.site_name', 'Jane', 'context:female');

        $this->parametres->forget('test.site_name', 'context:female');

        expect($this->seeInFile([
            'file'    => 'test',
            'key'     => 'site_name',
            'context' => 'context:female',
        ]))->toBeFalsy();

        expect($this->seeInFile([
            'file'    => 'test',
            'key'     => 'site_name',
            'value'   => 'Humpty',
            'type'    => 'string',
            'context' => null,
        ]))->toBeTruthy();

        expect($this->seeInFile([
            'file'    => 'test',
            'key'     => 'site_name',
            'value'   => 'Jack',
            'type'    => 'string',
            'context' => 'context:male',
        ]))->toBeTruthy();
    });

    it('Le fichier JSON reste valide apres plusieurs operations', function () {
        $this->parametres->set('test.site_name', 'Foo');
        $this->parametres->set('test.site_email', 'foo@example.com');
        $this->parametres->forget('test.site_name');
        $this->parametres->set('test.site_title', 'Bar');

        $content = file_get_contents($this->path);
        $data    = json_decode($content, true);

        expect($data)->toBeA('array');
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
    });

    describe('setMany', function () {
        it('Insere plusieurs donnees en une seule operation', function () {
            $this->parametres->setMany([
                'test.site_name'  => 'BatchName',
                'test.site_email' => 'batch@example.com',
                'test.site_title' => 'BatchTitle',
            ]);

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'BatchName',
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_email', 'value' => 'batch@example.com',
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_title', 'value' => 'BatchTitle',
            ]))->toBeTruthy();
        });

        it('Insere plusieurs donnees avec le contexte', function () {
            $this->parametres->setMany([
                'test.site_name'  => 'ContextName',
                'test.site_email' => 'context@example.com',
            ], 'environment:test');

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'ContextName', 'context' => 'environment:test',
            ]))->toBeTruthy();
        });

        it('Insere plusieurs donnees pour differents fichiers', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'nada.site_name' => 'NadaName',
            ]);

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'BatchName',
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'nada', 'key' => 'site_name', 'value' => 'NadaName',
            ]))->toBeTruthy();
        });

        it('Ne cree pas de doublons lors de la mise a jour via setMany', function () {
            $this->parametres->setMany([
                'test.site_name'  => 'InitialName',
                'test.site_email' => 'initial@example.com',
            ]);

            $this->parametres->setMany([
                'test.site_name'  => 'UpdatedName',
                'test.site_email' => 'updated@example.com',
                'test.site_title' => 'NewTitle',
            ]);

            $data = json_decode(file_get_contents($this->path), true);
            $data = collect($data)->where('file', 'test');

            expect($data->count())->toBe(3);

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'UpdatedName',
            ]))->toBeTruthy();
        });
    });

    describe('forgetMany', function () {
        it('Supprime plusieurs donnees', function () {
            $this->parametres->setMany([
                'test.site_name'  => 'BatchName',
                'test.site_email' => 'batch@example.com',
                'test.site_title' => 'BatchTitle',
            ]);

            $this->parametres->forgetMany(['test.site_name', 'test.site_email']);

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name',
            ]))->toBeFalsy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_email',
            ]))->toBeFalsy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_title', 'value' => 'BatchTitle',
            ]))->toBeTruthy();
        });

        it('Supprime plusieurs donnees pour differents fichiers', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'nada.site_name' => 'NadaName',
            ]);

            $this->parametres->forgetMany(['test.site_name', 'nada.site_name']);

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name',
            ]))->toBeFalsy();
            expect($this->seeInFile([
                'file' => 'nada', 'key' => 'site_name',
            ]))->toBeFalsy();
        });
    });

    describe('getMany', function () {
        it('Recupere plusieurs valeurs', function () {
            $this->parametres->setMany([
                'test.site_name'  => 'BatchName',
                'test.site_email' => 'batch@example.com',
            ]);

            $results = $this->parametres->getMany(['test.site_name', 'test.site_email']);

            expect($results['test.site_name'])->toBe('BatchName');
            expect($results['test.site_email'])->toBe('batch@example.com');
        });

        it('Retourne un tableau vide pour une liste vide', function () {
            expect($this->parametres->getMany([]))->toBe([]);
        });
    });

    describe('has', function () {
        it('Retourne true si la valeur existe', function () {
            $this->parametres->set('test.site_name', 'Foo');

            $handlers = ReflectionHelper::getPrivateProperty($this->parametres, 'handlers');
            $handler  = $handlers['json'];

            expect($handler->has('test', 'site_name'))->toBeTruthy();
        });

        it('Retourne false si la valeur n\'existe pas', function () {
            $handlers = ReflectionHelper::getPrivateProperty($this->parametres, 'handlers');
            $handler  = $handlers['json'];

            expect($handler->has('test', 'non_existent'))->toBeFalsy();
        });
    });

    describe('Écritures différées', function () {
        beforeEach(function () {
            $config                         = config('parametres');
            $config['handlers']             = ['json'];
            $config['json']['defer_writes'] = true;

            $this->parametres = new Parametres($config);

            $this->getJsonHandler = function () {
                $handlers = ReflectionHelper::getPrivateProperty($this->parametres, 'handlers');

                return $handlers['json'] ?? null;
            };

            $this->getPendingProperties = fn ($handler) => ReflectionHelper::getPrivateProperty($handler, 'pendingProperties');
        });

        it('Ne persiste pas immediatement les donnees', function () {
            $this->parametres->set('test.site_name', 'Foo');

            $data = json_decode(file_get_contents($this->path), true) ?: [];

            expect($data)->toBe([]);
            expect($this->parametres->get('test.site_name'))->toBe('Foo');
        });

        it('Persiste les donnees lors de l\'appel a persistPendingProperties', function () {
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_lang', 'fr');

            $handler = $this->getJsonHandler();
            $handler->persistPendingProperties();

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'Foo',
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_lang', 'value' => 'fr',
            ]))->toBeTruthy();
        });

        it('Regroupe les modifications multiples avant persistance', function () {
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_name', 'Bar');
            $this->parametres->set('test.site_lang', 'en');

            $handler = $this->getJsonHandler();

            $pendingProperties = $this->getPendingProperties($handler);
            expect(count($pendingProperties))->toBe(2);

            $handler->persistPendingProperties();

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'Bar',
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_lang', 'value' => 'en',
            ]))->toBeTruthy();
        });

        it('Regroupe les suppressions avec les modifications', function () {
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_lang', 'fr');

            $handler = $this->getJsonHandler();
            $handler->persistPendingProperties();

            $this->parametres->set('test.site_name', 'Bar');
            $this->parametres->forget('test.site_lang');

            $handler->persistPendingProperties();

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'Bar',
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_lang',
            ]))->toBeFalsy();
        });

        it('Gere correctement les modifications avec contextes differes', function () {
            $this->parametres->set('test.site_name', 'General');
            $this->parametres->set('test.site_name', 'Specific', 'context:test');
            $this->parametres->set('test.site_lang', 'fr', 'context:test');

            $handler = $this->getJsonHandler();

            $pendingProperties = $this->getPendingProperties($handler);
            expect(count($pendingProperties))->toBe(3);

            $handler->persistPendingProperties();

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'General', 'context' => null,
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'Specific', 'context' => 'context:test',
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_lang', 'value' => 'fr', 'context' => 'context:test',
            ]))->toBeTruthy();
        });

        it('Ne fait rien si aucune propriete en attente', function () {
            $handler = $this->getJsonHandler();

            expect(fn () => $handler->persistPendingProperties())->not->toThrow();
        });

        it('Maintient l\'integrite des donnees lors d\'operations multiples', function () {
            $this->parametres->set('test.site_name', 'Value1');
            $this->parametres->set('test.site_name', 'Value2');
            $this->parametres->forget('test.site_name');
            $this->parametres->set('test.site_name', 'Value3');

            $handler = $this->getJsonHandler();
            $handler->persistPendingProperties();

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'Value3',
            ]))->toBeTruthy();
        });

        it('Suppression puis ecriture differee : seule la derniere operation compte', function () {
            $this->parametres->set('test.site_name', 'InitialValue');

            $handler = $this->getJsonHandler();
            $handler->persistPendingProperties();

            $config                         = config('parametres');
            $config['handlers']             = ['json'];
            $config['json']['defer_writes'] = true;
            $deferred                       = new Parametres($config);

            $deferred->forget('test.site_name');
            $deferred->set('test.site_name', 'NewValue');

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'InitialValue',
            ]))->toBeTruthy();

            $handlers = ReflectionHelper::getPrivateProperty($deferred, 'handlers');
            $handlers['json']->persistPendingProperties();

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'NewValue',
            ]))->toBeTruthy();
        });

        it('setMany en mode differe persiste apres persistPendingProperties', function () {
            $this->parametres->setMany([
                'test.site_name'  => 'DeferredName',
                'test.site_email' => 'deferred@example.com',
            ]);

            $data = json_decode(file_get_contents($this->path), true) ?: [];
            expect($data)->toBe([]);

            $handler = $this->getJsonHandler();
            $handler->persistPendingProperties();

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name', 'value' => 'DeferredName',
            ]))->toBeTruthy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_email', 'value' => 'deferred@example.com',
            ]))->toBeTruthy();
        });

        it('forgetMany en mode differe supprime apres persistPendingProperties', function () {
            $this->parametres->set('test.site_name', 'BatchName');
            $this->parametres->set('test.site_email', 'batch@example.com');

            $handler = $this->getJsonHandler();
            $handler->persistPendingProperties();

            $config                         = config('parametres');
            $config['handlers']             = ['json'];
            $config['json']['defer_writes'] = true;
            $deferred                       = new Parametres($config);

            $deferred->forgetMany(['test.site_name', 'test.site_email']);

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name',
            ]))->toBeTruthy();

            $handlers = ReflectionHelper::getPrivateProperty($deferred, 'handlers');
            $handlers['json']->persistPendingProperties();

            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_name',
            ]))->toBeFalsy();
            expect($this->seeInFile([
                'file' => 'test', 'key' => 'site_email',
            ]))->toBeFalsy();
        });
    });
});
