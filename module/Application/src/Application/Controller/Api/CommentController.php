<?php

namespace Application\Controller\Api;

use DateTime;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Autowp\Votings\Votings;

use Application\Comments;
use Application\HostManager;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\Item;
use Application\Model\Picture;

class CommentController extends AbstractRestfulController
{
    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var TableGateway
     */
    private $userTable;

    /**
     * @var InputFilter
     */
    private $postInputFilter;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Votings
     */
    private $votings;

    /**
     * @var TableGateway
     */
    private $articleTable;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    /**
     * @var InputFilter
     */
    private $publicListInputFilter;

    /**
     * @var InputFilter
     */
    private $putInputFilter;

    /**
     * @var InputFilter
     */
    private $getInputFilter;

    public function __construct(
        Comments $comments,
        RestHydrator $hydrator,
        TableGateway $userTable,
        InputFilter $listInputFilter,
        InputFilter $publicListInputFilter,
        InputFilter $postInputFilter,
        InputFilter $putInputFilter,
        InputFilter $getInputFilter,
        User $userModel,
        HostManager $hostManager,
        MessageService $message,
        Picture $picture,
        Item $item,
        Votings $votings,
        TableGateway $articleTable
    ) {
        $this->comments = $comments;
        $this->hydrator = $hydrator;
        $this->userTable = $userTable;
        $this->listInputFilter = $listInputFilter;
        $this->publicListInputFilter = $publicListInputFilter;
        $this->postInputFilter = $postInputFilter;
        $this->putInputFilter = $putInputFilter;
        $this->getInputFilter = $getInputFilter;
        $this->userModel = $userModel;
        $this->hostManager = $hostManager;
        $this->message = $message;
        $this->picture = $picture;
        $this->item = $item;
        $this->votings = $votings;
        $this->articleTable = $articleTable;
    }

    public function subscribeAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $itemId = (int)$this->params('item_id');
        $typeId = (int)$this->params('type_id');

        switch ($this->getRequest()->getMethod()) {
            case Request::METHOD_POST:
            case Request::METHOD_PUT:
                $this->comments->service()->subscribe($typeId, $itemId, $user['id']);

                return new JsonModel([
                    'status' => true
                ]);
                break;

            case Request::METHOD_DELETE:
                $this->comments->service()->unSubscribe($typeId, $itemId, $user['id']);

                return new JsonModel([
                    'status' => true
                ]);
                break;
        }

