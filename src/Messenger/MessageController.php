<?php
namespace Messenger;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Silex\Application;
use Silex\ControllerProviderInterface;

class MessageController implements ControllerProviderInterface
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
            'Messenger\MessageController::getAll'
        );

        $factory->get(
            '/{id}',
            'Messenger\MessageController::get'
        );

        $factory->post(
            '/',
            'Messenger\MessageController::create'
        );

        $factory->put(
            '/{id}',
            'Messenger\MessageController::update'
        );

        $factory->patch(
            '/{id}',
            'Messenger\MessageController::patch'
        );

        $factory->options(
            '/',
            'Messenger\MessageController::options'
        );

        $factory->delete(
            '/{id}',
            'Messenger\MessageController::delete'
        );

        return $factory;
    }

    /**
     * @param Application $app
     * @param $user_id - User id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getAll(Application $app, $user_id)
    {
        if (!$this->getUserById($user_id, $app)) {
            return $app->json(array('status' => 'fail', 'errors' => 'Not found'), 404);
        }
        $sql = "SELECT * FROM `messages` WHERE `user_id`=?";
        $message = $app['db']->fetchAll($sql, array($user_id));

        return $app->json($message);
    }

    /**
     * @param Application $app
     * @param $user_id - User id
     * @param $id - Message id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function get(Application $app, $id, $user_id)
    {
        if (!$this->getUserById($user_id, $app)) {
            return $app->json('Not found', 404);
        }
        $message = $this->getMessageByUserAndMessageId($user_id, $id, $app);

        if (!$message) {
            return $app->json(array('status' => 'fail', 'errors' => 'Not found'), 404);
        }

        return $app->json($message);
    }

    /**
     * @param Application $app
     * @param Request $request
     * @param $user_id - User id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function create(Application $app, Request $request, $user_id)
    {
        if (!$this->getUserById($user_id, $app)) {
            return $app->json('Not found', 404);
        }
        $errors = $this->validate($request->request->all(), $app);

        if (count($errors) > 0) {
            $errorsArray = array();

            foreach ($errors as $error) {
                $errorsArray[$error->getPropertyPath()] = $error->getMessage();
            }

            return $app->json(array('response' => 'fail', 'errors' => $errorsArray), 400);
        }
        $insertMessageQuery = "INSERT INTO `messages`(`title`,`body`,`user_id`) VALUES(?,?,?)";

        $addMessageResult = $app['db']->executeUpdate($insertMessageQuery, array(
            $request->request->get("title"),
            $request->request->get("body"),
            $user_id
        ));

        if (!$addMessageResult) {
            return $app->json(array('response' => 'fail', 'errors' => 'Conflict'), 409);
        }
        $messageId = $app['db']->lastInsertId();

        return $app->json(
            array('status' => 'success', 'errors' => ''),
            201,
            array('Location' => $request->getUri() . $messageId)
        );
    }

    /**
     * @param Application $app
     * @param Request $request
     * @param $id - Message id
     * @param $user_id - User id
     * @param bool|false $ignoreBlank - ignoring empty field validation to realize patch request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function update(Application $app, Request $request, $id, $user_id, $ignoreBlank = false)
    {
        if (!$this->getUserById($user_id, $app)) {
            return $app->json('Not found', 404);
        }

        $message = $this->getMessageByUserAndMessageId($user_id, $id, $app);

        if (!$message) {
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
        $updateMessageQuery = "UPDATE `messages` SET `title`=coalesce(?, `title`),`body`=coalesce(?, `body`) WHERE id=?";

        $updateResult = $app['db']->executeUpdate($updateMessageQuery, array(
            $request->request->get("title"),
            $request->request->get("body"),
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
    public function patch(Application $app, Request $request, $id, $user_id)
    {
        return $this->update($app, $request, $id, $user_id, true);
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
     * @param Application $app
     * @param $user_id - User id
     * @param $id - Message id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function delete(Application $app, $id, $user_id)
    {
        if (!$this->getUserById($user_id, $app)) {
            return $app->json('Not found', 404);
        }
        $message = $this->getMessageByUserAndMessageId($user_id, $id, $app);

        if (!$message) {
            return $app->json(array('status' => 'fail', 'errors' => 'Not found'), 404);
        }
        $deleteResult = $app['db']->delete('messages', array('id' => $id));

        if (!$deleteResult) {
            return $app->json(array('response' => 'fail', 'errors' => 'Conflict'), 409);
        }

        return $app->json(
            array('status' => 'success', 'errors' => ''),
            204
        );
    }

    /**
     * @param $message - Message
     * @param Application $app
     * @param bool|false $ignoreBlank - ignoring empty field validation to realize patch request
     * @return mixed
     */
    private function validate($message, Application $app, $ignoreBlank = false)
    {
        $titleValidation = array();
        $contentValidation = array();
        $constraintArray = array();

        if (isset($message['title'])) {
            $titleValidation[] = new Assert\Length(array('min' => 2));
        }

        if (isset($message['body'])) {
            $contentValidation[] = new Assert\Length(array('min' => 6));
        }

        if (!$ignoreBlank) {
            $titleValidation[] = new Assert\NotBlank();
            $contentValidation[] = new Assert\NotBlank();
        }

        if (count($titleValidation) > 0) {
            $constraintArray['title'] = $titleValidation;
        }

        if (count($contentValidation) > 0) {
            $constraintArray['body'] = $contentValidation;
        }
        $constraint = new Assert\Collection($constraintArray);
        $errors = $app['validator']->validateValue($message, $constraint);

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

    /**
     * @param $id - User id
     * @param $app
     * @return mixed
     */

    private function getMessageByUserAndMessageId($user_id, $id, $app)
    {
        $sql = "SELECT * FROM `messages` WHERE `user_id`=? AND `id`=?";
        $message = $app['db']->fetchArray($sql, array($user_id, $id));

        return $message;
    }
}
