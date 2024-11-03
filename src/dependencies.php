<?php
// Access the container through the app instance
$container = $app->getContainer();

$container->set('db', function () {
    return new PDO('sqlite:' . __DIR__ . '/../database/chat.db');
});
