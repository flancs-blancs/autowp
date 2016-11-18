<?php

namespace Application\View\Helper\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\View\Helper\Sidebar as Helper;

class SidebarFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Helper(
            $container->get(\Application\Model\Message::class)
        );
    }
}