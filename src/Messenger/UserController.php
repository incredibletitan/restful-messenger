<?php
namespace Messenger;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Silex\Application;
use Silex\ControllerProviderInterface;

class UserController implements ControllerProviderInterface
{
    /**
     * @param Application $app
     * @return \Silex\ControllerCollection
     */
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

        $factory->put(
            '/{id}',
            'Messenger\UserController::update'
        );

        $factory->patch(
            '/{id}',
            'Messenger\UserController::patch'
        );

        $factory->options(
            '/',
            'Messenger\UserController::options'
        );

        return $factory;
    }

    /**
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getAll(Application $app)
    {
        $sql = "SELECT * FROM `users`";
        $users = $app['db']->fetchAll($sql);

        return $app->json($users);
    }

    /**
     * @param Application $app
     * @param $id - user Id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function get(Application $app, $id)
    {
        $user = $this->getUserById($id, $app);

        if (!$user) {
            return $app->json(array('status' => 'fail', 'errors' => 'Not found'), 404);
        }

        return $app->json($user);
    }

    /**
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function create(Application $app, Request $request)
    {
        $errors = $this->validate($request->request->all(), $app);

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

    /**
     * @param Application $app
     * @param Request $request
     * @param $id
     * @param bool|false $ignoreBlank - ignoring empty field validation to realize patch request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function update(Application $app, Request $request, $id, $ignoreBlank = false)
    {
        $user = $this->getUserById($id, $app);

        if (!$user) {
            return $app->json(array('status' => 'fail', 'errors' => 'Not found'), 404);
        }
        $errors = $this->validate($request->request->all(), $app, $ignoreBlank);

        if (count($errors) > 0) {
            $errorsArray = array();

            foreach ($errors as $error) {
                $errorsArray[$error->getPropertyPath()] = $error->getMessage();
            }

            return $app->json(array('response' => 'fail', 'errors' => $errorsArray), 400);
        }

        //If some fields are empty(for example in patch request), query doesn't change them
        $updateUsersQuery = "UPDATE `users` SET `name`=coalesce(?, `name`),`email`=coalesce(?, `email`),`password`=coalesce(?, `password`) WHERE id=?";

        $updateResult = $app['db']->executeUpdate($updateUsersQuery, array(
            $request->request->get("name"),
            $request->request->get("email"),
            $request->request->get("password"),
            $id
        ));

        if (!$updateResult) {
            return $app->json(array('response' => 'fail', 'errors' => 'Conflict'), 409);
        }

        return $app->json(
            array('status' => 'success', 'errors' => ''),
            204
        );
    }

    /**
     * @param Application $app
     * @param Request $request
     * @param $id - User id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function patch(Application $app, Request $request, $id)
    {
        return $this->update($app, $request, $id, true);
    }

    /**
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function options(Application $app)
    {
        return $app->json('', 200, array('Allow' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS'));
    }

    /**
     * @param $user
     * @param Application $app
     * @param bool|false $ignoreBlank - ignoring empty field validation to realize patch request
     * @return mixed
     */
    private function validate($user, Application $app, $ignoreBlank = false)
    {
        $nameValidation = array();
        $emailValidation = array();
        $passwordValidation = array();
        $constraintArray = array();

        if (isset($user['name'])) {
            $nameValidation[] = new Assert\Length(array('min' => 2));
        }

        if (isset($user['email'])) {
            $emailValidation[] = new Assert\Email();
        }

        if (isset($user['password'])) {
            $passwordValidation[] = new Assert\Length(array('min' => 6));
        }

        if (!$ignoreBlank) {
            $nameValidation[] = new Assert\NotBlank();
            $emailValidation[] = new Assert\NotBlank();
            $passwordValidation[] = new Assert\NotBlank();
        }

        if (count($nameValidation) > 0) {
            $constraintArray['name'] = $nameValidation;
        }

        if (count($emailValidation) > 0) {
            $constraintArray['email'] = $emailValidation;
        }

        if (count($passwordValidation) > 0) {
            $constraintArray['password'] = $passwordValidation;
        }
        $constraint = new Assert\Collection($constraintArray);
        $errors = $app['validator']->validateValue($user, $constraint);

        return $errors;
    }

    /**
     * @param $id - User id
     * @param $app
     * @return mixed
     */
    private function getUserById($id, $app)
    {
        $userQuery = "SELECT * FROM `users` WHERE `id`=?";
        $user = $app['db']->fetchArray($userQuery, array($id));

        return $user;
    }
}
