<?php

namespace Application\Controller\Api;

use Application\Model\UserAccount;
use Application\Service\UsersService;
use Autowp\User\Model\User;
use Autowp\User\Model\UserRemember;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LoginControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): LoginController
    {
        $filters = $container->get('InputFilterManager');
        $tables  = $container->get('TableManager');
        return new LoginController(
            $container->get(UsersService::class),
            $container->get('ExternalLoginServiceManager'),
            $filters->get('api_login'),
            $container->get('Config')['hosts'],
            $container->get(UserRemember::class),
            $container->get(UserAccount::class),
            $tables->get('login_state'),
            $container->get(User::class)
        );
    }
}