<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Parametres\Handlers\ArrayHandler;
use BlitzPHP\Parametres\Handlers\BaseHandler;
use BlitzPHP\Spec\ReflectionHelper;

use function Kahlan\expect;

class FakeHandler extends BaseHandler
{
    /**
     * {@inheritDoc}
     */
    public function has(string $file, string $property, ?string $context = null): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $file, string $property, ?string $context = null): mixed
    {
        return '';
    }
}

describe('Parametres / BaseHandler', function () {
    it('set', function () {
        $handler = new FakeHandler();

        expect(fn () => $handler->set('test', 'site_name', 'category:disease'))->toThrow(new RuntimeException());
    });

    it('forget', function () {
        $handler = new FakeHandler();

        expect(fn () => $handler->forget('test', 'site_name'))->toThrow(new RuntimeException());
    });

    it('flush', function () {
        $handler = new FakeHandler();

        expect(fn () => $handler->flush())->toThrow(new RuntimeException());
    });

    it('persistPendingProperties', function () {
        $handler = new FakeHandler();

        expect(fn () => $handler->persistPendingProperties())->toThrow(new RuntimeException());
    });

    it("setMany retourne une exception si set n'est pas implémenté", function () {
        $handler = new FakeHandler();

        expect(fn () => $handler->setMany([
            ['file' => 'test', 'property' => 'site_name', 'value' => 'Foo'],
        ]))->toThrow(new RuntimeException());
    });

    it("forgetMany retourne une exception si forget n'est pas implémenté", function () {
        $handler = new FakeHandler();

        expect(fn () => $handler->forgetMany([
            ['file' => 'test', 'property' => 'site_name'],
        ]))->toThrow(new RuntimeException());
    });

    it('getMany retourne un tableau vide pour une liste vide', function () {
        $handler = new FakeHandler();

        expect($handler->getMany([]))->toBe([]);
    });

    it('getMany retourne les valeurs demandées', function () {
        $handler = new FakeHandler();

        $results = $handler->getMany([
            ['file' => 'test', 'property' => 'site_name'],
            ['file' => 'test', 'property' => 'site_email'],
        ]);

        expect($results)->toBeA('array');
        expect($results)->toHaveLength(2);
    });

    describe('prepareValue', function () {
        it('convertit les booléens en entier', function () {
            $prepareValue = ReflectionHelper::getPrivateMethodInvoker(new ArrayHandler(), 'prepareValue');

            expect($prepareValue(true))->toBe(1);
            expect($prepareValue(false))->toBe(0);
        });

        it('sérialise les tableaux', function () {
            $prepareValue = ReflectionHelper::getPrivateMethodInvoker(new ArrayHandler(), 'prepareValue');
            $data         = ['foo' => 'bar'];

            expect($prepareValue($data))->toBe(serialize($data));
        });

        it('sérialise les objets', function () {
            $prepareValue = ReflectionHelper::getPrivateMethodInvoker(new ArrayHandler(), 'prepareValue');
            $data         = (object) ['foo' => 'bar'];

            expect($prepareValue($data))->toBe(serialize($data));
        });

        it('laisse les scalaires intacts', function () {
            $prepareValue = ReflectionHelper::getPrivateMethodInvoker(new ArrayHandler(), 'prepareValue');

            expect($prepareValue('foo'))->toBe('foo');
            expect($prepareValue(42))->toBe(42);
            expect($prepareValue(3.14))->toBe(3.14);
            expect($prepareValue(null))->toBeNull();
        });
    });

    describe('parseValue', function () {
        it('désérialise les données sérialisées', function () {
            $parseValue = ReflectionHelper::getPrivateMethodInvoker(new ArrayHandler(), 'parseValue');
            $data       = ['foo' => 'bar'];

            expect($parseValue(serialize($data), 'array'))->toBe($data);
        });

        it('convertit selon le type fourni', function () {
            $parseValue = ReflectionHelper::getPrivateMethodInvoker(new ArrayHandler(), 'parseValue');

            expect($parseValue('1', 'boolean'))->toBe(true);
            expect($parseValue('0', 'boolean'))->toBe(false);
            expect($parseValue('42', 'integer'))->toBe(42);
            expect($parseValue('3.14', 'double'))->toBe(3.14);
        });
    });

    describe('isSerialized', function () {
        it('détecte les chaînes sérialisées', function () {
            $isSerialized = ReflectionHelper::getPrivateMethodInvoker(new ArrayHandler(), 'isSerialized');

            expect($isSerialized(serialize(['foo' => 'bar'])))->toBeTruthy();
            expect($isSerialized(serialize('foo')))->toBeTruthy();
            expect($isSerialized(serialize(42)))->toBeTruthy();
            expect($isSerialized('N;'))->toBeTruthy();
        });

        it('rejette les chaînes non sérialisées', function () {
            $isSerialized = ReflectionHelper::getPrivateMethodInvoker(new ArrayHandler(), 'isSerialized');

            expect($isSerialized('foo'))->toBeFalsy();
            expect($isSerialized(''))->toBeFalsy();
            expect($isSerialized(42))->toBeFalsy();
            expect($isSerialized(null))->toBeFalsy();
            expect($isSerialized(['foo' => 'bar']))->toBeFalsy();
        });
    });
});
