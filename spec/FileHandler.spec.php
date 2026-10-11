<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Parametres\Parametres;
use BlitzPHP\Spec\ReflectionHelper;

use function Kahlan\expect;

describe('Parametres / FileHandler', function () {
    beforeAll(function () {
        config()->set('parametres.file.path', $path = storage_path('parametres/'));
        $this->path = $path;

        $this->seeInFile = function (string $file, ?string $context = null, array $where = []) {
            $filePath = $this->getFilePath($file, $context);

            if (! file_exists($filePath)) {
                return false;
            }

            $data = include $filePath;

            if (! is_array($data)) {
                return false;
            }

            foreach ($where as $property => $expectedValue) {
                if (! isset($data[$property])) {
                    return false;
                }

                if ($data[$property]['value'] !== $expectedValue) {
                    return false;
                }
            }

            return true;
        };

        $this->getFilePath = function (string $file, ?string $context = null): string {
            if ($context === null) {
                return $this->path . $file . '.php';
            }

            $contextHash = hash('xxh128', $context);

            return $this->path . $contextHash . DIRECTORY_SEPARATOR . $file . '.php';
        };

        $this->cleanDirectory = function (?string $dir = null) {
            $dir ??= $this->path;

            if (is_dir($dir)) {
                $files = glob($dir . '*.php');
                if ($files !== false) {
                    foreach ($files as $file) {
                        @unlink($file);
                    }
                }

                $directories = glob($dir . '*', GLOB_ONLYDIR);
                if ($directories !== false) {
                    foreach ($directories as $directory) {
                        $this->cleanDirectory($directory . '/');
                    }
                }

                @rmdir($dir);
            }
        };
    });

    beforeEach(function () {
        $config                         = config('parametres');
        $config['handlers']             = ['file'];
        $config['file']['defer_writes'] = false; // Désactivé par défaut pour les tests d'écriture immédiate

        $this->parametres = new Parametres($config);
    });

    afterEach(function () {
        $this->cleanDirectory();
    });

    xit('Crée le répertoire de stockage s\'il n\'existe pas', function () {
        $tempPath = storage_path('temp_parametres/');

        config()->set('parametres.file.path', $tempPath);

        $config             = config('parametres');
        $config['handlers'] = ['file'];

        $this->cleanDirectory($tempPath);

        expect(is_dir($tempPath))->toBeFalsy();

        $parametres = new Parametres($config);

        expect(is_dir($tempPath))->toBeTruthy();

        $this->cleanDirectory($tempPath);
    });

    it('Insert bien les données dans le fichier de stockage', function () {
        $this->parametres->set('test.site_name', 'Foo');

        expect($this->seeInFile('test', null, [
            'site_name' => 'Foo',
        ]))->toBeTruthy();

        expect(file_exists($this->getFilePath('test', null)))->toBeTruthy();
    });

    it('Peut définir une valeur booléenne `true`', function () {
        $this->parametres->set('test.site_name', true);

        expect($this->seeInFile('test', null, [
            'site_name' => true,
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeTruthy();
    });

    it('Peut définir une valeur booléenne `false`', function () {
        $this->parametres->set('test.site_name', false);

        expect($this->seeInFile('test', null, [
            'site_name' => false,
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeFalsy();
    });

    it('Peut définir une valeur à `null`', function () {
        $this->parametres->set('test.site_name', null);

        expect($this->seeInFile('test', null, [
            'site_name' => null,
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeNull();
    });

    it('Peut insérer un tableau de données', function () {
        $data = ['foo' => 'bar', 'baz' => 123];
        $this->parametres->set('test.site_name', $data);

        $filePath   = $this->getFilePath('test', null);
        $storedData = include $filePath;

        expect($storedData['site_name']['value'])->toBe($data);
        expect($storedData['site_name']['type'])->toBe('array');
        expect($this->parametres->get('test.site_name'))->toBe($data);
    });

    it('Peut insérer un objet', function () {
        $data = (object) ['foo' => 'bar'];
        $this->parametres->set('test.site_name', $data);

        $filePath   = $this->getFilePath('test', null);
        $storedData = include $filePath;

        expect((array) $storedData['site_name']['value'])->toBe((array) $data);
        expect($storedData['site_name']['type'])->toBe('object');
        expect((array) $this->parametres->get('test.site_name'))->toBe((array) $data);
    });

    it('Peut modifier une entrée existante dans le fichier de stockage', function () {
        $this->parametres->set('test.site_name', 'foo');
        $this->parametres->set('test.site_name', 'Bar');

        expect($this->seeInFile('test', null, [
            'site_name' => 'Bar',
        ]))->toBeTruthy();

        $filePath   = $this->getFilePath('test', null);
        $storedData = include $filePath;

        expect(count($storedData))->toBe(1);
    });

    it('Peut modifier une entrée existante et laisser les autres intactes', function () {
        $this->parametres->set('test.site_name', 'foo');
        $this->parametres->set('test.site_lang', 'fr');
        $this->parametres->set('fake.site_name', 'foo');

        $this->parametres->set('test.site_name', 'Bar');

        expect($this->seeInFile('test', null, [
            'site_name' => 'Bar',
            'site_lang' => 'fr',
        ]))->toBeTruthy();

        expect($this->seeInFile('fake', null, [
            'site_name' => 'foo',
        ]))->toBeTruthy();
    });

    it('Peut fonctionner sans fichier de configuration préexistant', function () {
        $this->parametres->set('nada.site_name', 'Bar');

        expect($this->seeInFile('nada', null, [
            'site_name' => 'Bar',
        ]))->toBeTruthy();

        expect($this->parametres->get('nada.site_name'))->toBe('Bar');
    });

    it('Peut supprimer les données dans le fichier de stockage', function () {
        $this->parametres->set('test.site_name', 'foo');
        $this->parametres->forget('test.site_name');

        expect($this->seeInFile('test', null, [
            'site_name' => 'foo',
        ]))->toBeFalsy();

        $filePath   = $this->getFilePath('test', null);
        $storedData = include $filePath;

        expect($storedData)->toBe([]);
    });

    it('Peut supprimer une donnée même si elle n\'est pas présente', function () {
        $this->parametres->forget('test.site_name');

        expect($this->seeInFile('test', null, [
            'site_name' => 'foo',
        ]))->toBeFalsy();
    });

    it('Peut vider toutes les données et continuer à utiliser les données du fichier de configuration', function () {
        expect('Parametres Test')->toBe($this->parametres->get('test.site_name'));

        $this->parametres->set('test.site_name', 'Foo');
        expect('Foo')->toBe($this->parametres->get('test.site_name'));

        $this->parametres->flush();

        expect($this->seeInFile('test', null, [
            'site_name' => 'Foo',
        ]))->toBeFalsy();

        expect('Parametres Test')->toBe($this->parametres->get('test.site_name'));
    });

    it('Peut définir une donnée avec le contexte', function () {
        $this->parametres->set('test.site_name', 'Banana', 'environment:test');

        expect($this->seeInFile('test', 'environment:test', [
            'site_name' => 'Banana',
        ]))->toBeTruthy();

        $contextPath = $this->getFilePath('test', 'environment:test');
        expect(file_exists($contextPath))->toBeTruthy();
    });

    it('Peut modifier les données d\'un contexte uniquement', function () {
        $this->parametres->set('test.site_name', 'Humpty');
        $this->parametres->set('test.site_name', 'Jack', 'context:male');
        $this->parametres->set('test.site_name', 'Jill', 'context:female');
        $this->parametres->set('test.site_name', 'Jane', 'context:female');

        expect($this->seeInFile('test', 'context:female', [
            'site_name' => 'Jane',
        ]))->toBeTruthy();

        expect($this->seeInFile('test', null, [
            'site_name' => 'Humpty',
        ]))->toBeTruthy();

        expect($this->seeInFile('test', 'context:male', [
            'site_name' => 'Jack',
        ]))->toBeTruthy();

        $filePath   = $this->getFilePath('test', 'context:female');
        $storedData = include $filePath;
        expect(count($storedData))->toBe(1);
    });

    it('Peut supprimer les données d\'un contexte uniquement', function () {
        $this->parametres->set('test.site_name', 'Humpty');
        $this->parametres->set('test.site_name', 'Jack', 'context:male');
        $this->parametres->set('test.site_name', 'Jane', 'context:female');

        $this->parametres->forget('test.site_name', 'context:female');

        expect($this->seeInFile('test', 'context:female', [
            'site_name' => 'Jane',
        ]))->toBeFalsy();

        expect($this->seeInFile('test', null, [
            'site_name' => 'Humpty',
        ]))->toBeTruthy();

        expect($this->seeInFile('test', 'context:male', [
            'site_name' => 'Jack',
        ]))->toBeTruthy();
    });

    it('Charge correctement le contexte général et spécifique', function () {
        $this->parametres->set('test.site_name', 'General');
        $this->parametres->set('test.site_name', 'Specific', 'context:test');

        $config             = config('parametres');
        $config['handlers'] = ['file'];
        $newParametres      = new Parametres($config);

        expect($newParametres->get('test.site_name'))->toBe('General');
        expect($newParametres->get('test.site_name', 'context:test'))->toBe('Specific');
    });

    it('Le contexte vide est différent du contexte null', function () {
        $this->parametres->set('test.site_name', 'Null', null);
        $this->parametres->set('test.site_name', 'Empty', '');

        $nullFile = $this->getFilePath('test', null);
        expect(file_exists($nullFile))->toBeTruthy();

        $emptyHash = hash('xxh128', '');
        $emptyFile = $this->path . $emptyHash . DIRECTORY_SEPARATOR . 'test.php';
        expect(file_exists($emptyFile))->toBeTruthy();

        expect($this->parametres->get('test.site_name', null))->toBe('Null');
        expect($this->parametres->get('test.site_name', ''))->toBe('Empty');
    });

    it('Le contexte utilise un sous-répertoire hashé', function () {
        $context = 'environment:production';
        $this->parametres->set('test.site_name', 'Prod', $context);

        $contextHash = hash('xxh128', $context);
        $expected    = $this->path . $contextHash . DIRECTORY_SEPARATOR . 'test.php';

        expect(file_exists($expected))->toBeTruthy();
    });

    it('Les handlers exposent has()', function () {
        $this->parametres->set('test.site_name', 'Test');

        $handlers = ReflectionHelper::getPrivateProperty($this->parametres, 'handlers');
        $handler  = $handlers['file'];

        expect($handler->has('test', 'site_name'))->toBeTruthy();
        expect($handler->has('test', 'non_existent'))->toBeFalsy();
    });

    it('Fusionne les modifications de différents processus', function () {
        // Processus A écrit site_name
        $this->parametres->set('test.site_name', 'First');

        $filePath = $this->getFilePath('test', null);
        expect(file_exists($filePath))->toBeTruthy();

        // Simule Processus B qui écrit site_email (ignore l'état en mémoire)
        $data               = include $filePath;
        $data['site_email'] = ['value' => 'concurrent@example.com', 'type' => 'string'];
        $content            = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        file_put_contents($filePath, $content);

        // Processus A écrit site_title (ne connaît pas site_email en mémoire)
        // La logique de merge doit préserver les propriétés existantes sur disque
        $this->parametres->set('test.site_title', 'Second');

        // Rechargement depuis le disque
        $config             = config('parametres');
        $config['handlers'] = ['file'];
        $newParametres      = new Parametres($config);

        expect($newParametres->get('test.site_name'))->toBe('First');
        expect($newParametres->get('test.site_email'))->toBe('concurrent@example.com');
        expect($newParametres->get('test.site_title'))->toBe('Second');
    });

    describe('setMany', function () {
        it('Insère plusieurs données dans le même fichier', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'test.site_email' => 'batch@example.com',
                'test.site_title' => 'BatchTitle',
            ]);

            expect($this->seeInFile('test', null, [
                'site_name'  => 'BatchName',
                'site_email' => 'batch@example.com',
                'site_title' => 'BatchTitle',
            ]))->toBeTruthy();

            $files = glob($this->path . '*.php', GLOB_NOSORT);
            expect(count($files))->toBe(1);
        });

        it('Insère plusieurs données dans différents fichiers', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'nada.site_name' => 'NadaName',
            ]);

            expect($this->seeInFile('test', null, ['site_name' => 'BatchName']))->toBeTruthy();
            expect($this->seeInFile('nada', null, ['site_name' => 'NadaName']))->toBeTruthy();

            $files = glob($this->path . '*.php', GLOB_NOSORT);
            expect(count($files))->toBe(2);
        });

        it('Insère plusieurs données avec le contexte', function () {
            $this->parametres->setMany([
                'test.site_name' => 'ContextName',
                'test.site_email' => 'context@example.com',
            ], 'environment:production');

            expect($this->seeInFile('test', 'environment:production', [
                'site_name'  => 'ContextName',
                'site_email' => 'context@example.com',
            ]))->toBeTruthy();
        });

        it('Accepte un tableau vide', function () {
            $this->parametres->set('test.site_name', 'ExistingName');
            $this->parametres->setMany([]);

            expect($this->parametres->get('test.site_name'))->toBe('ExistingName');
        });
    });

    describe('forgetMany', function () {
        it('Supprime plusieurs données dans le même fichier', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'test.site_email' => 'batch@example.com',
                'test.site_title' => 'BatchTitle',
            ]);

            $this->parametres->forgetMany(['test.site_name', 'test.site_email']);

            expect($this->seeInFile('test', null, [
                'site_name' => 'BatchName',
            ]))->toBeFalsy();

            expect($this->seeInFile('test', null, [
                'site_title' => 'BatchTitle',
            ]))->toBeTruthy();
        });

        it('Supprime plusieurs données pour différents fichiers', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'nada.site_name' => 'NadaName',
            ]);

            $this->parametres->forgetMany(['test.site_name', 'nada.site_name']);

            expect($this->seeInFile('test', null, ['site_name' => 'BatchName']))->toBeFalsy();
            expect($this->seeInFile('nada', null, ['site_name' => 'NadaName']))->toBeFalsy();
        });

        it('Accepte un tableau vide', function () {
            $this->parametres->set('test.site_name', 'ExistingName');
            $this->parametres->forgetMany([]);

            expect($this->parametres->get('test.site_name'))->toBe('ExistingName');
        });
    });

    describe('getMany', function () {
        it('Récupère plusieurs valeurs', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'test.site_email' => 'batch@example.com',
            ]);

            $results = $this->parametres->getMany(['test.site_name', 'test.site_email']);

            expect($results['test.site_name'])->toBe('BatchName');
            expect($results['test.site_email'])->toBe('batch@example.com');
        });

        it('Retourne un tableau vide pour une liste vide', function () {
            expect($this->parametres->getMany([]))->toBe([]);
        });

        it('Retourne null pour les clés inexistantes', function () {
            $results = $this->parametres->getMany(['nada.site_name']);

            expect($results['nada.site_name'])->toBeNull();
        });
    });

    describe('Écritures différées', function () {
        beforeEach(function () {
            $this->cleanDirectory();

            $config                         = config('parametres');
            $config['handlers']             = ['file'];
            $config['file']['defer_writes'] = true;

            $this->parametres = new Parametres($config);

            $this->getFileHandler = function () {
                $handlers = ReflectionHelper::getPrivateProperty($this->parametres, 'handlers');

                return $handlers['file'] ?? null;
            };

            $this->getPendingProperties = fn ($handler) => ReflectionHelper::getPrivateProperty($handler, 'pendingProperties');
        });

        it('Ne persiste pas immédiatement les données', function () {
            $this->parametres->set('test.site_name', 'Foo');

            expect(file_exists($this->getFilePath('test', null)))->toBeFalsy();
            expect($this->parametres->get('test.site_name'))->toBe('Foo');
        });

        it('Persiste les données lors de l\'appel à persistPendingProperties', function () {
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_lang', 'fr');

            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name' => 'Foo',
                'site_lang' => 'fr',
            ]))->toBeTruthy();
        });

        it('Regroupe les modifications multiples avant persistance', function () {
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_name', 'Bar');
            $this->parametres->set('test.site_lang', 'en');

            $handler = $this->getFileHandler();

            $pendingProperties = $this->getPendingProperties($handler);
            expect(count($pendingProperties))->toBe(2);

            $handler->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name' => 'Bar',
                'site_lang' => 'en',
            ]))->toBeTruthy();
        });

        it('Regroupe les suppressions avec les modifications', function () {
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_lang', 'fr');

            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            $this->parametres->set('test.site_name', 'Bar');
            $this->parametres->forget('test.site_lang');

            $pendingProperties = $this->getPendingProperties($handler);
            expect(count($pendingProperties))->toBe(2);

            $handler->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name' => 'Bar',
            ]))->toBeTruthy();
            expect($this->seeInFile('test', null, [
                'site_lang' => 'fr',
            ]))->toBeFalsy();
        });

        it('Gère correctement les modifications avec contextes différés', function () {
            $this->parametres->set('test.site_name', 'General');
            $this->parametres->set('test.site_name', 'Specific', 'context:test');
            $this->parametres->set('test.site_lang', 'fr', 'context:test');

            $handler = $this->getFileHandler();

            $pendingProperties = $this->getPendingProperties($handler);
            expect(count($pendingProperties))->toBe(3);

            $handler->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name' => 'General',
            ]))->toBeTruthy();

            expect($this->seeInFile('test', 'context:test', [
                'site_name' => 'Specific',
                'site_lang' => 'fr',
            ]))->toBeTruthy();
        });

        it('Ne fait rien si aucune propriété en attente', function () {
            $handler = $this->getFileHandler();

            expect(fn () => $handler->persistPendingProperties())->not->toThrow();
        });

        it('Maintient l\'intégrité des données lors d\'opérations multiples', function () {
            $this->parametres->set('test.site_name', 'Value1');
            $this->parametres->set('test.site_name', 'Value2');
            $this->parametres->forget('test.site_name');
            $this->parametres->set('test.site_name', 'Value3');

            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name' => 'Value3',
            ]))->toBeTruthy();
        });

        it('Fonctionne avec plusieurs fichiers différents', function () {
            $this->parametres->set('test.site_name', 'Test Value');
            $this->parametres->set('app.name', 'App Value');
            $this->parametres->set('user.settings', ['theme' => 'dark']);

            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            expect($this->seeInFile('test', null, ['site_name' => 'Test Value']))->toBeTruthy();
            expect($this->seeInFile('app', null, ['name' => 'App Value']))->toBeTruthy();

            $filePath   = $this->getFilePath('user', null);
            $storedData = include $filePath;
            expect($storedData['settings']['type'])->toBe('array');
        });

        it('Suppression puis écriture différée : seule la dernière opération compte', function () {
            $this->parametres->set('test.site_name', 'InitialValue');

            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            // Nouvelle instance en mode différé
            $config                         = config('parametres');
            $config['handlers']             = ['file'];
            $config['file']['defer_writes'] = true;
            $deferred                       = new Parametres($config);

            $deferred->forget('test.site_name');
            $deferred->set('test.site_name', 'NewValue');

            expect($this->seeInFile('test', null, [
                'site_name' => 'InitialValue',
            ]))->toBeTruthy();

            $handlers = ReflectionHelper::getPrivateProperty($deferred, 'handlers');
            $handlers['file']->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name' => 'NewValue',
            ]))->toBeTruthy();
        });

        it('setMany en mode différé ne persiste rien avant persistPendingProperties', function () {
            $this->parametres->setMany([
                'test.site_name' => 'DeferredName',
                'test.site_email' => 'deferred@example.com',
            ]);

            expect(file_exists($this->getFilePath('test', null)))->toBeFalsy();

            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name'  => 'DeferredName',
                'site_email' => 'deferred@example.com',
            ]))->toBeTruthy();
        });

        xit('forgetMany en mode différé supprime après persistPendingProperties', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'test.site_email' => 'batch@example.com',
            ]);

            $config                         = config('parametres');
            $config['handlers']             = ['file'];
            $config['file']['defer_writes'] = true;
            $deferred                       = new Parametres($config);

            $deferred->forgetMany(['test.site_name', 'test.site_email']);

            expect($this->seeInFile('test', null, [
                'site_name' => 'BatchName',
            ]))->toBeTruthy();

            $handlers = ReflectionHelper::getPrivateProperty($deferred, 'handlers');
            $handlers['file']->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name' => 'BatchName',
            ]))->toBeFalsy();
            expect($this->seeInFile('test', null, [
                'site_email' => 'batch@example.com',
            ]))->toBeFalsy();
        });
    });
});
