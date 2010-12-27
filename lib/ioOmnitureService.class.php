<?php

/**
 * General-purpose class for omniture activities
 *
 * @author Ryan Weaver <ryan.weaver@iostudio.com>
 */
class ioOmnitureService
{
  /**
   * @var sfEventDispatcher
   */
  protected $dispatcher;

  /**
   * @var sfWebRequest
   */
  protected $request;

  /**
   * @var sfController
   */
  protected $controller;

  /**
   * Whether to log or not
   *
   * @var bool
   */
  protected $logging;

  /**
   * @param sfWebRequest $request
   * @param sfWebResponse $response
   * @param sfController $controller
   */
  public function __construct(sfEventDispatcher $dispatcher, sfWebRequest $request, sfController $controller, $logging = false)
  {
    $this->dispatcher = $dispatcher;
    $this->request = $request;
    $this->controller = $controller;
    $this->logging = $logging;
  }

  /**
   * Returns whether or not the current request/response should have omniture
   * code added to it.
   *
   * @param sfWebResponse $response
   * @return bool
   */
  public function isResponseTrackable(sfWebResponse $response)
  {
    if ($this->request->isXmlHttpRequest() ||
        strpos($response->getContentType(), 'html') === false ||
        $response->getStatusCode() == 304 ||
        in_array($response->getStatusCode(), array(302, 301)) ||
        $this->controller->getRenderMode() != sfView::RENDER_CLIENT ||
        $response->isHeaderOnly())
    {
      return false;
    }

    return true;
  }

  /**
   * Applies the tracking code to the given content if possible, or just
   * returns the content.
   *
   * @param sfWebResponse $response     The current response factory
   * @param string $content             The actual content to modify and return
   * @param ioOmnitureTracker $tracker  The tracker object that will apply the tracking code
   * @return string The modified content
   */
  public function applyTrackerToResponse(sfWebResponse $response, $content, ioOmnitureTracker $tracker)
  {
    if (!$this->isResponseTrackable($response) || !$tracker->isEnabled())
    {
      $this->log('Tracking code not inserted.');

      return $content;
    }

    $this->log('Tracking code inserted');

    return $tracker->insert($content);
  }

  /**
   * Logs a message if logging is enabled.
   *
   * @param  string $message The message to log
   * @param int $priority The priority for the message
   * @return void
   */
  public function log($message, $priority = sfLogger::INFO)
  {
    if ($this->logging)
    {
      $event = new sfEvent(
        $this,
        'application.log',
        array($message, 'priority' => $priority)
      );

      $this->dispatcher->notify($event);
    }
  }

  /**
   * Enable or disable logging
   *
   * @param  boolean $logging
   * @return void
   */
  public function enableLogging($logging)
  {
    $this->logging = $logging;
  }
}