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
use BlitzPHP\Utilities\Date;
use BlitzPHP\Utilities\Iterable\Arr;

use function Kahlan\expect;

describe('Parametres / FileHandler', function () {
    beforeAll(function () {
        config()->set('parametres.file.path', $path = storage_path('.parametres.json'));
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
        $config['handlers'] = ['file'];

        $this->parametres = new Parametres($config);
    });

    afterEach(function () {
        @unlink($this->path);
    });

    it('Lève une exception si le chemin d\'accès du fichier de stockage n\'est pas specifié', function () {
        $config                 = config('parametres');
        $config['handlers']     = ['file'];
        $config['file']['path'] = '';

        expect(fn () => new Parametres($config))->toThrow(ParametresException::fileForStorageNotDefined());
    });

    it('Lève une exception si le dossier du fichier de stockage n\'existe pas', function () {
        $config                 = config('parametres');
        $config['handlers']     = ['file'];
        $config['file']['path'] = $path = __DIR__ . '/app/parametres.json';

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

	describe('Recuperation recursive', function () {
		it('Recuperation recursive des configurations', function () {
			config()->ghost('auth')->set('auth', [
				'session' => $expected = [
					'field'             => 'user',
					'allow_remembering' => true,
					'depth' => [
						'field'             => 'id',
                        'allow_remembering' => false,
                        'depth' => null, // Cas particulier pour le dernier niveau
					]
				],
			]);

			expect(parametre('auth.session'))->toBe($expected);
			expect(parametre('auth.session.field'))->toBe($expected['field']);
			expect(parametre('auth.session.allow_remembering'))->toBeTruthy();
			expect(parametre('auth.session.depth.field'))->toBe('id');
			expect(parametre('auth.session.depth.allow_remembering'))->toBeFalsy();
		});

		it('Recuperation recursive des parametres du fichier json', function () {
			parametre()->set('auth.session', $expected = [
				'field'             => 'user',
				'allow_remembering' => true,
				'depth' => [
					'field'             => 'id',
					'allow_remembering' => false,
					'depth' => null, // Cas particulier pour le dernier niveau
				]
			]);

			expect(parametre('auth.session'))->toBe($expected);
			expect(parametre('auth.session.field'))->toBe($expected['field']);
			expect(parametre('auth.session.allow_remembering'))->toBeTruthy();
			expect(parametre('auth.session.depth.field'))->toBe('id');
			expect(parametre('auth.session.depth.allow_remembering'))->toBeFalsy();
		});
	});
});
