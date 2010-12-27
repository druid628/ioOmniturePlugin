<?php

class ioOmnitureServiceTest extends PHPUnit_Framework_TestCase
{
  /**
   * Mocked
   *
   * @var sfEventDispatcher
   */
  protected $dispatcher;

  /**
   * Mocked
   *
   * @var sfWebRequest
   */
  protected $request;

  /**
   * Mocked
   *
   * @var sfController
   */
  protected $controller;

  /**
   * @var ioOmnitureService
   */
  protected $service;

  public function setup()
  {
    $this->dispatcher = $this->getMock('sfEventDispatcher');
    $this->request = $this->getMock('sfWebRequest', array(), array(), '', false, false);
    $this->controller = $this->getMock('sfController', array(), array(), '', false, false);

    $this->service = new ioOmnitureService($this->dispatcher, $this->request, $this->controller);
  }

  /**
   * @dataProvider isResponseTrackableProvider
   */
  public function testIsResponseTrackable($ajaxRequest, $contentType, $statusCode, $headerOnly, $renderMode, $expects)
  {
    $response = $this->getMock('sfWebResponse', array(), array(), '', false, false);

    $this->request->expects($this->any())
      ->method('isXmlHttpRequest')
      ->will($this->returnValue($ajaxRequest));

    $response->expects($this->any())
      ->method('getContentType')
      ->will($this->returnValue($contentType));

    $response->expects($this->any())
      ->method('getStatusCode')
      ->will($this->returnValue($statusCode));

    $response->expects($this->any())
      ->method('isHeaderOnly')
      ->will($this->returnValue($headerOnly));

    $this->controller->expects($this->any())
      ->method('getRenderMode')
      ->will($this->returnValue($renderMode));

    $this->assertEquals($expects, $this->service->isResponseTrackable($response));
  }

  public function isResponseTrackableProvider()
  {
    return array(
      array(false, 'html', 200, false, sfView::RENDER_CLIENT, true),    // good
      array(true, 'html', 200, false, sfView::RENDER_CLIENT, false),    // ajax
      array(false, 'json', 200, false, sfView::RENDER_CLIENT, false),   // non-html content
      array(false, 'html', 301, false, sfView::RENDER_CLIENT, false),   // non 301 response
      array(false, 'html', 302, false, sfView::RENDER_CLIENT, false),   // non 302 response
      array(false, 'html', 304, false, sfView::RENDER_CLIENT, false),   // non 304 response
      array(false, 'html', 200, true, sfView::RENDER_CLIENT, false),    // header only
      array(false, 'html', 200, false, sfView::RENDER_NONE, false),     // render mode non-client
    );
  }

  // the response is not trackable and so the tracking code is not inserted
  public function testApplyTrackerToResponseNotTrackable()
  {
    $response = $this->getMock('sfWebResponse', array(), array(), '', false, false);
    $tracker = $this->getMock('ioOmnitureTracker', array(), array(), '', false, false);
    $tracker->expects($this->never())
      ->method('insert');

    $service = $this->getMock('ioOmnitureService', array('isResponseTrackable'), array(), '', false, false);
    $service->expects($this->once())
      ->method('isResponseTrackable')
      ->with($response)
      ->will($this->returnValue(false));

    $newContent = $service->applyTrackerToResponse($response, 'response_content', $tracker);

    $this->assertEquals('response_content', $newContent, 'The original response content is returned');
  }

  // the tracker is diasbled and the tracking code is not inserted
  public function testApplyTrackerToResponseDisabled()
  {
    $response = $this->getMock('sfWebResponse', array(), array(), '', false, false);
    $tracker = $this->getMock('ioOmnitureTracker', array(), array(), '', false, false);

    // tracker::insert() is never called because the tracker is disabled
    $tracker->expects($this->never())
      ->method('insert');
    $tracker->expects($this->once())
      ->method('isEnabled')
      ->will($this->returnValue(false));

    $service = $this->getMock('ioOmnitureService', array('isResponseTrackable'), array(), '', false, false);
    $service->expects($this->once())
      ->method('isResponseTrackable')
      ->with($response)
      ->will($this->returnValue(true));

    $newContent = $service->applyTrackerToResponse($response, 'response_content', $tracker);

    $this->assertEquals('response_content', $newContent, 'The original response content is returned');
  }

  // the tracker successfully inserts the tracking code
  public function testApplyTrackerToResponseSuccessfully()
  {
    $response = $this->getMock('sfWebResponse', array(), array(), '', false, false);
    $tracker = $this->getMock('ioOmnitureTracker', array(), array(), '', false, false);

    // now we expect the insert to be called
    $tracker->expects($this->once())
      ->method('insert')
      ->with('response_content')
      ->will($this->returnValue('changed_content'));
    $tracker->expects($this->once())
      ->method('isEnabled')
      ->will($this->returnValue(true));

    $service = $this->getMock('ioOmnitureService', array('isResponseTrackable'), array(), '', false, false);
    $service->expects($this->once())
      ->method('isResponseTrackable')
      ->with($response)
      ->will($this->returnValue(true));

    $newContent = $service->applyTrackerToResponse($response, 'response_content', $tracker);

    $this->assertEquals('changed_content', $newContent, 'The content from the tracker is returned');
  }

  public function testLogging()
  {
    // log twice, but turn logging off once, so the logging event should only be called once
    $this->dispatcher->expects($this->once())
      ->method('notify');

    $this->service->enableLogging(true);
    $this->service->log('subject', 'message');

    $this->service->enableLogging(false);
    $this->service->log('subject2', 'message2');
  }
}