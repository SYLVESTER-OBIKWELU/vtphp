<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use VtPhp\Foundation\Application;
use VtPhp\Foundation\Http\Kernel;
use VtPhp\Http\ResponseEmitter;

require dirname(__DIR__).'/vendor/autoload.php';

/** @var Application $app */
$app = require dirname(__DIR__).'/bootstrap/app.php';

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);

(new ResponseEmitter())->emit($response);

$kernel->terminate($request, $response);
