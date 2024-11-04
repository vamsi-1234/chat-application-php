<?php
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

// Create a new container
$container = new Container();
AppFactory::setContainer($container);

// Create Slim app
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);


// Load dependencies and routes
require __DIR__ . '/../src/dependencies.php';
require_once __DIR__ . '/../src/routes.php';

$app->run();

return $app;
