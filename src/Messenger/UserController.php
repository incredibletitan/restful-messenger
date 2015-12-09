<?php
namespace Messenger;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Silex\Application;
use Silex\ControllerProviderInterface;

class UserController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        /**
         * @var \Silex\ControllerCollection $factory
         */
        $factory = $app['controllers_factory'];

        $factory->get(
            '/',
            'Messenger\UserController::getAll'
        );

        $factory->get(
            '/{id}',
            'Messenger\UserController::get'
        );

        $factory->post(
            '/',
            'Messenger\UserController::create'
        );


        return $factory;
    }

    public function getAll(Application $app)
    {
        $sql = "SELECT * FROM `users`";
        $users = $app['db']->fetchAll($sql);

        return $app->json($users);
    }

    public function get(Application $app, $id)
    {
        $sql = "SELECT * FROM `users` WHERE `id`=?";
        $user = $app['db']->fetchArray($sql, array($id));

        if (!$user) {
            return $app->json(array('status' => 'fail', 'errors' => 'Not found'), 404);
        }

        return $app->json($user);
    }

    //
    public function create(Application $app, Request $request)
    {
        $name = $request->request->get("name");
        $email = $request->request->get("email");
        $password = $request->request->get("password");

        $user = array(
            'name' => $name,
            'email' => $email,
            'password' => $password
        );

        $constraint = new Assert\Collection(array(
            'name' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 2))),
            'email' => array(new Assert\NotBlank(), new Assert\Email()),
            'password' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 6)))
        ));
        $errors = $app['validator']->validateValue($user, $constraint);

        if (count($errors) > 0) {
            $errorsArray = array();

            foreach ($errors as $error) {
                $errorsArray[$error->getPropertyPath()] = $error->getMessage();
            }

            return $app->json(array('response' => 'fail', 'errors' => $errorsArray), 400);
        }
        $insertUsersQuery = "INSERT INTO `users`(`name`,`email`,`password`) VALUES(?,?,?)";

        $addUserResult = $app['db']->executeUpdate($insertUsersQuery, array(
            $request->request->get("name"),
            $request->request->get("email"),
            $request->request->get("password"),
        ));

        if (!$addUserResult) {
            return $app->json(array('response' => 'fail', 'errors' => 'Conflict'), 409);
        }
        $userId = $app['db']->lastInsertId();

        return $app->json(
            array('status' => 'success', 'errors' => ''),
            201,
            array('Location' => $request->getUri() . $userId)
        );
    }

}