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
    return $this->_ioOmnitureTracker;
  }
  
  public static function observe(sfEvent $event)
  {
    $subject = $event->getSubject();
    
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
   * Automatic plugin modules and helper loading
   *
   * @param  sfEvent  $event
   */
  public function listenToContextLoadFactories(sfEvent $event)
  {
    $this->_context  = $event->getSubject();
    
    // Create the tracker
    $class    = sfConfig::get('app_omniture_tracker_class', 'ioOmnitureTracker');
    $config   = sfConfig::get('app_omniture_params', array());
    $tracker  = new $class($this->_context, $config);
    
    // pull callables from session storage
    $callables = $this->_context->getUser()->getAttribute('callables', array(), 'io_omniture_plugin');
    foreach ($callables as $callable)
    {
      list($method, $arguments) = $callable;
      call_user_func_array(array($tracker, $method), $arguments);
    }

    // Set the tracker to the request
    $this->_context->getUser()->getAttributeHolder()->removeNamespace('io_omniture_plugin');
    $this->_ioOmnitureTracker = $tracker;
  }
  
  public function listenToResponseFilterContent(sfEvent $event, $content)
  {
    $response = $event->getSubject();

    $tracker  = $this->_ioOmnitureTracker;
    
    // insert tracking code
    if ($tracker->responseIsTrackable() && $tracker->isEnabled())
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
}
