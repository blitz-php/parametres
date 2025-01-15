<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: argument.type
	'message' => '#^Parameter \\#1 \\$name of function service expects class\\-string\\<parametres\\>, string given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Commands/ClearParametres.php',
];
$ignoreErrors[] = [
	// identifier: argument.type
	'message' => '#^Parameter \\#1 \\$name of function service expects class\\-string\\<parametres\\>, string given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Config/helpers.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property BlitzPHP\\\\Parametres\\\\Handlers\\\\DatabaseHandler\\:\\:\\$builder \\(BlitzPHP\\\\Database\\\\Builder\\\\BaseBuilder\\) does not accept BlitzPHP\\\\Contracts\\\\Database\\\\BuilderInterface\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Handlers/DatabaseHandler.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property BlitzPHP\\\\Parametres\\\\Handlers\\\\DatabaseHandler\\:\\:\\$db \\(BlitzPHP\\\\Database\\\\Connection\\\\BaseConnection\\) does not accept BlitzPHP\\\\Contracts\\\\Database\\\\ConnectionInterface\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Handlers/DatabaseHandler.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
