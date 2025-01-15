<?php

use BlitzPHP\Parametres\Config\Services;
use BlitzPHP\Parametres\Handlers\BaseHandler;
use BlitzPHP\Parametres\Parametres;
use BlitzPHP\Spec\ReflectionHelper;

use function Kahlan\expect;

class FakeHandler extends BaseHandler
{
	/** {@inheritDoc} */
	public function has(string $file, string $property, ?string $context = null): bool
	{
		return true;
	}
	/** {@inheritDoc} */
	public function get(string $file, string $property, ?string $context = null): mixed
	{
		return '';
	}
}

describe('Parametres / BaseHandler', function() {
	it("set", function() {
        $handler = new FakeHandler();

		expect(fn() => $handler->set('test', 'site_name', 'category:disease'))->toThrow(new RuntimeException());
    });

	it("forget", function() {
        $handler = new FakeHandler();

		expect(fn() => $handler->forget('test', 'site_name'))->toThrow(new RuntimeException());
    });
	it("flush", function() {
        $handler = new FakeHandler();

		expect(fn() => $handler->flush())->toThrow(new RuntimeException());
    });
});
