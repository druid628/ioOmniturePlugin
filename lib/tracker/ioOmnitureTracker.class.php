<?php

/**
 * Houses the core API for manipulating the Omniture tracking code.
 * 
 * Built originally from sfGoogleAnalyticsPlugin
 * 
 * @package     ioOmniturePlugin
 * @subpackage  tracker
 * @author      Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author      Ryan Weaver <ryan.weaver@iostudio.com>
 * @version     svn:$Id$ $Author$
 */
class ioOmnitureTracker
{
  const
    POSITION_TOP              = 'top',
    POSITION_BOTTOM           = 'bottom';
  
  /**
   * @var string The account name
   * @var sfUser
   */
  protected
    $account                  = null,
    $user                     = null;
  
  /**
   * The options array
   *
   * @var array
   */
  protected $options = array(
    'enabled'             => false,                   // whether the tracker is enabled or not
    'insertion'           => self::POSITION_BOTTOM,   // where to insert the code
    'include_javascript'  => true,                    // whether to include the actual s_code.js file
  );
  

  /**
   * Various common omniture information
   */
  protected
    $pageName                 = null, // The name of the page, if other than the url
    $referrer                 = null, // The referrer if you need to set it manually
    $transactionId            = null, // The transactionID
    $zip                      = null, // The zip code of the current user
    $state                    = null; // The state of the current user
  
  /**
   * @var array   An array of event numbers that should be output via the
   *              s.events="event#" call. This is an array where the event
   *              number is the key and a true/false boolean is the value
   * @var array   An array of s.prop# variables that should be set. This
   *              array takes the form array(5 => 'search term'), which
   *              sets s.prop5="search term"
   * @var array   An array of s.eVar$ variables that should be set. This
   *              is an array like the props array
   */
  protected
    $events                   = array(),
    $props                    = array(),
    $eVars                    = array();

  /**
   * @param  string $account The account name
   * @param array $options Options for the tracker
   */
  public function __construct($account, $options = array())
  {
    $this->account = $account;

    foreach ($options as $key => $val)
    {
      $this->setOption($key, $val);
    }
  }

  /**
   * Optionally set the user, which allows messages to be "planted" across
   * requests.
   *
   * @param sfUser $user
   */
  public function setUser(sfUser $user)
  {
    $this->user = $user;
  }
  
  /**
   * Insert tracking code into a response.
   */
  public function insert($content)
  {
    $code = array();
    $code[] = '<!-- SiteCatalyst code version: H.20.3';
    $code[] = 'Copyright 1997-2009 Omniture, Inc. More info available at';
    $code[] = 'http://www.omniture.com -->';
    $code[] = '<script type="text/javascript">';
    $code[] = sprintf('  var s_account="%s";', $this->getAccount());
    $code[] = '</script>';
    
    if ($this->getOption('include_javascript', true))
    {
      // sometimes the helpers aren't loaded
      sfApplicationConfiguration::getActive()->loadHelpers(array('Asset', 'Tag'));
      
      $code[] = javascript_include_tag($this->getSCodePath());
    }
    $code[] = '<script type="text/javascript"><!--';
    
    // custom variable setting code
    throw new Exception('This must be re-implemented');
    /*
    if ($response->getStatusCode() == '404')
    {
      $html[] = 's.pageType="errorPage";';
    }
     *
     */

    if ($this->getPageName())
    {
      $code[] = sprintf('s.pageName="%s"', $this->getPageName());
    }
    
    if ($this->getReferrer())
    {
      $code[] = sprintf('s.referrer="%s"', $this->getReferrer());
    }
    
    if ($this->getTransactionId())
    {
      $code[] = sprintf('s.transactionID="%s"', $this->getTransactionId());
    }

    if ($this->getState())
    {
      $code[] = sprintf('s.state="%s"', $this->getState());
    }

    if ($this->getZip())
    {
      $code[] = sprintf('s.zip="%s"', $this->getZip());
    }
    
    // build an array of activated events as the events_list
    $events_list = array();
    foreach($this->events as $eventNumber => $enabled)
    {
      if ($enabled)
      {
        $events_list[] = sprintf('event%s', $eventNumber);
      }
    }
    
    // add the events that are activated to the output html
    $code[] = sprintf('s.events="%s"', implode(',', $events_list));
    
    // add the s.prop# values
    foreach($this->props as $propNumber => $propValue)
    {
      $code[] = sprintf('s.prop%s="%s"', $propNumber, $propValue);
    }
    
    // add the s.eVar# values
    foreach($this->eVars as $eVarNumber => $eVarValue)
    {
      $code[] = sprintf('s.eVar%s="%s"', $eVarNumber, $eVarValue);
    }
    
    $code[] = '/************* DO NOT ALTER ANYTHING BELOW THIS LINE ! **************/';
    $code[] = 'var s_code=s.t();if(s_code)document.write(s_code)//--></script>';
    $code[] = '<!-- End SiteCatalyst code version: H.20.3 -->';
    
    $code = join("\n", $code);

    return $this->doInsert($content, $code, $this->insertion);
  }
  
