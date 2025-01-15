<?php

use BlitzPHP\Parametres\Parametres;
use BlitzPHP\Utilities\Date;

use function Kahlan\expect;

describe('Parametres / DatabaseHandler', function() {
	beforeAll(function() {
		@unlink(STORAGE_PATH . 'database.sqlite');

		config()->ghost('migrations')->set('migrations', [
			'enabled'         => true,
			'table'           => 'migrations',
			'timestampFormat' => 'Y-m-d-His_',
		]);
		config()->ghost('database')->set('database', [
			'connection' => 'sqlite',
			'other' => [
				'driver' => 'pdosqlite',
				'database' => STORAGE_PATH . 'database.sqlite',
			],
			'sqlite' => [
				'driver' => 'pdosqlite',
				'database' => STORAGE_PATH . 'database.sqlite',
			],
		]);

		$this->db = service('database');

		command('migrate --namespace=BlitzPHP\\\\Parametres');

		$this->seeInDatabase = function(string $table, array $where) {
			$whereNull = array_filter($where, fn($v) => $v === null);
			$where     = array_diff($where, $whereNull);

			$builder = $this->db->table($table);

			if ($where !== []) {
				$builder->where($where);
			}
			if ($whereNull !== []) {
				$builder->whereNull(array_keys($whereNull));
			}

			return $builder->count() > 0;
		};
	});

	afterAll(function() {
		command('migrate:rollback');
		$this->db->close();
		@unlink(STORAGE_PATH . 'database.sqlite');

    });

	beforeEach(function() {
		$config             = config('parametres');
		$config['handlers'] = ['database'];

        $this->parametres = new Parametres($config);
        $this->table      = $config['database']['table'];
        $this->group      = $config['database']['group'];
	});

	it("Insert bien les donnees en bd", function() {
		 $this->parametres->set('test.site_name', 'Foo');

        expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
            'value' => 'Foo',
            'type'  => 'string',
        ]))->toBeTruthy();
    });

	it("Groupe invalide", function() {
		$config                      = config('parametres');
		$config['handlers']          = ['database'];
		$config['database']['group'] = 'another';

		expect(function() use ($config) {
			$param = new Parametres($config);
	        $param->set('test.site_name', true);
		})->toThrow(new InvalidArgumentException());
    });

	it("Modifie le groupe par defaut", function() {
		config()->set('parametres.database.group', 'other');

		$this->parametres->set('test.site_name', true);

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
            'value' => '1',
            'type'  => 'boolean',
        ]))->toBeTruthy();

		expect($this->parametres->get('test.site_name'))->toBeTruthy();
    });

	it("Peut definir une valeur booleenne `true`", function() {
		$this->parametres->set('test.site_name', true);

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
            'value' => '1',
            'type'  => 'boolean',
        ]))->toBeTruthy();

		expect($this->parametres->get('test.site_name'))->toBeTruthy();
    });

	it("Peut definir une valeur booleenne `false`", function() {
		$this->parametres->set('test.site_name', false);

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
            'value' => '0',
            'type'  => 'boolean',
        ]))->toBeTruthy();

		expect($this->parametres->get('test.site_name'))->toBeFalsy();
    });

	it("Peut definir une valeur à `null`", function() {
		$this->parametres->set('test.site_name', null);

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
            'value' => null,
            'type'  => 'NULL',
        ]))->toBeTruthy();

		expect($this->parametres->get('test.site_name'))->toBeNull();
    });

	it("Peut inserer un tableau de donnees", function() {
		$data = ['foo' => 'bar'];
		$this->parametres->set('test.site_name', $data);

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
            'value' => serialize($data),
            'type'  => 'array',
        ]))->toBeTruthy();

		expect($this->parametres->get('test.site_name'))->toBe($data);
    });

	it("Peut inserer un object", function() {
		$data = (object) ['foo' => 'bar'];
		$this->parametres->set('test.site_name', $data);

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
            'value' => serialize($data),
            'type'  => 'object',
        ]))->toBeTruthy();

		expect((array) $this->parametres->get('test.site_name'))->toBe((array) $data);
    });

	it("Peut modifier une entree existante en db", function() {
		$this->db->table($this->table)->insert([
			'file'       => 'test',
			'key'        => 'site_name',
			'value'      => 'foo',
			'created_at' => Date::now()->format('Y-m-d H:i:s'),
			'updated_at' => Date::now()->format('Y-m-d H:i:s'),
		]);

		$this->parametres->set('test.site_name','Bar');

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
            'value' => 'Bar',
        ]))->toBeTruthy();
    });

	it("Peut fonctionner sans le fichier de configuration", function() {
		$this->parametres->set('nada.site_name', 'Bar');

		expect($this->seeInDatabase($this->table, [
            'file' => 'nada',
            'key'   => 'site_name',
            'value' => 'Bar',
        ]))->toBeTruthy();

		expect($this->parametres->get('nada.site_name'))->toBe('Bar');
    });

	it("Peut supprimer les donnees en db", function() {
		$this->db->table($this->table)->insert([
			'file'       => 'test',
			'key'        => 'site_name',
			'value'      => 'foo',
			'created_at' => Date::now()->format('Y-m-d H:i:s'),
			'updated_at' => Date::now()->format('Y-m-d H:i:s'),
		]);

		$this->parametres->forget('test.site_name');

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
        ]))->toBeFalsy();
    });

	it("Peut supprimer une donnee meme si elle n'est pas deja presente en db", function() {
		$this->parametres->forget('test.site_name');

		expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
        ]))->toBeFalsy();
    });

	it("Peut vider toutes les donnees en db, et continuer a utiliser les donnees du fichier de configuration", function() {
		// Valeur par defaut issue du fichier de config
        expect('Parametres Test')->toBe($this->parametres->get('test.site_name'));

        $this->parametres->set('test.site_name', 'Foo');

        // Doit etre la derniere valeur definie
        expect('Foo')->toBe($this->parametres->get('test.site_name'));

        $this->parametres->flush();

        expect($this->seeInDatabase($this->table, [
            'file' => 'test',
            'key'   => 'site_name',
		]))->toBeFalsy();

		// Doit rentrer à la valeur par défaut
		expect('Parametres Test')->toBe($this->parametres->get('test.site_name'));
    });

	it("Peut definir une donnee avec le contexte", function() {
		$this->parametres->set('test.site_name','Banana', 'environment:test');

		expect($this->seeInDatabase($this->table, [
			'file'    => 'test',
			'key'     => 'site_name',
			'value'   => 'Banana',
			'type'    => 'string',
			'context' => 'environment:test',
        ]))->toBeTruthy();
    });

	it("Peut modifier les donnees d'un context uniquement", function() {
		$this->parametres->set('test.site_name', 'Humpty');
        $this->parametres->set('test.site_name', 'Jack', 'context:male');
        $this->parametres->set('test.site_name', 'Jill', 'context:female');
        $this->parametres->set('test.site_name', 'Jane', 'context:female');

		expect($this->seeInDatabase($this->table, [
			'file'    => 'test',
			'key'     => 'site_name',
            'value'   => 'Jane',
            'type'    => 'string',
            'context' => 'context:female',
        ]))->toBeTruthy();

		expect($this->seeInDatabase($this->table, [
			'file'    => 'test',
			'key'     => 'site_name',
            'value'   => 'Humpty',
            'type'    => 'string',
            'context' => null,
        ]))->toBeTruthy();

		expect($this->seeInDatabase($this->table, [
			'file'    => 'test',
			'key'     => 'site_name',
            'value'   => 'Jack',
            'type'    => 'string',
            'context' => 'context:male',
        ]))->toBeTruthy();
    });

	it("Peut supprimer les donnees d'un context uniquement", function() {
		$this->parametres->set('test.site_name', 'Humpty');
        $this->parametres->set('test.site_name', 'Jack', 'context:male');
        $this->parametres->set('test.site_name', 'Jane', 'context:female');

		$this->parametres->forget('test.site_name', 'context:female');

		expect($this->seeInDatabase($this->table, [
			'file'    => 'test',
			'key'     => 'site_name',
            'context' => 'context:female',
        ]))->toBeFalsy();

		expect($this->seeInDatabase($this->table, [
			'file'    => 'test',
			'key'     => 'site_name',
            'value'   => 'Humpty',
            'type'    => 'string',
            'context' => null,
        ]))->toBeTruthy();

		expect($this->seeInDatabase($this->table, [
			'file'    => 'test',
			'key'     => 'site_name',
            'value'   => 'Jack',
            'type'    => 'string',
            'context' => 'context:male',
        ]))->toBeTruthy();
    });
});