        return $this->notFoundAction();
    }

    public function indexAction()
    {
        $user = $this->user()->get();

        $isModer = $this->user()->inheritsRole('moder');

        $inputFilter = $isModer ? $this->listInputFilter : $this->publicListInputFilter;

        $inputFilter->setData($this->params()->fromQuery());

        if (! $inputFilter->isValid()) {
            return $this->inputFilterResponse($inputFilter);
        }

        $values = $inputFilter->getValues();

        $options = [
            'order' => 'comment_message.datetime DESC'
        ];

        if ($values['item_id']) {
            $options['item_id'] = $values['item_id'];
        }

        if ($values['type_id']) {
            $options['type'] = $values['type_id'];
        }

        if ($values['parent_id']) {
            $options['parent_id'] = $values['parent_id'];
        }

        if ($values['no_parents']) {
            $options['no_parents'] = $values['no_parents'];
        }

        if ($isModer) {
            if ($values['user']) {
                if (! is_numeric($values['user'])) {
                    $userRow = $this->userTable->select([
                        'identity' => $values['user']
                    ])->current();
                    if ($userRow) {
                        $values['user'] = $userRow['id'];
                    }
                }

                $options['user'] = $values['user'];
            }

            if (strlen($values['moderator_attention'])) {
                $options['attention'] = $values['moderator_attention'];
            }

            if ($values['pictures_of_item_id']) {
                $options['type'] = \Application\Comments::PICTURES_TYPE_ID;
                $options['callback'] = function (Sql\Select $select) use ($values) {
                    $select
                        ->join('pictures', 'comment_message.item_id = pictures.id', [])
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                        ->where(['item_parent_cache.parent_id = ?' => $values['item_id']]);
                };
            }

            switch ($values['order']) {
                case 'date_desc':
                    $options['order'] = 'comment_message.datetime DESC';
                    break;
                case 'date_asc':
                default:
                    $options['order'] = 'comment_message.datetime ASC';
                    break;
            }
        } else {
            $options['order'] = 'comment_message.datetime ASC';
        }

        $paginator = $this->comments->service()->getMessagesPaginator($options);

        $paginator
            ->setItemCountPerPage(50)
            ->setCurrentPageNumber($this->params()->fromQuery('page'));

        $this->hydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null
        ]);

        $comments = [];
        foreach ($paginator->getCurrentItems() as $commentRow) {
            $comments[] = $this->hydrator->extract($commentRow);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $comments
        ]);
    }

    private function nextMessageTime()
    {
        $user = $this->user()->get();
        if (! $user) {
            return null;
        }

        return $this->userModel->getNextMessageTime($user['id']);
    }

    private function needWait()
    {
        $nextMessageTime = $this->nextMessageTime();
        if ($nextMessageTime) {
            return $nextMessageTime > new DateTime();
        }

        return false;
    }

    public function postAction()
    {
        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        $this->postInputFilter->setData($data);

        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $data = $this->postInputFilter->getValues();

        $itemId = (int)$data['item_id'];
        $typeId = (int)$data['type_id'];

        if ($this->needWait()) {
            return new ApiProblemResponse(new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                'invalid_params' => [
                    'message' => [
                        'invalid' => 'Too often'
                    ]
                ]
            ]));
        }

        $object = null;
        switch ($typeId) {
            case \Application\Comments::PICTURES_TYPE_ID:
                $object = $this->picture->getRow(['id' => $itemId]);
                break;

            case \Application\Comments::ITEM_TYPE_ID:
                $object = $this->item->getRow(['id' => $itemId]);
                break;

            case \Application\Comments::VOTINGS_TYPE_ID:
                $object = $this->votings->isVotingExists($itemId);
                break;

            case \Application\Comments::ARTICLES_TYPE_ID:
                $object = $this->articleTable->select(['id' => $itemId])->current();
                break;

            default:
                throw new Exception('Unknown type_id');
        }

        if (! $object) {
            return $this->notFoundAction();
        }

        $moderatorAttention = false;
        if ($this->user()->isAllowed('comment', 'moderator-attention')) {
            $moderatorAttention = (bool)$data['moderator_attention'];
        }

        $ip = $request->getServer('REMOTE_ADDR');
        if (! $ip) {
            $ip = '127.0.0.1';
        }

        $messageId = $this->comments->service()->add([
            'typeId'             => $typeId,
            'itemId'             => $itemId,
            'parentId'           => $data['parent_id'] ? (int)$data['parent_id'] : null,
            'authorId'           => $currentUser['id'],
            'message'            => $data['message'],
            'ip'                 => $ip,
            'moderatorAttention' => $moderatorAttention
        ]);

        if (! $messageId) {
            throw new Exception("Message add fails");
        }

        $this->userModel->getTable()->update([
            'last_message_time' => new Sql\Expression('NOW()')
        ], [
            'id' => $currentUser['id']
        ]);

        if ($this->user()->inheritsRole('moder')) {
            if ($data['parent_id'] && $data['resolve']) {
                $this->comments->service()->completeMessage($data['parent_id']);
            }
        }

        if ($data['parent_id']) {
            $authorId = $this->comments->service()->getMessageAuthorId($data['parent_id']);
            if ($authorId && ($authorId != $currentUser['id'])) {
                $parentMessageAuthor = $this->userModel->getTable()->select(['id' => (int)$authorId])->current();
                if ($parentMessageAuthor && ! $parentMessageAuthor['deleted']) {
                    $uri = $this->hostManager->getUriByLanguage($parentMessageAuthor['language']);

                    $url = $this->comments->getMessageUrl($messageId, true, $uri) . '#msg' . $messageId;
                    $moderUrl = $this->url()->fromRoute('users/user', [
                        'user_id' => $currentUser['identity'] ? $currentUser['identity'] : 'user' . $currentUser['id'],
                    ], [
                        'force_canonical' => true,
                        'uri'             => $uri
                    ]);
                    $message = sprintf(
                        $this->translate(
                            'pm/user-%s-replies-to-you-%s',
                            'default',
                            $parentMessageAuthor['language']
                        ),
                        $moderUrl,
                        $url
                    );
                    $this->message->send(null, $parentMessageAuthor['id'], $message);
                }
            }
        }

        $this->comments->notifySubscribers($messageId);

        $url = $this->url()->fromRoute('api/comment/item/get', [
            'id' => $messageId
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        return $this->getResponse()->setStatusCode(201);
    }

    public function putAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        //TODO: prevent load message from admin forum
        $row = $this->comments->service()->getMessageRow((int)$this->params('id'));
        if (! $row) {
            return $this->notFoundAction();
        }

        $request = $this->getRequest();
        $data = (array)$this->processBodyContent($request);

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->putInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'No fields provided'));
        }

        $this->putInputFilter->setValidationGroup($fields);

        $this->putInputFilter->setData($data);
        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $values = $this->putInputFilter->getValues();

        if (array_key_exists('user_vote', $values)) {
            if ($user['votes_left'] <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'user_vote' => [
                            'invalid' => $this->translate('comments/vote/no-more-votes')
                        ]
                    ]
                ]));
            }

            $result = $this->comments->service()->voteMessage(
                $row['id'],
                $user['id'],
                $values['user_vote']
            );
            if (! $result['success']) {
                return new ApiProblemResponse(new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'user_vote' => [
                            'invalid' => $result['error']
                        ]
                    ]
                ]));
            }

            $this->userModel->decVotes($user['id']);
        }

        if (array_key_exists('deleted', $values)) {
            if ($this->user()->isAllowed('comment', 'remove')) {
                if ($values['deleted']) {
                    $this->comments->service()->queueDeleteMessage($row['id'], $user['id']);
                } else {
                    $this->comments->service()->restoreMessage($row['id']);
                }
            }
        }

        return $this->getResponse()->setStatusCode(200);
    }

    public function getAction()
    {
        $user = $this->user()->get();

        $this->getInputFilter->setData($this->params()->fromQuery());

        if (! $this->getInputFilter->isValid()) {
            return $this->inputFilterResponse($this->getInputFilter);
        }

        $values = $this->getInputFilter->getValues();

        //TODO: prevent load message from admin forum
        $row = $this->comments->service()->getMessageRow((int)$this->params('id'));
        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }
}
