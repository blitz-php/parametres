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
        Services::resetSingle('parametres');

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

    describe('getMany', function () {
        it('retourne plusieurs valeurs', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'test.site_email' => 'batch@example.com',
            ]);

            $results = $this->parametres->getMany(['test.site_name', 'test.site_email']);

            expect($results['test.site_name'])->toBe('BatchName');
            expect($results['test.site_email'])->toBe('batch@example.com');
        });

        it('retourne un tableau vide pour une liste vide', function () {
            expect($this->parametres->getMany([]))->toBe([]);
        });

        it('retourne null pour les clés inexistantes', function () {
            $results = $this->parametres->getMany(['nada.site_name']);

            expect($results['nada.site_name'])->toBeNull();
        });

        it('respecte le contexte', function () {
            $this->parametres->setMany(['test.site_name' => 'General']);

            $this->parametres->setMany(['test.site_name' => 'Context'], 'testing:true');

            $results = $this->parametres->getMany(['test.site_name'], 'testing:true');

            expect($results['test.site_name'])->toBe('Context');
        });
    });

    describe('setMany', function () {
        it('stocke plusieurs valeurs', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'test.site_email' => 'batch@example.com',
            ]);

            expect($this->parametres->get('test.site_name'))->toBe('BatchName');
            expect($this->parametres->get('test.site_email'))->toBe('batch@example.com');
        });

        it('accepte un tableau vide', function () {
            $this->parametres->set('test.site_name', 'ExistingName');
            $this->parametres->setMany([]);

            expect($this->parametres->get('test.site_name'))->toBe('ExistingName');
        });

        it('respecte le contexte', function () {
            $this->parametres->setMany([
                'test.site_name'=> 'BatchName',
                'test.site_email' => 'batch@example.com',
            ], 'testing:true');

            expect($this->parametres->get('test.site_name', 'testing:true'))->toBe('BatchName');
            expect($this->parametres->get('test.site_email', 'testing:true'))->toBe('batch@example.com');
        });
    });

    describe('forgetMany', function () {
        xit('supprime plusieurs valeurs', function () {
            $this->parametres->setMany([
                'test.site_name' => 'BatchName',
                'test.site_email'  => 'batch@example.com',
            ]);

            $this->parametres->forgetMany(['test.site_name', 'test.site_email']);

            expect($this->parametres->get('test.site_name'))->toBeNull();
            expect($this->parametres->get('test.site_email'))->toBeNull();
        });

        it('accepte un tableau vide', function () {
            $this->parametres->set('test.site_name', 'ExistingName');
            $this->parametres->forgetMany([]);

            expect($this->parametres->get('test.site_name'))->toBe('ExistingName');
        });

        it('respecte le contexte', function () {
            $this->parametres->set('test.site_name', 'General');
            $this->parametres->set('test.site_name', 'Context', 'testing:true');

            $this->parametres->forgetMany(['test.site_name'], 'testing:true');

            expect($this->parametres->get('test.site_name', 'testing:true'))->toBe('General');
        });
    });
});
