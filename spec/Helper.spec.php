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

use function Kahlan\expect;

describe('Parametres / Helper', function () {
    it('Le helper renvoi le service', function () {
        expect(parametre())->toBeAnInstanceOf(Parametres::class);
    });

    it("Leve une exception si la cle n'est pas au bon format", function () {
        expect(fn () => parametre('foobar'))
            ->toThrow(new InvalidArgumentException());
    });

    it('Defini une valeur null', function () {
        parametre('foo.bam', null);

        expect(service('parametres')->get('foo.bam'))->toBeNull();
        expect(parametre('foo.bam'))->toBeNull();
    });

    it('Retourne la bonne valeur via la notation DotAccess', function () {
        service('parametres')->set('foo.bar', 'baz');

        expect('baz')->toBe(parametre('foo.bar'));
    });

    it('Defini une valeur via la notation DotAccess', function () {
        service('parametres')->set('foo.bar', 'baz');

        parametre('foo.bar', false);

        expect(service('parametres')->get('foo.bar'))->toBeFalsy();
        expect(parametre('foo.bar'))->toBeFalsy();
    });
});
