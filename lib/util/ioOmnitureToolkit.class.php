<?php

/**
 * Static utility methods.
 * 
 * Built originally from sfGoogleAnalyticsPlugin
 * 
 * @package     ioOmniturePlugin
 * @subpackage  util
 * @author      Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author      Ryan Weaver <ryan.weaver@iostudio.com>
 * @version     svn:$Id$ $Author$
 */
class ioOmnitureToolkit
{
  /**
   * Log a message.
   * 
   * @param   mixed   $subject
   * @param   string  $message
   * @param   string  $priority
   */
  static public function logMessage($subject, $message, $priority = 'info')
  {
    ProjectConfiguration::getActive()->getEventDispatcher()->notify(
      new sfEvent(
        $subject,
        'application.log',
        array($message, 'priority' => constant('sfLogger::'.strtoupper($priority)))
      )
    );
  }
}
