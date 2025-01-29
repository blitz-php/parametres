<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Parametres\Config\Services;
use BlitzPHP\Parametres\Parametres;
use BlitzPHP\Spec\ReflectionHelper;

use function Kahlan\expect;

describe('Parametres / Parametres', function () {
    beforeEach(function () {
        config()->reset('parametres');
        config()->set('parametres.handlers', ['array']);

        $this->parametres = new Parametres(config('parametres'));
    });

    it('Utilisation directe de la classe Parametres', function () {
        $config             = config('parametres');
        $config['handlers'] = [];

        $parametres = new Parametres($config);
        $result     = ReflectionHelper::getPrivateProperty($parametres, 'handlers');

        expect($result)->toBe([]);
    });

    it('Utilisation du service', function () {
        Services::resetSingle(Parametres::class);

        config()->set('parametres.handlers', []);

        $parametres = service('parametres');
        $result     = ReflectionHelper::getPrivateProperty($parametres, 'handlers');

        expect($result)->toBe([]);
    });

    it('Recuperation a partir des config', function () {
        expect(config('test.site_name'))->toBe($this->parametres->get('test.site_name'));
    });

    it('Recuperation avec le context', function () {
        $this->parametres->set('test.site_name', 'NoContext');
        $this->parametres->set('test.site_name', 'YesContext', 'testing:true');

        expect('NoContext')->toBe($this->parametres->get('test.site_name'));
        expect('YesContext')->toBe($this->parametres->get('test.site_name', 'testing:true'));
    });

    it('Recuperation sans le context', function () {
        $this->parametres->set('test.site_name', 'NoContext');

        expect('NoContext')->toBe($this->parametres->get('test.site_name', 'testing:true'));
    });

    it('Forget avec le context', function () {
        $this->parametres->set('test.site_name', 'Bar');
        $this->parametres->set('test.site_name', 'Amnesia', 'category:disease');

        $this->parametres->forget('test.site_name', 'category:disease');

        expect('Bar')->toBe($this->parametres->get('test.site_name', 'category:disease'));
    });
});
