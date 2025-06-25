<?php

declare(strict_types=1);

$finder = Isolated\Symfony\Component\Finder\Finder::class;

$project_source_dir = __DIR__;

$config = include __DIR__ . '/vendor/reallyspecific/wp-utils/assets/scoper-config.inc.php';

$config['prefix']     = "ReallySpecific\\BetterLLMStxt\\Dependencies";
$config['output-dir'] = __DIR__ . '/dependencies';

return $config;