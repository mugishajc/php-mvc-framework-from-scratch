<?php

declare(strict_types=1);

use Framework\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = (require dirname(__DIR__) . '/bootstrap/app.php')();

$app->run(Request::fromGlobals())->send();
