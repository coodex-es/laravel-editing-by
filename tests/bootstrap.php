<?php

$autoloadCandidates = array_filter([
    getenv('EDITING_BY_TEST_AUTOLOAD') ?: null,
    __DIR__.'/../vendor/autoload.php',
]);

$loader = null;

foreach ($autoloadCandidates as $autoloadPath) {
    if (! is_file($autoloadPath)) {
        continue;
    }

    $loader = require $autoloadPath;

    if ($loader instanceof Composer\Autoload\ClassLoader) {
        $loader->addPsr4('CoodexEs\\LaravelEditingBy\\Tests\\', __DIR__);
    }

    break;
}

if (! $loader) {
    fwrite(STDERR, "Unable to locate an autoloader for package tests.\n");
    exit(1);
}
