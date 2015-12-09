<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

$app = new Silex\Application();
$app['debug'] = true;

//Register validator service
$app->register(new Silex\Provider\ValidatorServiceProvider());

// Register Provider to DB
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver' => 'pdo_sqlite',
        'path' => __DIR__ . '/db/messenger.sqlite',
    ),
));

// Make json accepting functionality
$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);

        $request->request->replace(is_array($data) ? $data : array());
    }
});

//Mount needed controllers
$app->mount('/users', new Messenger\UserController());
$app->mount('/users/{user_id}/messages', new Messenger\MessageController());

$app->error(function (\Exception $e, $code) use ($app) {
    return $app->json(array("error" => $e->getMessage()), $code);
});

$app->run();
