<?php

namespace Application\Hydrator\Api;

use DateTime;
use DateInterval;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Permissions\Acl\Acl;

use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;

class UserHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    protected $userId = null;

    private $userRole = null;

    private $acl;

    private $router;

    /**
     * @var User
     */
    private $userModel;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->router = $serviceManager->get('HttpRouter');
        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
        $this->userModel = $serviceManager->get(\Autowp\User\Model\User::class);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('last_online', $strategy);
        $this->addStrategy('reg_date', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('image', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('img', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('avatar', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        return $this;
    }

    public function extract($object)
    {
        $deleted = (bool)$object['deleted'];

        $isMe = $object['id'] == $this->userId;

        if ($deleted) {
            $user = [
                'id'       => null,
                'name'     => null,
                'deleted'  => $deleted,
                'url'      => null,
                'longAway' => false,
                'green'    => false
            ];
        } else {
            $longAway = false;
            $lastOnline = Row::getDateTimeByColumnType('timestamp', $object['last_online']);
            if ($lastOnline) {
                $date = new DateTime();
                $date->sub(new DateInterval('P6M'));
                if ($date > $lastOnline) {
                    $longAway = true;
                }
            } else {
                $longAway = true;
            }

            $isGreen = $object['role'] && $this->acl->isAllowed($object['role'], 'status', 'be-green');

            $user = [
                'id'        => (int)$object['id'],
                'name'      => $object['name'],
                'deleted'   => $deleted,
                'url'       => $this->router->assemble([
                    'user_id' => $object['identity'] ? $object['identity'] : 'user' . $object['id']
                ], [
                    'name' => 'users/user'
                ]),
                'long_away' => $longAway,
                'green'     => $isGreen
            ];

            if ($this->filterComposite->filter('last_online')) {
                $lastOnline = Row::getDateTimeByColumnType('timestamp', $object['last_online']);
                $user['last_online'] = $this->extractValue('last_online', $lastOnline);
            }

            if ($this->filterComposite->filter('reg_date')) {
                $regDate = Row::getDateTimeByColumnType('timestamp', $object['reg_date']);
                $user['reg_date'] = $this->extractValue('reg_date', $regDate);
            }

            if ($this->filterComposite->filter('identity')) {
                $user['identity'] = $object['identity'];
            }

            if ($this->filterComposite->filter('image')) {
                $user['image'] = $this->extractValue('image', [
                    'image'  => $object['img']
                ]);
            }

            $canViewEmail = $isMe;
            if (! $canViewEmail) {
                $canViewEmail = $this->isModer();
            }

            if ($canViewEmail && $this->filterComposite->filter('email')) {
                $user['email'] = $object['e_mail'];
            }

            $canViewLogin = $isMe;
            if (! $canViewLogin) {
                $canViewLogin = $this->isModer();
            }

            if ($canViewLogin && $this->filterComposite->filter('login')) {
                $user['login'] = $object['login'];
            }

            if ($this->filterComposite->filter('img')) {
                $user['img'] = $this->extractValue('img', [
                    'image' => $object['img']
                ]);
            }

            if ($this->filterComposite->filter('avatar')) {
                $user['avatar'] = $this->extractValue('image', [
                    'image'  => $object['img'],
                    'format' => 'avatar'
                ]);
            }

            if ($this->filterComposite->filter('gravatar')) {
                $user['gravatar'] = sprintf(
                    'https://www.gravatar.com/avatar/%s?s=70&d=%s&r=g',
                    md5($object['e_mail']),
                    urlencode('https://www.autowp.ru/_.gif')
                );
            }

            if ($isMe && $this->filterComposite->filter('language')) {
                $user['language'] = $object['language'];
            }

            if ($isMe && $this->filterComposite->filter('timezone')) {
                $user['timezone'] = $object['timezone'];
            }

            if ($isMe && $this->filterComposite->filter('votes_left')) {
                $user['votes_left'] = (int)$object['votes_left'];
            }

            if ($isMe && $this->filterComposite->filter('votes_per_day')) {
                $user['votes_per_day'] = (int)$object['votes_per_day'];
            }

            if ($isMe && $this->filterComposite->filter('specs_weight')) {
                $user['specs_weight'] = (float)$object['specs_weight'];
            }
        }

        return $user;
    }

    private function isModer()
    {
        $role = $this->getUserRole();
        if (! $role) {
            return false;
        }

        return $this->acl->inheritsRole($role, 'moder');
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }

    public function setUserId($userId)
    {
        if ($this->userId != $userId) {
            $this->userId = $userId;
            $this->userRole = null;
        }

        return $this;
    }

    private function getUserRole()
    {
        if (! $this->userId) {
            return null;
        }

        if (! $this->userRole) {
            $this->userRole = $this->userModel->getUserRole($this->userId);
        }

        return $this->userRole;
    }
}
