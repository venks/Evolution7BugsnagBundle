<?php
/*
 * This file is part of the Evolution7BugsnagBundle.
 *
 * (c) Evolution 7 <http://www.evolution7.com.au>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Evolution7\BugsnagBundle\Bugsnag;

use Evolution7\BugsnagBundle\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Evolution7\BugsnagBundle\ReleaseStage\ReleaseStageInterface;

/**
 * The BugsnagBundle Client Loader.
 *
 * This class assists in the loading of the bugsnag Client class.
 *
 */
class ClientLoader
{
    protected $enabled = false;
    private $bugsnagClient;

    /**
     * Constructor to set up and configure the Bugsnag_Client
     *
     * @param \Bugsnag_Client                                          $bugsnagClient
     * @param ReleaseStageInterface                                    $releaseStageClass
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct(\Bugsnag_Client $bugsnagClient, ReleaseStageInterface $releaseStageClass, ContainerInterface $container)
    {
        $this->bugsnagClient = $bugsnagClient;

        // Report only if the kernel environment matches one of the enabled_stages
        if (in_array($container->getParameter('kernel.environment'), $container->getParameter('bugsnag.enabled_stages'))) {
            $this->enabled = true;
        }

        // Set up the Bugsnag client
        $this->bugsnagClient->setReleaseStage($releaseStageClass->get());
        $this->bugsnagClient->setNotifyReleaseStages($container->getParameter('bugsnag.notify_stages'));
        $this->bugsnagClient->setProjectRoot(realpath($container->getParameter('kernel.root_dir').'/..'));

        if ($container->hasParameter('bugsnag.user') && $container->has($container->getParameter('bugsnag.user'))) {
            $service = $container->get($container->getParameter('bugsnag.user'));
            if ($service instanceof UserInterface) {
                $this->bugsnagClient->setUser($service->getUserAsArray());
            }
        }

        // If the proxy settings are configured, provide these to the Bugsnag client
        if ($container->hasParameter('bugsnag.proxy')) {
            $this->bugsnagClient->setProxySettings($container->getParameter('bugsnag.proxy'));
        }

        // app version
        if ($container->hasParameter('bugsnag.app_version')) {
            $this->bugsnagClient->setAppVersion($container->getParameter('bugsnag.app_version'));
        }

        // Set up result array
        $metaData = array(
            'Symfony' => array()
        );

        // Get and add controller information, if available
        if ($container->isScopeActive('request')) {
            $request = $container->get('request');
            $controller = $request->attributes->get('_controller');

            if ($controller !== null) {
                $metaData['Symfony'] = array('Controller' => $controller);
            }

            $metaData['Symfony']['Route'] = $request->get('_route');

            // Json types transmit params differently.
            if ($request->getContentType() == 'json') {
                $metaData['request']['params'] = $request->request->all();
            }

            $this->bugsnagClient->setMetaData($metaData);
        }
    }

    /**
     * Deal with Exceptions
     *
     * @param \Exception $exception
     * @param array|null $metadata
     * @param string $severity
     */
    public function notifyOnException(\Exception $exception, $metadata = null, $severity = 'error')
    {
        if ($this->enabled) {
            $this->bugsnagClient->notifyException($exception, $metadata, $severity);
        }
    }

    /**
     * Deal with errors
     *
     * @param string $message  Error message
     * @param array  $metadata Metadata to be provided
     * @param string|null $severity
     */
    public function notifyOnError($message, Array $metadata = null, $severity = null)
    {
        if ($this->enabled) {
            $this->bugsnagClient->notifyError('Error', $message, $metadata, $severity);
        }
    }
}
