<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require_once $root . '/functions.php';

\Codeception\Configuration::config($root . '/codeception.yml');
