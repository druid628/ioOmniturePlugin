<?php

class ioOmnitureTrackerTest extends PHPUnit_Framework_TestCase
{
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
    // tests getters and setters WITH plant
  }

  public function basicGettersSettersProvider()
  {
    return array(
      array('setPageName', 'getPageName', 'test_page'),
      array('setReferrer', 'getReferrer', 'test_referrer'),
      array('setTransactionId', 'getTransactionId', 'transaction_id'),
      array('setZip', 'getZip', 'test_zip'),
      array('setState', 'getState', 'test_state'),
    );
  }
}