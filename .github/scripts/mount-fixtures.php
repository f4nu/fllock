<?php

/*
 * Wires the package's fixture panel into a freshly scaffolded application.
 *
 * A script rather than `php -r` in the workflow: every namespace here is full
 * of backslashes, and escaping those through YAML, then the shell, then PHP is
 * how the previous three attempts died.
 *
 * Run from the application root, with the package checked out beside it.
 */

$fail = function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

// The fixtures are autoloaded from the package rather than copied in: copying
// them would put them at a path their PSR-4 namespace does not match.
$composer = json_decode(file_get_contents('composer.json'), true)
    ?? $fail('composer.json is not readable');

$composer['autoload-dev']['psr-4']['F4nu\\Fllock\\Tests\\'] = '../fllock/tests/';

file_put_contents(
    'composer.json',
    json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);

// Register the fixture panel.
$providersFile = 'bootstrap/providers.php';
$providers = file_get_contents($providersFile) ?: $fail("$providersFile is not readable");
$provider = 'F4nu\\Fllock\\Tests\\Fixtures\\AdminPanelProvider::class';

if (! str_contains($providers, $provider)) {
    $providers = preg_replace(
        '/\n\];/',
        "\n    " . $provider . ",\n];",
        $providers,
        1,
    ) ?? $fail('could not register the fixture panel provider');

    file_put_contents($providersFile, $providers);
}

echo "Fixture panel mounted." . PHP_EOL;
