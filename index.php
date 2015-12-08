<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

$app = new Silex\Application();
$app['debug'] = true;

// accept JSON
$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_sqlite',
        'path'     => __DIR__.'/db/messenger.sqlite',
    ),
));

$app->get('/messages/', function() use ($app) {
    $sql = "SELECT * FROM messages";
   	$messages = $app['db']->fetchAll($sql);
    
    return $app->json($messages);
});

$app->get('/messages/{id}', function(Silex\Application $app, $id) use ($app) {
    return 'messages ' . $id;
});

$app->post('/messages/', function() use ($app) {
	return 'messages post';
});

$app->run();