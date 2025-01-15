<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Parametres\Handlers\BaseHandler;

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
});
