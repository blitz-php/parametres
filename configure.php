#!/usr/bin/env php
<?php

function ask(string $question, string $default = ''): string
{
    $answer = readline($question.($default ? " ({$default})" : null).': ');

    if (! $answer) {
        return $default;
    }

    return $answer;
}

function askWithOptions(string $question, array $options, string $default = ''): string
{
    $suggestions = implode('/', array_map(
        fn (string $option) => $option === $default ? strtoupper($option) : $option,
        $options,
    ));

    $answer = ask("{$question} ({$suggestions})");

    $validOptions = implode(', ', $options);

    while (! in_array($answer, $options)) {
        if ($default && $answer === '') {
            $answer = $default;

            break;
        }

        writeln(PHP_EOL."Veuillez choisir l'une des options suivantes: {$validOptions}");

        $answer = ask("{$question} ({$suggestions})");
    }

    if (! $answer) {
        $answer = $default;
    }

    return $answer;
}

function confirm(string $question, bool $default = false): bool
{
    $answer = ask($question.' ('.($default ? 'Y/n' : 'y/N').')');

    if (! $answer) {
        return $default;
    }

    return strtolower($answer) === 'y';
}

function writeln(string $line): void
{
    echo $line.PHP_EOL;
}

function run(string $command): string
{
    return trim(shell_exec($command));
}

function str_after(string $subject, string $search): string
{
    $pos = strrpos($subject, $search);

    if ($pos === false) {
        return $subject;
    }

    return substr($subject, $pos + strlen($search));
}

function slugify(string $subject): string
{
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $subject), '-'));
}

function title_case(string $subject): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $subject)));
}

function replace_in_file(string $file, array $replacements): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        str_replace(
            array_keys($replacements),
            array_values($replacements),
            $contents
        )
    );
}

function removeReadmeParagraphs(string $file): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        preg_replace('/<!--delete-->.*<!--\/delete-->/s', '', $contents) ?: $contents
    );
}

function determineSeparator(string $path): string
{
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function replaceForWindows(): array
{
    return preg_split('/\\r\\n|\\r|\\n/', run('dir /S /B * | findstr /v /i .git\ | findstr /v /i vendor | findstr /v /i '.basename(__FILE__).' | findstr /r /i /M /F:/ ":author :vendor :package VendorName skeleton vendor_name vendor_slug author@domain.com"'));
}

function replaceForAllOtherOSes(): array
{
    return explode(PHP_EOL, run('grep -E -r -l -i ":author|:vendor|:package|VendorName|skeleton|vendor_name|vendor_slug|author@domain.com" --exclude-dir=vendor ./* ./.github/* | grep -v '.basename(__FILE__)));
}

function setupTestingLibrary(string $testingLibrary): void
{
    if ($testingLibrary === 'pest') {
        unlink(__DIR__.'/tests/ExampleTestPhpunit.php');
        unlink(__DIR__.'/tests/ExampleTestKahlan.php');
        unlink(__DIR__.'/.github/workflows/run-tests-phpunit.yml');
        unlink(__DIR__.'/.github/workflows/run-tests-kahlan.yml');
        unlink(__DIR__.'/phpunit.xml.dist');
        unlink(__DIR__.'/kahlan-config.php');

        rename(
            from: __DIR__.'/tests/ExampleTestPest.php',
            to: __DIR__.'/tests/ExampleTest.php'
        );

        rename(
            from: __DIR__.'/.github/workflows/run-tests-pest.yml',
            to: __DIR__.'/.github/workflows/run-tests.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_testing' => '"pestphp/pest": "^2.15"',
            ':scripts_testing' => '"test": "vendor/bin/pest",
            "test:cov": "vendor/bin/pest --coverage"',
            ':plugins_testing' => '"pestphp/pest-plugin": true',
        ]);
    } elseif ($testingLibrary === 'phpunit') {
        unlink(__DIR__.'/tests/ExampleTestPest.php');
        unlink(__DIR__.'/tests/ArchTest.php');
        unlink(__DIR__.'/tests/Pest.php');
        unlink(__DIR__.'/tests/ExampleTestKahlan.php');
        unlink(__DIR__.'/.github/workflows/run-tests-pest.yml');
        unlink(__DIR__.'/.github/workflows/run-tests-kahlan.yml');
        unlink(__DIR__.'/kahlan-config.php');

        rename(
            from: __DIR__.'/tests/ExampleTestPhpunit.php',
            to: __DIR__.'/tests/ExampleTest.php'
        );

        rename(
            from: __DIR__.'/.github/workflows/run-tests-phpunit.yml',
            to: __DIR__.'/.github/workflows/run-tests.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_testing' => '"phpunit/phpunit": "^10.3.2"',
            ':scripts_testing' => '"test": "vendor/bin/phpunit",
            "test:cov": "vendor/bin/phpunit --coverage"',
            ':plugins_testing,' => '', // Nous devons également supprimer la virgule ici, car il n'y a rien à ajouter
        ]);
    } elseif ($testingLibrary === 'kahlan') {
        unlink(__DIR__.'/tests/ExampleTestPest.php');
        unlink(__DIR__.'/tests/ExampleTestPhpunit.php');
        unlink(__DIR__.'/tests/ArchTest.php');
        unlink(__DIR__.'/tests/Pest.php');
        unlink(__DIR__.'/.github/workflows/run-tests-pest.yml');
        unlink(__DIR__.'/.github/workflows/run-tests-phpunit.yml');
        unlink(__DIR__.'/phpunit.xml.dist');

        rename(
            from: __DIR__.'/tests/ExampleTestKahlan.php',
            to: __DIR__.'/tests/ExampleSpec.php'
        );
        rename(
            from: __DIR__.'/tests',
            to: __DIR__.'/spec'
        );
        file_put_contents(__DIR__.'/spec/bootstrap.php', "<?php \n");

        rename(
            from: __DIR__.'/.github/workflows/run-tests-kahlan.yml',
            to: __DIR__.'/.github/workflows/run-tests.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_testing' => '"kahlan/kahlan": "^6.0"',
            ':scripts_testing' => '"test": "vendor/bin/kahlan",
			"test:cov": [
				"Composer\\Config::disableProcessTimeout",
				"vendor/bin/kahlan --coverage=4 --reporter=verbose --clover=clover.xml"
			]',
            ':plugins_testing,' => '', // Nous devons également supprimer la virgule ici, car il n'y a rien à ajouter
        ]);
    }
}

