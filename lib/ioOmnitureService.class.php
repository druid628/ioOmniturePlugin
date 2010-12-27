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
   * Applies the tracker service to the response object - effectively adding
   * the tracking code.
   *
   * @param sfWebResponse $response
   * @param ioOmnitureTracker $tracker
   * @return void
   */
  public function applyTrackerToResponse(sfWebResponse $response, ioOmnitureTracker $tracker)
  {
    
  }
}