<?php

include_once __DIR__.'/../vendor/autoload.php';

$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->addPsr4('PhpDeploy\\', __DIR__.'/../lib', true);
$classLoader->register();
