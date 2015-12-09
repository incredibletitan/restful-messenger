<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Routing\Generator\UrlGenerator;

$app = new Silex\Application();
$app['debug'] = true;

// Register Provider to DB
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_sqlite',
        'path'     => __DIR__ . '/db/messenger.sqlite',
    ),
));

// Register url provider
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// accept JSON
$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);

        $request->request->replace(is_array($data) ? $data : array());
    }
});

$app->get('/users/', function(){
	return 1;
})->bind('users');

$app->post('/users/', function(Request $request) use ($app) {
	$insertUsersQuery = "INSERT INTO `users`(`name`,`email`,`password`) VALUES(?,?,?)";
	$name = $request->request->get("name");
	$email = $request->request->get("email");
	$password = $request->request->get("password");
	
	if (empty($name) || empty($password) || empty($email)) {

	}

	$app['db']->executeUpdate($insertUsersQuery, array(
		$request->request->get("name"),
		$request->request->get("email"),
		$request->request->get("password"),
	));

	return $app->json(array('status' => 'succcess', 'errors' => ''), 201, array('Location' => $request->getUri() . '/' . 101));
});

$app->get('/messages/', function() use ($app) {
    $sql = "SELECT * FROM messages";

	$messages = $app['db']->fetchAll($sql);
   
    
    return $app->json(array());
});

$app->error(function (\Exception $e, $code) use($app) {
    return $app->json(array("error" => $e->getMessage()),$code);    
});

// $app->get('/messages/{id}', function(Silex\Application $app, $id) use ($app) {
//     return 'messages ' . $id;
// });

// $app->post('/messages/', function() use ($app) {
// 	return 'messages post';
// });

$app->run();