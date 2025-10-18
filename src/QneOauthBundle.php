<?php

namespace Oxodao\QneOAuthBundle;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class QneOauthBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $node */
        $node = $definition->rootNode();

        $node
            ->children()
            ->scalarNode('url')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('redirect_url')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('role_parser')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('login_url_as_json')->defaultFalse()->end()
            ->scalarNode('user_entity')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('user_updater')->isRequired()->cannotBeEmpty()->end()
            ->end()
        ;
    }

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()->set('qne_oauth.url', $config['url']);
        $container->parameters()->set('qne_oauth.client_id', $config['client_id']);
        $container->parameters()->set('qne_oauth.client_secret', $config['client_secret']);
        $container->parameters()->set('qne_oauth.redirect_url', $config['redirect_url']);
        $container->parameters()->set('qne_oauth.login_url_as_json', $config['login_url_as_json']);
        $container->parameters()->set('qne_oauth.user_entity', $config['user_entity']);

        // why the fuck the new bundle making way prevents from loading the service.yaml from anywhere
        // i tried all paths >:(
        $container->import('./Resources/config/services.yaml');

        $container->services()->alias('qne_oauth.role_parser', \ltrim($config['role_parser'], '@'));
        $container->services()->alias('qne_oauth.user_updater', \ltrim($config['user_updater'], '@'));
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
