<?php

class ioOmnitureTrackerTest extends PHPUnit_Framework_TestCase
{
  /**
   * @var ioOmnitureTracker
   */
  protected $tracker;

  /**
   * A mocked user
   *
   * @var sfUser
   */
  protected $user;

  public function setup()
  {
    $this->tracker = new ioOmnitureTracker('testing');
    $this->user = $this->getMock('sfUser', array(), array(), '', false, false);
  }

  public function testConstructor()
  {
    
  }

  public function testGetSetUser()
  {
    
  }

  /**
   * @dataProvider basicGettersSettersProvider
   */
  public function testBasicGettersSetters($setter, $getter, $value)
  {
    $this->tracker->setUser($this->user);

    // test the normal getter and setter
    $this->tracker->$setter($value);
    $this->assertEquals($value, $this->tracker->$getter(), sprintf('The "%s" getter and "%s" setter work correctly', $getter, $setter));

    // "plant" a variable - it should not be set on the tracker, but should be
    // set as a user variable
    $existingCallables = array(array('fakePlant1', array('foo')));

    // setup what the new callables will look like - the setPageName plant
    // has two args - the value and an empty array for the options
    $newCallables = $existingCallables;
    $newCallables[] = array($setter, array($value, array()));

    $this->user->expects($this->once())
      ->method('setAttribute')
      ->with('callables', $newCallables);

    $this->user->expects($this->atLeastOnce())
      ->method('getAttribute')
      ->will($this->returnValue($existingCallables));

    $this->tracker->$setter('something_different');
    $this->tracker->$setter($value, array('use_flash' => true));
    $this->assertEquals('something_different', $this->tracker->$getter());
  }

  public function basicGettersSettersProvider()
  {
    return array(
      array('setPageName', 'getPageName', 'test_page'),
      array('setPageType', 'getPageType', 'test_page_type'),
      array('setReferrer', 'getReferrer', 'test_referrer'),
      array('setTransactionId', 'getTransactionId', 'transaction_id'),
      array('setZip', 'getZip', 'test_zip'),
      array('setState', 'getState', 'test_state'),
    );
  }
}