function setupCodeStyleLibrary(string $codeStyleLibrary): void
{
    if ($codeStyleLibrary === 'pint') {
        unlink(__DIR__.'/.github/workflows/fix-php-code-style-issues-cs-fixer.yml');

        rename(
            from: __DIR__.'/.github/workflows/fix-php-code-style-issues-pint.yml',
            to: __DIR__.'/.github/workflows/fix-php-code-style-issues.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_codestyle' => '"laravel/pint": "^1.0"',
            ':scripts_codestyle' => '"format": "vendor/bin/pint"',
            ':plugins_testing' => '',
        ]);

        unlink(__DIR__.'/.php-cs-fixer.dist.php');
    } elseif ($codeStyleLibrary === 'cs fixer') {
        unlink(__DIR__.'/.github/workflows/fix-php-code-style-issues-pint.yml');

        rename(
            from: __DIR__.'/.github/workflows/fix-php-code-style-issues-cs-fixer.yml',
            to: __DIR__.'/.github/workflows/fix-php-code-style-issues.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_codestyle' => '"blitz-php/coding-standard": "^1.4"',
            ':scripts_codestyle' => '"cs:fix": [
            	"Composer\\Config::disableProcessTimeout",
				"vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --allow-risky=yes"
			]',
            ':plugins_testing' => '',
        ]);
    }
}

$gitName = run('git config user.name');
$authorName = ask('Nom de l\'auteur', $gitName);

$gitEmail = run('git config user.email');
$authorEmail = ask('Email de l\'auteur', $gitEmail);

$usernameGuess = explode(':', run('git config remote.origin.url'))[1];
$usernameGuess = dirname($usernameGuess);
$usernameGuess = basename($usernameGuess);
$authorUsername = ask('Nom d\'utilisateur de l\'auteur', $usernameGuess);

$vendorName = ask('Nom du fournisseur', $authorUsername);
$vendorSlug = slugify($vendorName);
$vendorNamespace = ucwords($vendorName);
$vendorNamespace = ask('Namespace du fournisseur', $vendorNamespace);

$currentDirectory = getcwd();
$folderName = basename($currentDirectory);

$packageName = ask('Nom du package', $folderName);
$packageSlug = slugify($packageName);

$className = title_case($packageName);
$className = ask('Nom de la classe', $className);
$description = ask('Description du package', "Ceci est mon package {$packageSlug}");

$testingLibrary = askWithOptions(
    'Quelle bibliothèque de tests souhaitez-vous utiliser?',
    ['kahlan', 'pest', 'phpunit'],
    'kahlan',
);

$codeStyleLibrary = askWithOptions(
    'Quelle bibliothèque de styles de code souhaitez-vous utiliser?',
    ['cs fixer', 'pint'],
    'cs fixer',
);

writeln('------');
writeln("Auteur        			 : {$authorName} ({$authorUsername}, {$authorEmail})");
writeln("Fournisseur   			 : {$vendorName} ({$vendorSlug})");
writeln("Package       			 : {$packageSlug} <{$description}>");
writeln("Namespace     			 : {$vendorNamespace}\\{$className}");
writeln("Nom de classe 			 : {$className}");
writeln("Librarie de test 		 : {$testingLibrary}");
writeln("Librairie de code style : {$codeStyleLibrary}");
writeln('------');

writeln('Ce script remplacera les valeurs ci-dessus dans tous les fichiers pertinents du répertoire du projet.');

if (! confirm('Modifier des fichiers?', true)) {
    exit(1);
}

$files = (str_starts_with(strtoupper(PHP_OS), 'WIN') ? replaceForWindows() : replaceForAllOtherOSes());

foreach ($files as $file) {
    replace_in_file($file, [
        ':author_name' => $authorName,
        ':author_username' => $authorUsername,
        'author@domain.com' => $authorEmail,
        ':vendor_name' => $vendorName,
        ':vendor_slug' => $vendorSlug,
        'VendorName' => $vendorNamespace,
        ':package_name' => $packageName,
        ':package_slug' => $packageSlug,
        'Skeleton' => $className,
        ':package_description' => $description,
    ]);

    match (true) {
        str_contains($file, determineSeparator('src/SkeletonClass.php')) => rename($file, determineSeparator('./src/'.$className.'Class.php')),
        str_contains($file, 'README.md') => removeReadmeParagraphs($file),
        default => [],
    };
}

setupTestingLibrary($testingLibrary);
setupCodeStyleLibrary($codeStyleLibrary);

confirm('Executer `composer install` et lancer les tests?') && run('composer install && composer test');

confirm('Laissez ce script se supprimer?', true) && unlink(__FILE__);
