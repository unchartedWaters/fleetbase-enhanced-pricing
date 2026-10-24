<?php

declare(strict_types=1);

// Prepended to every Pest process by scripts/pest-runner.php. The pricing engine and the profile
// checks are plain PHP, so the Composer autoloader is all most tests need.

$autoloadCandidates = [
    getcwd() . '/server_vendor/autoload.php',
    getcwd() . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate) === true) {
        require_once $candidate;
        break;
    }
}

set_error_handler(function (int $severity, string $message): bool {
    if (str_contains($message, '/pestphp/pest/vendor/autoload.php') === true) {
        return true;
    }

    return false;
}, E_WARNING);
