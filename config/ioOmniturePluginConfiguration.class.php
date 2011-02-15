<?php

/**
 * Plugin configuration for the ioOmniture plugin - hooks into several events
 * 
 * @package     ioOmniturePlugin
 * @subpackage  configuration
 * @author      Ryan Weaver <ryan.weaver@iostudio.com>
 * @since       2009-12-18
 * @version     svn:$Id$ $Author$
 */
class ioOmniturePluginConfiguration extends sfPluginConfiguration
{
  /**
   * @var ioOmnitureTracker
   */
  protected $_ioOmnitureTracker;

  /**
   * @var sfContext
   */
  protected $_context;

  /**
   * @var ioOmnitureService
   */
  protected $service;

  /**
   * We listen to a variety of events here
   */
  public function initialize()
  {
    $listener = array($this, 'observe');

    // add ->getOmnitureTracker() to several core factories
    $this->dispatcher->connect('request.method_not_found', $listener);
    $this->dispatcher->connect('response.method_not_found', $listener);
    $this->dispatcher->connect('component.method_not_found', $listener);
    $this->dispatcher->connect('user.method_not_found', $listener);

    // core events needed to make the plugin work
    $this->dispatcher->connect('response.filter_content', array($this, 'listenToResponseFilterContent'));
    $this->dispatcher->connect('context.load_factories', array($this, 'listenToContextLoadFactories'));
  }

  /**
   * Returns the ioOmnitureTracker, which houses the core API for manipulating
   * the Omniture tracking code.
   *
   * @return ioOmnitureTracker
   */
  public function getOmnitureTracker()
  {
    if ($this->_ioOmnitureTracker === null)
    {
      if (!$this->_context)
      {
        throw new sfException('The omniture tracker cannot be created before the factories are loaded');
      }

      $this->_ioOmnitureTracker = $this->createOmnitureTracker($this->_context->getUser());
    }

    return $this->_ioOmnitureTracker;
  }

  /**
   * Generic observer for several *.method_not_found listeners.
   *
   * This effectively adds the getOmnitureTracker() and setOmnitureTracker()
   * to several methods.
   *
   * @param sfEvent $event
   */
  public function observe(sfEvent $event)
  {
    switch ($event['method'])
    {
      case 'getOmnitureTracker':
        $event->setReturnValue($this->getOmnitureTracker());
        return true;
      
      case 'setOmnitureTracker':
        $this->_ioOmnitureTracker = $event['arguments'][0];
        return true;
    }
  }
  
  /**
   * Binds the context to this class.
   *
   * @param  sfEvent  $event
   */
  public function listenToContextLoadFactories(sfEvent $event)
  {
    $this->_context = $event->getSubject();
  }

  /**
   * Creates and returns a new instance of the omniture tracker.
   *
   * This reads callables from the session and applies them to the tracker.
   *
   * @return ioOmnitureTracker
   */
  protected function createOmnitureTracker(sfUser $user)
  {
    // Create the tracker
    $class    = sfConfig::get('app_io_omniture_plugin_tracker_class', 'ioOmnitureTracker');
    $account  = sfConfig::get('app_io_omniture_plugin_account');
    if (!$account)
    {
      throw new InvalidArgumentException('The app_io_omniture_plugin_account config parameter is missing.');
    }

    $config   = sfConfig::get('app_io_omniture_plugin_params', array());
    $tracker  = new $class($account, $config);

    // pull callables from session storage
    $callables = $user->getAttribute('callables', array(), 'io_omniture_plugin');
    foreach ($callables as $callable)
    {
      list($method, $arguments) = $callable;
      call_user_func_array(array($tracker, $method), $arguments);
    }
    $user->setAttribute('callables', array(), 'io_omniture_plugin');

    return $tracker;
  }

  /**
   * Listens to the response.filter_content and adds the tracking code.
   *
   * @param sfEvent $event
   * @param  string $content
   * @return string
   */
  public function listenToResponseFilterContent(sfEvent $event, $content)
  {
    $response = $event->getSubject();
    $tracker = $this->getOmnitureTracker();

    if (sfConfig::get('app_io_omniture_plugin_handle_404') && $response->getStatusCode() == '404')
    {
      $tracker->setPageType('errorPage');
    }

    return $this->getOmnitureService()->applyTrackerToResponse(
      $response,
      $content,
      $tracker
    );
  }

  /**
   * @return ioOmnitureService
   */
  public function getOmnitureService()
  {
    if ($this->service === null)
    {
      if ($this->_context === null)
      {
        throw new sfException('Cannot create the omniture service before the factories have been loaded');
      }

      $class = sfConfig::get('app_io_omniture_plugin_service_class', 'ioOmnitureService');
      $this->service = new $class(
        $this->dispatcher,
        $this->_context->getRequest(),
        $this->_context->getController(),
        sfConfig::get('sf_logging_enabled')
      );
    }

    return $this->service;
  }
}