  /**
   * Returns the path to the main s_code.js file. This is here so that
   * this class can be subclassed and overridden
   */
  protected function getSCodePath()
  {
    return '/js/s_code.js';
  }
  
  /**
   * Insert tracking html into the source html and returns it
   * 
   * @param   string $html The source html content to insert into
   * @param   string $code The tracking code to insert
   * @param   string $position Where to insert the code
   */
  protected function doInsert($html, $code, $position = null)
  {
    if ($position == null)
    {
      $position = self::POSITION_BOTTOM;
    }
    
    // check for overload
    $method = 'doInsert'.$position;
    if (method_exists($this, $method))
    {
      return call_user_func(array($this, $method), $html, $code);
    }

    switch ($position)
    {
      case self::POSITION_TOP:
        $new = preg_replace('/<body[^>]*>/i', "$0\n".$code."\n", $html, 1);
        break;

      case self::POSITION_BOTTOM:
        $new = str_ireplace('</body>', "\n".$code."\n</body>", $html);
        break;

      default:
        throw new InvalidArgumentException(sprintf('Cannot place omniture code with position "%s"', $position));
    }

    // I guess if we couldn't insert, just throw it on the end...
    if ($html == $new)
    {
      $new .= $code;
    }

    return $new;
  }
  
  /**
   * Saves the value to the session if use_flash is specified in the options array.
   * 
   * @param   mixed $value
   * @param   array $options
   * 
   * @return  bool  whether to continue execution
   */
  protected function prepare(& $value, & $options = array())
  {
    if (isset($options['use_flash']) && $options['use_flash'])
    {
      unset($options['use_flash']);
      
      $trace = debug_backtrace();
      
      $caller = $trace[1];
      $this->plant($caller['function'], array($value, $options));
      
      return false;
    }

    return true;
  }
  
  /**
   * Plant a callable to be executed against the next request's tracker.
   *
   * This basically stores a callable on the session that will be called
   * on the tracker on the next request. This is how you can "save" a portion
   * of tracking information to be applied on the next request.
   * 
   * @param   string $method
   * @param   array $arguments
   */
  protected function plant($method, $arguments = array())
  {
    if (!$user = $this->getUser())
    {
      throw new LogicException('Cannot plant values without an injected user.');
    }
    
    $callables = $user->getAttributeHolder()->get('callables', array(), 'io_omniture_plugin');
    $callables[] = array($method, $arguments);
    
    $user->getAttributeHolder()->set('callables', $callables, 'io_omniture_plugin');
  }
  
  /**
   * @return sfUser
   */
  public function getUser()
  {
    return $this->user;
  }

  /**
   * Toggle tracker's enabled state.
   * 
   * @param   bool $enabled
   */
  public function setEnabled($enabled)
  {
    $this->setOption('enabled', $enabled);
  }
  
  /**
   * @return boolean
   */
  public function isEnabled()
  {
    return $this->getOption('enabled', false);
  }
  
  /**
   * Returns the account used for this tracker
   * 
   * @return string
   */
  public function getAccount()
  {
    return $this->account;
  }
  
