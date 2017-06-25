<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemLanguageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');

        return new ItemLanguageController(
            $tables->get('item_language'),
            $container->get(\Autowp\TextStorage\Service::class),
            $hydrators->get(\Application\Hydrator\Api\ItemLanguageHydrator::class),
            $container->get(\Application\Model\BrandVehicle::class),
            $container->get(\Application\HostManager::class),
            $filters->get('api_item_language_put')
        );
    }
}