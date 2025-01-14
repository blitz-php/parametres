<?php

use BlitzPHP\CodingStandard\Blitz;
use Nexus\CsConfig\Factory;
use Nexus\CsConfig\Fixer\Comment\NoCodeSeparatorCommentFixer;
use Nexus\CsConfig\FixerGenerator;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->files()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/spec',
    ])
    ->append([
        __FILE__,
    ]);

$overrides = [
    'static_lambda' => false,
];

$options = [
    'cacheFile'    => 'build/.php-cs-fixer.cache',
    'finder'       => $finder,
    'customFixers' => FixerGenerator::create('vendor/nexusphp/cs-config/src/Fixer', 'Nexus\\CsConfig\\Fixer'),
    'customRules'  => [
        NoCodeSeparatorCommentFixer::name() => true,
    ],
];

return Factory::create(new Blitz(), $overrides, $options)->forLibrary(
    ':vendor_slug/:package_slug"',
    ':author_name',
    'author@domain.com',
    date('Y')
);