  /**
   * Define a page other than what's in the address bar.
   * 
   * @param   string $pageName
   * @param   array $options
   */
  public function setPageName($pageName, $options = array())
  {
    if ($this->prepare($pageName, $options))
    {
      $this->pageName = $pageName;
    }
  }
  
  /**
   * @return string
   */
  public function getPageName()
  {
    return $this->pageName;
  }
  
  /**
   * Activates the given event number. For example, passing the parameter
   * "5" would create the s.events="event5" output
   * 
   * @param integer The event number to activate
   */
  public function activateEvent($num, $options = array())
  {
    if ($this->prepare($num, $options))
    {
      $this->events[$num] = true;
    }
  }
  
  /**
   * Deactivates an event (e.g., removes s.event="event5")
   * 
   * @see activateEvent()
   * @param integer The event number to deactivate
   */
  public function deactivateEvent($num)
  {
    $this->events[$num] = false;
  }
  
  /**
   * Sets a particular s.prop# value
   * 
   * @param integer The prop number to set (e.g. 5 for s.prop5)
   * @param string  The value to set the prop to
   */
  public function setProp($num, $value, $options = array())
  {
    $options = array_merge(array('num' => $num), $options);
    
    $this->setPropInternal($value, $options);
  }
  
  /**
   * Used internally to fit in with the single value interface that
   * allows us to use the prepare() function
   * 
   * @see setProp
   */
  public function setPropInternal($value, $options = array())
  {
    if (!isset($options['num']))
    {
      throw new sfException('A "num" number option must be passed');
    }
    
    if ($this->prepare($value, $options))
    {
      $num = $options['num'];
      
      $this->props[$num] = $value;
    }
  }
  
  /**
   * Sets a particular s.eVar# value
   * 
   * @param integer The prop number to set (e.g. 5 for s.eVar5)
   * @param string  The value to set the eVar to
   */
  public function seteVar($num, $value, $options = array())
  {
    $options = array_merge(array('num' => $num), $options);
    
    $this->seteVarInternal($value, $options);
  }
  
  /**
   * Used internally to fit in with the single value interface that
   * allows us to use the prepare() function
   * 
   * @see 
   */
  public function seteVarInternal($value, $options = array())
  {
    if (!isset($options['num']))
    {
      throw new sfException('An "num" number option must be passed');
    }
    
    if ($this->prepare($value, $options))
    {
      $num = $options['num'];
      
      $this->eVars[$num] = $value;
    }
  }
  
  /**
   * Sets the referrer variable if you need to actually output that
   * variable (in a case where you'd need to manually set the referrer)
   */
  public function setReferrer($referrer, $options = array())
  {
    if ($this->prepare($referrer, $options))
    {
      $this->referrer = $referrer;
    }
  }
  
  /**
   * Getter for the referrer, if it has been set
   */
  public function getReferrer()
  {
    return $this->referrer;
  }
  
  /**
   * Sets the transactionId variable
   */
  public function setTransactionId($transactionId, $options = array())
  {
    if ($this->prepare($transactionId, $options))
    {
      $this->transactionId = $transactionId;
    }
  }
  
  /**
   * Getter for the transactionId, if it's been set
   */
  public function getTransactionId()
  {
    return $this->transactionId;
  }

  public function setZip($zip)
  {
    $this->zip = $zip;
  }

  public function getZip()
  {
    return $this->zip;
  }

  public function setState($state)
  {
    $this->state = $state;
  }

  public function getState()
  {
    return $this->state;
  }

  /**
   * Sets an option value
   *
   * @param  string $name The name of the option
   * @param  mixed $value The value to set the option to
   * @return void
   */
  public function setOption($name, $value)
  {
    $this->options[$name] = $value;
  }

  /**
   * @param  string $name The name of the option to retrieve
   * @param mixed $default The value to retrieve if the option does not exist
   * @return mixed
   */
  public function getOption($name, $default = null)
  {
    return isset($this->options[$name]) ? $this->options[$name] : $default;
  }
}
