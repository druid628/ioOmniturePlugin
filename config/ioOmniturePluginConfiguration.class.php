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
    
    $this->dispatcher->connect('request.method_not_found', $listener);
    $this->dispatcher->connect('response.method_not_found', $listener);
    $this->dispatcher->connect('component.method_not_found', $listener);
    $this->dispatcher->connect('user.method_not_found', $listener);
    
    $this->dispatcher->connect('response.filter_content', array($this, 'listenToResponseFilterContent'));
    $this->dispatcher->connect('context.load_factories', array($this, 'listenToContextLoadFactories'));
  }

  /**
   * Returns the ioOmnitureTracker, which houses the core API for manipulating the Omniture tracking code.
   *
   * @return ioEditableContentService
   */
  public function getOmnitureTracker()
  {
    if ($this->_ioOmnitureTracker === null)
    {
      throw new sfException('Omniture tracker is not yet available');
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
   * Binds the context to this
   *
   * @param  sfEvent  $event
   */
  public function listenToContextLoadFactories(sfEvent $event)
  {
    $this->_context = $event->getSubject();
    $this->_ioOmnitureTracker = $this->createOmnitureTracker($this->_context->getUser());
  }

  /**
   * Creates and returns a new instance of the omniture tracker.
   *
   * @return ioOmnitureTracker
   */
  protected function createOmnitureTracker(sfUser $user)
  {
    // Create the tracker
    $class    = sfConfig::get('app_io_omniture_plugin_tracker_class', 'ioOmnitureTracker');
    $config   = sfConfig::get('app_io_omniture_plugin_params', array());
    $tracker  = new $class($user, $config);

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
    $tracker  = $this->getOmnitureTracker();

    // insert tracking code
    if ($this->responseIsTrackable() && (true || $tracker->isEnabled()))
    {
      if (sfConfig::get('sf_logging_enabled'))
      {
        ioOmnitureToolkit::logMessage($this, 'Inserting tracking code.');
      }
      
      $tracker->insert($response, $content);
      
      return $response->getContent();
    }
    elseif (sfConfig::get('sf_logging_enabled'))
    {
      ioOmnitureToolkit::logMessage($this, 'Tracking code not inserted.');
    }

    return $content;
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
        $this->_context->getResponse(),
        $this->_context->getController()
      );
    }

    return $this->service;
  }
}
