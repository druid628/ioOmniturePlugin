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
   * @var sfContext
   * @var sfNamespacedParameterHolder
   */
  protected
    $context                  = null,
    $parameterHolder          = null;
  
  /**
   * A list of different parameters for the tracker
   */
  
  /**
   * @var boolean Whether the tracker is enabled or not
   * @var string  The account name to use with the account
   * @var string  "top" or "bottom" - where to insert the code
   */
  protected  
    $enabled                  = false,
    $account                  = null,
    $insertion                = null,
    $includeJavascript        = null;
  
  /**
   * @var string  The name of the page, if other than the url
   * @var string  The referrer if you need to set it manually
   * @var string  The transactionID
   */
  protected
    $pageName                 = null,
    $referrer                 = null,
    $transactionId            = null,
    $zip                      = null,
    $state                    = null;
  
  /**
   * @var array   An array of event numbers that should be output via the
   *              s.events="event#" call. This is an array where the event
   *              number is the key and a true/false boolean is the value
   * @var array   An array of s.prop# variables that should be set. This
   *              array takes the form array(5 => 'search term'), which
   *              sets s.prop5="seach term"
   * @var array   An array of s.eVar$ variables that should be set. This
   *              is an array like the props array
   */
  protected
    $events                   = array(),
    $props                    = array(),
    $eVars                    = array();
  
  public function __construct(sfContext $context, $options = array())
  {
    $this->initialize($context, $options);
  }
  
  public function initialize(sfContext $context, $options)
  {
    $this->context = $context;
    
    $this->parameterHolder = new sfNamespacedParameterHolder();
    
    $this->configure($options);
  }
  
  /**
   * Extract options used by tracker's helper functions.
   * 
   * View options include:
   * 
   *  * track_as
   *  * is_route
   *  * is_event
   *  * use_linker
   * 
   * @param   array $options
   * 
   * @return  array
   */
  public function extractViewOptions(& $options)
  {
    $viewOptions = array();
    
    foreach (array('track_as', 'is_route', 'is_event', 'use_linker') as $option)
    {
      if (isset($options[$option]))
      {
        $viewOptions[$option] = $options[$option];
        unset($options[$option]);
      }
    }
    
    return $viewOptions;
  }
  
  /**
   * isTrackable
   *
   * Response is not trackable:
   *  - for XHR requests
   *  - if not HTML
   *  - if 304
   *  - if 301, 302 - if this is a redirect 
   *  - if not rendering to the client
   *  - if HTTP headers only
   * @return bool
   */
  public function responseIsTrackable()
  {
    $request    = $this->context->getRequest();
    $response   = $this->context->getResponse();
    $controller = $this->context->getController();

    if ($request->isXmlHttpRequest() ||
        strpos($response->getContentType(), 'html') === false ||
        $response->getStatusCode() == 304 ||
        in_array($response->getStatusCode(), array(302, 301)) || 
        $controller->getRenderMode() != sfView::RENDER_CLIENT ||
        $response->isHeaderOnly())
    {
      return false;
    }
    else
    {
      return true;
    }
  }
  
  /**
   * Insert tracking code into a response.
   * 
   * @param   sfResponse $response
   */
  public function insert(sfResponse $response, $content = null)
  {
    if (!$content) 
    {
      $content = $response->getContent();
    }
    
    $html = array();
    $html[] = '<!-- SiteCatalyst code version: H.20.3';
    $html[] = 'Copyright 1997-2009 Omniture, Inc. More info available at';
    $html[] = 'http://www.omniture.com -->';
    $html[] = '<script type="text/javascript">';
    $html[] = sprintf('  var s_account="%s";', $this->getAccount());
    $html[] = '</script>';
    
    if ($this->getIncludeJavascript())
    {
      // sometimes the helpers aren't loaded
      sfApplicationConfiguration::getActive()->loadHelpers(array('Asset', 'Tag'));
      
      $html[] = javascript_include_tag($this->getSCodePath());
    }
    $html[] = '<script type="text/javascript"><!--';
    
    // custom variable setting code
    if ($response->getStatusCode() == '404')
    {
      $html[] = 's.pageType="errorPage";';
    }
    
    if ($this->getPageName())
    {
      $html[] = sprintf('s.pageName="%s"', $this->getPageName());
    }
    
    if ($this->getReferrer())
    {
      $html[] = sprintf('s.referrer="%s"', $this->getReferrer());
    }
    
    if ($this->getTransactionId())
    {
      $html[] = sprintf('s.transactionID="%s"', $this->getTransactionId());
    }

    if ($this->getState())
    {
      $html[] = sprintf('s.state="%s"', $this->getState());
    }

    if ($this->getZip())
    {
      $html[] = sprintf('s.zip="%s"', $this->getZip());
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
    $html[] = sprintf('s.events="%s"', implode(',', $events_list));
    
    // add the s.prop# values
    foreach($this->props as $propNumber => $propValue)
    {
      $html[] = sprintf('s.prop%s="%s"', $propNumber, $propValue);
    }
    
    // add the s.eVar# values
    foreach($this->eVars as $eVarNumber => $eVarValue)
    {
      $html[] = sprintf('s.eVar%s="%s"', $eVarNumber, $eVarValue);
    }
    
    $html[] = '/************* DO NOT ALTER ANYTHING BELOW THIS LINE ! **************/';
    $html[] = 'var s_code=s.t();if(s_code)document.write(s_code)//--></script>';
    $html[] = '<!-- End SiteCatalyst code version: H.20.3 -->';
    
    $html = join("\n", $html);
    $this->doInsert($response, $content, $html, $this->insertion);
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
   * Insert content into a response.
   * 
   * @param   sfResponse $response
   * @param   string $content
   * @param   string $position
   */
  protected function doInsert(sfResponse $response, $content, $html, $position = null)
  {
    if ($position == null)
    {
      $position = self::POSITION_BOTTOM;
    }
    
    // check for overload
    $method = 'doInsert'.$position;
    
    if (method_exists($this, $method))
    {
      call_user_func(array($this, $method), $response, $content);
    }
    else
    {
      switch ($position)
      {
        case self::POSITION_TOP:
        $new = preg_replace('/<body[^>]*>/i', "$0\n".$html."\n", $content, 1);
        break;
        
        case self::POSITION_BOTTOM:
        $new = str_ireplace('</body>', "\n".$html."\n</body>", $content);
        break;
      }
      
      if ($content == $new)
      {
        $new .= $html;
      }
      
      $response->setContent($new);
    }
  }
  
  /**
   * Apply common options to a value.
   * 
   * @param   mixed $value
   * @param   mixed $options
   * 
   * @return  bool  whether to continue execution
   */
  protected function prepare(& $value, & $options = array())
  {
    if (is_string($options))
    {
      $options = sfToolkit::stringToArray($options);
    }
    
    if (isset($options['use_flash']) && $options['use_flash'])
    {
      unset($options['use_flash']);
      
      $trace = debug_backtrace();
      
      $caller = $trace[1];
      $this->plant($caller['function'], array($value, $options));
      
      return false;
    }
    else
    {
      if (is_string($value) && isset($options['is_route']) && $options['is_route'])
      {
        $value = $this->context->getController()->genUrl($value);
        unset($options['is_route']);
      }
      
      return true;
    }
  }
  
  /**
   * Plant a callable to be executed against the next request's tracker.
   * 
   * @param   string $method
   * @param   array $arguments
   */
  protected function plant($method, $arguments = array())
  {
    $user = $this->getContext()->getUser();
    
    $callables = $user->getAttributeHolder()->get('callables', array(), 'io_omniture_plugin');
    $callables[] = array($method, $arguments);
    
    $user->getAttributeHolder()->set('callables', $callables, 'io_omniture_plugin');
  }
  
  /**
   * Escape the provided value for Javascript evaluation.
   * 
   * @param   string $value
   * 
   * @return  string
   */
  protected function escape($value)
  {
    if (function_exists('json_encode'))
    {
      $escaped = json_encode($value);
    }
    else
    {
      sfLoader::loadHelpers(array('Escaping'));
      $escaped = '"'.esc_js($value).'"';
    }
    
    return $escaped;
  }
  
  /**
   * Apply non-null configuration values.
   * 
   * @param   array $params
   */
  public function configure($params)
  {
    $params = array_merge(array(
      'enabled'                     => null,
      'insertion'                   => null,
      'account'                     => null,
      'page_name'                   => null,
      'domain_name'                 => null,
      'detect_flash_policy'         => null,
      'include_javascript'          => null), $params);
    
    if (!is_null($params['enabled']))
    {
      $this->setEnabled($params['enabled']);
    }
    
    if (!is_null($params['account']))
    {
      $this->setAccount($params['account']);
    }
    
    if (!is_null($params['page_name']))
    {
      $this->setPageName($params['page_name']);
    }
    
    if (!is_null($params['insertion']))
    {
      $this->setInsertion($params['insertion']);
    }
    
    if (!is_null($params['include_javascript']))
    {
      $this->setIncludeJavascript($params['include_javascript']);
    }
  }
  
  /**
   * @return sfContext
   */
  public function getContext()
  {
    return $this->context;
  }
  
  /**
   * @return sfNamespacedParameterHolder
   */
  public function getParameterHolder()
  {
    return $this->parameterHolder;
  }
  
  /**
   * @return string
   */
  public function getParameter($name, $default = null, $ns = null)
  {
    return $this->parameterHolder->get($name, $default, $ns);
  }
  
  /**
   * @return boolean
   */
  public function hasParameter($name, $ns = null)
  {
    return $this->parameterHolder->has($name, $ns);
  }
  
  public function setParameter($name, $value, $ns = null)
  {
    return $this->parameterHolder->set($name, $value, $ns);
  }
  
  /**
   * Toggle tracker's enabled state.
   * 
   * @param   bool $enabled
   */
  public function setEnabled($enabled)
  {
    $this->enabled = (bool) $enabled;
  }
  
  /**
   * @return boolean
   */
  public function isEnabled()
  {
    return $this->enabled;
  }
  
  /**
   * Set the account to use for this tracker.
   * 
   * @param   string $account
   */
  public function setAccount($account)
  {
    $this->account = $account;
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
   * Set whether or not to include javascript
   * 
   * @param   string $account
   */
  public function setIncludeJavascript($includeJavascript)
  {
    $this->includeJavascript = $includeJavascript;
  }
  
  /**
   * Returns whether or not to include javascript
   * 
   * @return string
   */
  public function getIncludeJavascript()
  {
    return $this->includeJavascript;
  }
  
  /**
   * Set where the tracking code should be inserted into the response.
   * 
   * @param   string $insertion
   * @param   array $options
   */
  public function setInsertion($insertion, $options = array())
  {
    if ($this->prepare($insertion, $options))
    {
      $this->insertion = $insertion;
    }
  }
  
  /**
   * Returns the insertion location (bottom or top)
   * 
   * @return string
   */
  public function getInsertion()
  {
    return $this->insertion;
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
   * Getter for the referrer, if it's been set
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
}
