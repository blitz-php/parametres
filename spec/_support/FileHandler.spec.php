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

                if ($data[$property]['value'] != $expectedValue) {
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
        // $this->cleanDirectory();

        $config             = config('parametres');
        $config['handlers'] = ['file'];
        $config['file']['defer_writes'] = false; // Désactivé par défaut pour les tests d'écriture immédiate

        $this->parametres = new Parametres($config);
    });

    afterEach(function () {
        $this->cleanDirectory();
    });

    xit('Crée le répertoire de stockage s\'il n\'existe pas', function () {
        $tempPath = storage_path('temp_parametres/');

        config()->set('parametres.file.path', $tempPath);

        $config = config('parametres');
        $config['handlers'] = ['file'];

        $this->cleanDirectory($tempPath);

        expect(is_dir($tempPath))->toBeFalsy();

        $parametres = new Parametres($config);

        expect(is_dir($tempPath))->toBeTruthy();

        // Nettoyage
        $this->cleanDirectory($tempPath);
    });

    it('Insert bien les données dans le fichier de stockage', function () {
        $this->parametres->set('test.site_name', 'Foo');

        expect($this->seeInFile('test', null, [
            'site_name' => 'Foo'
        ]))->toBeTruthy();

        expect(file_exists($this->getFilePath('test', null)))->toBeTruthy();
    });

    it('Peut définir une valeur booléenne `true`', function () {
        $this->parametres->set('test.site_name', true);

        expect($this->seeInFile('test', null, [
            'site_name' => 1
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeTruthy();
    });

    it('Peut définir une valeur booléenne `false`', function () {
        $this->parametres->set('test.site_name', false);

        expect($this->seeInFile('test', null, [
            'site_name' => 0
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeFalsy();
    });

    it('Peut définir une valeur à `null`', function () {
        $this->parametres->set('test.site_name', null);

        expect($this->seeInFile('test', null, [
            'site_name' => null
        ]))->toBeTruthy();

        expect($this->parametres->get('test.site_name'))->toBeNull();
    });

    it('Peut insérer un tableau de données', function () {
        $data = ['foo' => 'bar', 'baz' => 123];
        $this->parametres->set('test.site_name', $data);

        $filePath = $this->getFilePath('test', null);
        $storedData = include $filePath;

        expect($storedData['site_name']['value'])->toBe($data);
        expect($storedData['site_name']['type'])->toBe('array');
        expect($this->parametres->get('test.site_name'))->toBe($data);
    });

    it('Peut insérer un objet', function () {
        $data = (object) ['foo' => 'bar'];
        $this->parametres->set('test.site_name', $data);

        $filePath = $this->getFilePath('test', null);
        $storedData = include $filePath;

        expect((array) $storedData['site_name']['value'])->toBe((array) $data);
        expect($storedData['site_name']['type'])->toBe('object');
        expect((array) $this->parametres->get('test.site_name'))->toBe((array) $data);
    });

    it('Peut modifier une entrée existante dans le fichier de stockage', function () {
        $this->parametres->set('test.site_name', 'foo');
        $this->parametres->set('test.site_name', 'Bar');

        expect($this->seeInFile('test', null, [
            'site_name' => 'Bar'
        ]))->toBeTruthy();

        $filePath = $this->getFilePath('test', null);
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
            'site_lang' => 'fr'
        ]))->toBeTruthy();

        expect($this->seeInFile('fake', null, [
            'site_name' => 'foo'
        ]))->toBeTruthy();
    });

    it('Peut fonctionner sans fichier de configuration préexistant', function () {
        $this->parametres->set('nada.site_name', 'Bar');

        expect($this->seeInFile('nada', null, [
            'site_name' => 'Bar'
        ]))->toBeTruthy();

        expect($this->parametres->get('nada.site_name'))->toBe('Bar');
    });

    it('Peut supprimer les données dans le fichier de stockage', function () {
        $this->parametres->set('test.site_name', 'foo');
        $this->parametres->forget('test.site_name');

        expect($this->seeInFile('test', null, [
            'site_name' => 'foo'
        ]))->toBeFalsy();

        $filePath = $this->getFilePath('test', null);
        $storedData = include $filePath;

        expect($storedData)->toBe([]);
    });

    xit('Peut supprimer une donnée même si elle n\'est pas présente', function () {
        $this->parametres->forget('test.site_name');

        expect(file_exists($this->getFilePath('test', null)))->toBeFalsy();
    });

    it('Peut vider toutes les données et continuer à utiliser les données du fichier de configuration', function () {
        expect('Parametres Test')->toBe($this->parametres->get('test.site_name'));

        $this->parametres->set('test.site_name', 'Foo');
        expect('Foo')->toBe($this->parametres->get('test.site_name'));

        $this->parametres->flush();

        expect($this->seeInFile('test', null, [
            'site_name' => 'Foo'
        ]))->toBeFalsy();

        expect('Parametres Test')->toBe($this->parametres->get('test.site_name'));
    });

    it('Peut définir une donnée avec le contexte', function () {
        $this->parametres->set('test.site_name', 'Banana', 'environment:test');

        expect($this->seeInFile('test', 'environment:test', [
            'site_name' => 'Banana'
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
            'site_name' => 'Jane'
        ]))->toBeTruthy();

        expect($this->seeInFile('test', null, [
            'site_name' => 'Humpty'
        ]))->toBeTruthy();

        expect($this->seeInFile('test', 'context:male', [
            'site_name' => 'Jack'
        ]))->toBeTruthy();

        // Vérifier que le contexte female n'a qu'une seule entrée
        $filePath = $this->getFilePath('test', 'context:female');
        $storedData = include $filePath;
        expect(count($storedData))->toBe(1);
    });

    it('Peut supprimer les données d\'un contexte uniquement', function () {
        $this->parametres->set('test.site_name', 'Humpty');
        $this->parametres->set('test.site_name', 'Jack', 'context:male');
        $this->parametres->set('test.site_name', 'Jane', 'context:female');

        $this->parametres->forget('test.site_name', 'context:female');

        expect($this->seeInFile('test', 'context:female', [
            'site_name' => 'Jane'
        ]))->toBeFalsy();

        expect($this->seeInFile('test', null, [
            'site_name' => 'Humpty'
        ]))->toBeTruthy();

        expect($this->seeInFile('test', 'context:male', [
            'site_name' => 'Jack'
        ]))->toBeTruthy();
    });

    it('Charge correctement le contexte général et spécifique', function () {
        $this->parametres->set('test.site_name', 'General');
        $this->parametres->set('test.site_name', 'Specific', 'context:test');

        // Réinitialiser l'instance pour forcer le rechargement
        $config = config('parametres');
        $config['handlers'] = ['file'];
        $newParametres = new Parametres($config);

        expect($newParametres->get('test.site_name'))->toBe('General');
        expect($newParametres->get('test.site_name', 'context:test'))->toBe('Specific');
    });

    describe('Écritures différées', function () {
        beforeEach(function () {
            $this->cleanDirectory();

            $config = config('parametres');
            $config['handlers'] = ['file'];
            $config['file']['defer_writes'] = true;

            $this->parametres = new Parametres($config);
        });

        it('Ne persiste pas immédiatement les données', function () {
            $this->parametres->set('test.site_name', 'Foo');

            // Le fichier ne devrait pas exister immédiatement
            expect(file_exists($this->getFilePath('test', null)))->toBeFalsy();

            // Mais la valeur devrait être accessible en mémoire
            expect($this->parametres->get('test.site_name'))->toBe('Foo');
        });

        it('Persiste les données lors de l\'appel à persistPendingProperties', function () {
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_lang', 'fr');

            // Appel manuel à la persistance
            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            expect($this->seeInFile('test', null, [
                'site_name' => 'Foo',
                'site_lang' => 'fr'
            ]))->toBeTruthy();
        });

        it('Regroupe les modifications multiples avant persistance', function () {
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_name', 'Bar');
            $this->parametres->set('test.site_lang', 'en');

            $handler = $this->getFileHandler();

            // Vérifier que les propriétés en attente sont correctement marquées
            $pendingProperties = $this->getPendingProperties($handler);
            expect(count($pendingProperties))->toBe(2); // site_name et site_lang

            $handler->persistPendingProperties();

            // Seule la dernière valeur de site_name devrait être persistée
            expect($this->seeInFile('test', null, [
                'site_name' => 'Bar',
                'site_lang' => 'en'
            ]))->toBeTruthy();
        });

        it('Regroupe les suppressions avec les modifications', function () {
            // D'abord créer des données
            $this->parametres->set('test.site_name', 'Foo');
            $this->parametres->set('test.site_lang', 'fr');

            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            // Maintenant, modifier et supprimer
            $this->parametres->set('test.site_name', 'Bar');
            $this->parametres->forget('test.site_lang');

            // Vérifier les propriétés en attente
            $pendingProperties = $this->getPendingProperties($handler);
            expect(count($pendingProperties))->toBe(2);

            $handler->persistPendingProperties();

            // Vérifier le résultat final
            expect($this->seeInFile('test', null, [
                'site_name' => 'Bar'
            ]))->toBeTruthy();
            expect($this->seeInFile('test', null, [
                'site_lang' => 'fr'
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
                'site_name' => 'General'
            ]))->toBeTruthy();

            expect($this->seeInFile('test', 'context:test', [
                'site_name' => 'Specific',
                'site_lang' => 'fr'
            ]))->toBeTruthy();
        });

        it('Ne fait rien si aucune propriété en attente', function () {
            $handler = $this->getFileHandler();

            // Cela ne devrait pas lever d'exception
            expect(fn () => $handler->persistPendingProperties())->not->toThrow();
        });

        it('Maintient l\'intégrité des données lors d\'opérations multiples', function () {
            // Effectuer plusieurs opérations
            $this->parametres->set('test.site_name', 'Value1');
            $this->parametres->set('test.site_name', 'Value2');
            $this->parametres->forget('test.site_name');
            $this->parametres->set('test.site_name', 'Value3');

            $handler = $this->getFileHandler();
            $handler->persistPendingProperties();

            // Seule la dernière valeur devrait être persistée
            expect($this->seeInFile('test', null, [
                'site_name' => 'Value3'
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

            $filePath = $this->getFilePath('user', null);
            $storedData = include $filePath;
            expect($storedData['settings']['type'])->toBe('array');
        });

        // Helper pour récupérer le handler FileHandler
        $this->getFileHandler = function () {
            $handlers = ReflectionHelper::getPrivateProperty($this->parametres, 'handlers');
            return $handlers['file'] ?? null;
        };

        // Helper pour récupérer les propriétés en attente
        $this->getPendingProperties = function ($handler) {
            return ReflectionHelper::getPrivateProperty($handler, 'pendingProperties');
        };
    });
});
