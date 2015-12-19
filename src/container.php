<?php

$container = new League\Container\Container;

// Register the request object singleton to be used later in the request cyncle
$container->singleton('Symfony\Component\HttpFoundation\Request', function () {
    return Symfony\Component\HttpFoundation\Request::createFromGlobals();
});

// Service Providers
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\ConfigServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\DatabaseServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\TemplateServiceProvider');
$container->addServiceProvider('Ps2alerts\Api\ServiceProvider\RedisServiceProvider');

// Inflectors
$container->inflector('Ps2alerts\Api\Contract\ConfigAwareInterface')
          ->invokeMethod('setConfig', ['config']);
$container->inflector('Ps2alerts\Api\Contract\DatabaseAwareInterface')
          ->invokeMethod('setDatabaseDriver', ['Aura\Sql']);
$container->inflector('Ps2alerts\Api\Contract\TemplateAwareInterface')
          ->invokeMethod('setTemplateDriver', ['Twig_Environment']);
$container->inflector('Ps2alerts\Api\Contract\RedisAwareInterface')
          ->invokeMethod('setRedisDriver', ['redis']);

// Repositories
$container->add('Ps2alerts\Api\Repository\ResultRepository');
// Metrics Repositories
$container->add('Ps2alerts\Api\Repository\Metrics\MapRepository');
$container->add('Ps2alerts\Api\Repository\Metrics\MapInitialRepository');
$container->add('Ps2alerts\Api\Repository\Metrics\OutfitRepository');

// Loaders
$container->add('Ps2alerts\Api\Loader\Metrics\MapMetricsLoader')
          ->withArgument('Ps2alerts\Api\Repository\Metrics\MapRepository');
$container->add('Ps2alerts\Api\Loader\Metrics\MapInitialMetricsLoader')
          ->withArgument('Ps2alerts\Api\Repository\Metrics\MapInitialRepository');
$container->add('Ps2alerts\Api\Loader\Metrics\OutfitMetricsLoader')
          ->withArgument('Ps2alerts\Api\Repository\Metrics\OutfitRepository');
$container->add('Ps2alerts\Api\Loader\ResultLoader')
          ->withArgument('Ps2alerts\Api\Repository\ResultRepository');

// Endpoint Injectors
$container->add('Ps2alerts\Api\Controller\Alerts\ResultsEndpointController')
          ->withArgument('Ps2alerts\Api\Loader\ResultLoader');
// Metrics Endpoints
$container->add('Ps2alerts\Api\Controller\Metrics\MapMetricsEndpoint')
          ->withArgument('Ps2alerts\Api\Loader\Metrics\MapMetricsLoader');
$container->add('Ps2alerts\Api\Controller\Metrics\MapInitialMetricsEndpoint')
          ->withArgument('Ps2alerts\Api\Loader\Metrics\MapInitialMetricsLoader');
$container->add('Ps2alerts\Api\Controller\Metrics\OutfitMetricsEndpoint')
          ->withArgument('Ps2alerts\Api\Loader\Metrics\OutfitMetricsLoader');

// Container Inflector
$container->inflector('League\Container\ContainerAwareInterface')
          ->invokeMethod('setContainer', [$container]);

return $container;
