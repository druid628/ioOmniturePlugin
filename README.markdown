ioOmniturePlugin
================

This plugin allows for integration with omniture's tracking software, allowing
you to integrate and output the correct omniture variables.

This plugin was built as a branch of the sfGoogleAnalyticsPlugin.

Basic Usage
-----------

The plugin largely works automatically, appending the necessary code automatically
to the bottom of each page once activated. First, configure the omniture
account the plugin should use:

    # apps/frontend/config/app.yml
    all:
      io_omniture_plugin:
        account:  my_account

There are several other options (see `app.yml` in the plugin), but this is
all you need to get started. You should now see the omniture Javascript
code appear at the bottom of each page.

By default, the omniture Javascript file is loaded from `/js/s_code.js`.
This can be changed by subclassing the `ioOmnitureTracker` class and overriding
the `getSCodePath()` method.

Also, the library is configured to **only output the Javascript code in the
`prod` environment*. This can be changed, but it prevents you from driving
traffic up when developing locally.

More Complex Tracking
---------------------

With Omniture, you can do lot's of things. The ioOmniturePlugin makes many
of these things quite simple.

### Setting `s.prop` variables

One of the most common things to do in omniture is to set `s.prop` variables.
These are generic counter variables. For example, we might configure `prop5`
to be "the total number of times a term is searched for" in your site's search.
You configure this in the omniture control panel and then implement it in
your project.

Let's run with that specific example:

    public function executeSearch(sfWebRequest $request)
    {
        $q = $request->getParameter('q');

        if ($q)
        {
          $this->getOmnitureTracker()->setProp(5, $q);
        }

        // ...
    }

If you look at the resulting page, a new variable will be included in the
omniture Javascript at the bottom of the page:

    s.prop5 = "foo"

### Setting eVar variables

Another common task is to assign "properties" to your user. For example, if
you labeled every user with the US state he/she was from, you could run
reports based on state. User-specific properties are called `eVars`, and
they can be set much in the same way as `s.prop` variables. Suppose we want
to break down reports based on whether or not people used the site search
and that we've configured `eVar9` to hold that data:

    public function executeSearch(sfWebRequest $request)
    {
        $q = $request->getParameter('q');

        if ($q)
        {
          $this->getOmnitureTracker()->setProp(5, $q);
          $this->getOmnitureTracker()->seteVar(9, 1);
        }

        // ...
    }

Once again, you'll see a new Javascript variable at the bottom of the page's
source:

    s.eVar9 = 1

### Throwing events

Finally, omniture events are a very powerful way to break data down by who
has or hasn't performed some event. Common events are "completed transaction".
Let's throw an event, ``event2``, when a user performs a search:

    public function executeSearch(sfWebRequest $request)
    {
        $q = $request->getParameter('q');

        if ($q)
        {
          $this->getOmnitureTracker()->setProp(5, $q);
          $this->getOmnitureTracker()->seteVar(9, 1);

          if ($this->getUser()->hasAttribute('event_search', 'omniture')
          {
            $this->getOmnitureTracker()->activateEvent(2);

            $this->getUser()->setAttribute('event_search', true, 'omniture');
          }
        }

        // ...
    }

Events are slightly different because, in most cases, you don't want to throw
them multiple times per user. Instead, you simply want to record that this
user indeed used the search. I'm not totally sure what happens when you fire
this multiple times, but my impression is that terrible things happen.

Like before, you'll see a new Javascript variable at the bottom of the page's
source:

    s.events = "event2"

The Challenge of Redirects and AJAX
-----------------------------------

Omniture only works you set an omniture variable, and then that same request
renders as a full HTML page. If you set an omniture variable and then redirect
the user or return just an AJAX response (without the full HTML body), omniture
won't work.

These challenges make omniture difficult to deal with, but each can be overcome.

### Omniture and Redirects

Pretend for a moment that you need to throw an event and set an `eVar` after
a form was successfully filled out. If you set these in your action and then
redirect, they'll be lost forever as they don't transfer over to the next
request.

Fortunately, it is possible to temporarily persist omniture variables across
a redirect:

    public function executeProcess(sfWebRequest $request)
    {
        $form = SomeForm();

        // ...

        $this->getOmnitureTracker()->setEvar(5, $foo, array('use_flash' => true));
        $this->getOmnitureTracker()->activateEvent(2, array('use_flash' => true));

        $this->redirect('form_thanks');
    }

When you use the `use_flash` option, the `evar` and `event` are saved to
the session and then reapplied on the next request, after the redirect. The
variables will appear on the Javascript of the *next* page.

### Omniture and AJAX

Setting omniture variables during an AJAX request is a bit more difficult.
Since the full HTML body is never rendered, the ioOmniturePlugin can never
output the necessary code to set the variable. When using AJAX, settting
variables on the omniture tracker is useless.

The correct way to do this is to manually output the variables using an
alternative Javascript method. Here is an example using jQuery:

    <script type="text/javascript">
      $(document).ready(function(){
        var s=s_gi(s_account)

        s.linkTrackEvents="event2";
        s.linkTrackVars="eVar1,eVar9";
        s.eVar1="foo";
        s.evar9="bar";

        // send the information
        s.t();

        // clear out variables
        s.linkTrackEvents=""
        s.events=""
      });
    </script>
