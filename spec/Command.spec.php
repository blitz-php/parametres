<?php

use function Kahlan\expect;

describe('Parametres / Command', function() {
	it("La commande `parametres:clear` fonctionne", function() {
		$parametres = service('parametres');

		$parametres->set('foo.site_name', 'Humpty');
		$parametres->set('foo.site_name', 'Jack', 'context:male');
		$parametres->set('foo.site_name', 'Jill', 'context:female');
		$parametres->set('foo.site_name', 'Jane', 'context:female');

		expect($parametres->get('foo.site_name'))->toBe('Humpty');
		expect($parametres->get('foo.site_name', 'context:male'))->toBe('Jack');
		expect($parametres->get('foo.site_name', 'context:female'))->toBe('Jane');

		command('parametres:clear --yes');

		expect($parametres->get('foo.site_name'))->toBeNull();
		expect($parametres->get('foo.site_name', 'context:male'))->toBeNull();
		expect($parametres->get('foo.site_name', 'context:female'))->toBeNull();
    });

	it("Publisher", function() {
		config()->ghost('publisher')->set('publisher.restrictions', [ROOTPATH => '*']);

		$path = CONFIG_PATH . 'parametres.php';

		expect(file_exists($path))->toBeFalsy();

		// conserver les fichiers originaux car a la fin, on suppimera tous les fichiers publiés
		$original_files = array_map(fn($f) => $f->getRelativePathname(), service('fs')->files(CONFIG_PATH));

		command('publish');
		// command('publish --namespace=BlitzPHP\\\\Parametres');

		expect(file_exists($path))->toBeTruthy();

		$content = file_get_contents($path);
		expect(str_contains($content, 'Paramètres du gestionnaire "Database".'))->toBeTruthy();

		foreach (service('fs')->files(CONFIG_PATH) as $f) {
			/** @var \Symfony\Component\Finder\SplFileInfo $f */
			if (!in_array($f->getRelativePathname(), $original_files)) {
				@unlink($f->getPathname());
			}
		}

		expect(file_exists($path))->toBeFalsy();
	});
});
