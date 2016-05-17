<?php

$container = new League\Container\Container;

// Register the request object singleton to be used later in the request cyncle
$container->singleton('Symfony\Component\HttpFoundation\Request', function () {
    return Symfony\Component\HttpFoundation\Request::createFromGlobals();
});

// Service Providers
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\ConfigServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\DatabaseDataServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\DatabaseServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\HttpClientServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\LogServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\RedisServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\TemplateServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\UuidServiceProvider');

// Inflectors
$container->inflector('Ps2alerts\Api\Contract\ConfigAwareInterface')
          ->invokeMethod('setConfig', ['config']);
$container->inflector('Ps2alerts\Api\Contract\DatabaseAwareInterface')
          ->invokeMethod('setDatabaseDriver', ['Database'])
          ->invokeMethod('setDatabaseDataDriver', ['Database\Data']);
$container->inflector('Ps2alerts\Api\Contract\LogAwareInterface')
          ->invokeMethod('setLogDriver', ['Monolog\Logger']);
$container->inflector('Ps2alerts\Api\Contract\HttpClientAwareInterface')
          ->invokeMethod('setHttpClientDriver', ['GuzzleHttp\Client']);
$container->inflector('Ps2alerts\Api\Contract\TemplateAwareInterface')
          ->invokeMethod('setTemplateDriver', ['Twig_Environment']);
$container->inflector('Ps2alerts\Api\Contract\RedisAwareInterface')
          ->invokeMethod('setRedisDriver', ['redis']);
$container->inflector('Ps2alerts\Api\Contract\UuidAwareInterface')
          ->invokeMethod('setUuidDriver', ['Ramsey\Uuid\Uuid']);

// Container Inflector
$container->inflector('League\Container\ContainerAwareInterface')
          ->invokeMethod('setContainer', [$container]);

// Processing deps
$container->add('Ps2alerts\Api\Factory\AuraFactory')
          ->withArgument('Aura\SqlQuery\QueryFactory');

return $container;
