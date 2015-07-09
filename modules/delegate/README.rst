===============
Delegate module
===============

The Delegate module implements two major new functions in SimpleSAMLphp –
delegating the login request to a secondary, remote IdP, and delegating the
actual authentication to other SimpleSAMLphp modules. The Delegate module does
not perform any authentication on its own.


Delegate to a secondary, remote IdP
====================================

This module lets your IdP act as a proxy for other IdPs. There are two
advantages to this approach:

* A uniform user experience – all login redirects are sent to your IdP, and
  there's no need for IdP discovery or WAYF at the SP.
* Simpler SP setup – complexity related to configuring an SP with multiple IdPs
  is transferred to your IdP.

When using the Delegate module, all authentication at a remote IdP, will be
redirected to that IdP, per usual::

    +----------------------------------------------------+
    |                                   +--------------+ |
    | +------+      +-----------+   +-> |  Remote IdP  | |
    | |  SP  | ---> | IdP  +----+   |   +--------------+ |
    | |      |      |      | SP | --+                    |
    | +------+      +------+----+   |   +--------------+ |
    |                               +-> |  Remote IdP  | |
    |                                   +--------------+ |
    +----------------------------------------------------+


The purpose of this is to support secondary login mechanisms without having to
break user experience for the majority of users. In this setup, all users are
redirected to your IdP for authentication, but can from there select to
authenticate with a remote IdP. This approach is tailored to a specific scenario
– when there's a large percentage of users that *doesn't* need to authenticate
at a remote IdP!

Take an organization, for instance, where a high percentage of (but not all)
users are people with local accounts at that organization. The remaining users
needs to authenticate using federated authentication, or some remote IdP. When
using an IdP with SimpleSAMLphp and the Delegate module, all users can be sent
to a login page where these local users can log in directly using some
authentication module (e.g. LDAP). This enables the least number of steps and
hinderance for internal users, like WAYFs at SPs. The much smaller percentage
that need a external source will get one more step in their login cycle.

Having the IdP redirect the user to one or more external choices also
simplifies the configuration and design choices for services with SPs.
They will only ever have one IdP to relate to. The IdP configures and
handles the different delegate possibilities. In effect this centralizes the
more difficult bits of federated authentication to the people best equipped to
understand and handle it.

The Delegate module supports having multiple external IdPs, and makes it
possible to cherry-pick which remote IdPs a SP should have access to in the
configuration.

The Delegate module also supports access control for situations where a user
with a valid session but an invalid realm (authenticated either internally or
externally) accesses the IdP.


Delegating to other modules
============================

The Delegate module is designed to use other SimpleSAMLphp modules to handle
the actual authentication. The idea is to use modules built around a specific
protocol or authentication system as *backends* for delegation. This helps
prevent code duplication and promotes modularity.

At this time the only supported module is the *LDAP* module, but any module
with an API that enables the Delegate module to pass username and password to a
returning function, can be supported. Existing modules can be redesigned to
support this::

    +----------------------------------------------------+
    |                                   +--------------+ |
    |                        +--------> | LDAP-catalog | |
    |                        |          +--------------+ |
    |                        '                           |
    | +------+      +----+------+       +--------------+ |
    | |      |      | I  | LDAP |   +-> |  Remote IdP  | |
    | |  SP  | ---> | d  +-+----+   |   +--------------+ |
    | |      |      | P    | SP | --+                    |
    | +------+      +------+----+   |   +--------------+ |
    |                               +-> |  Remote IdP  | |
    |                                   +--------------+ |
    +----------------------------------------------------+


Implementing a module that supports delegation
----------------------------------------------

To support delegation, modules must use a ``SimpleSAML_Auth_Source`` object as
authentication source. The authentication source must have one ``login``
function that takes two arguments, username and password. The function must
return an array of attributes for the SP.

A simple authentication source that accepts the username 'foo' with the password
'bar' could look like::

    // foobar/lib/Auth/Source/Foobar.php
    class sspmod_foobar_Auth_Source_Foobar extends SimpleSAML_Auth_Source
    {
        public function login($username, $password)
        {
            if ($username === "foo" && $password === "bar") {
                return array('name' => 'Foo');
            }
            throw new SimpleSAML_Error_User('WRONGUSERPASS')
        }
    }

The attributes that a module returns should be configurable, but this is not
mandatory. Note, however, that the SP will receive all attributes that is
returned from the ``login`` method of your ``SimpleSAML_Auth_Source`` class.

Altering behaviour with hooks
=============================

SimpleSAMLphp modules written for use with the Delegate module, can alter the
behaviour or appearence of the Delegate login page.

Typical usage for this would be to negotiate authentication with external
systems (automatic login) [#negotiate]_, or add extra functionality to the login page.
Hooks are called just before the login page is rendered.


Writing hooks for the Delegate module
-------------------------------------

Hooks are stored in the 'hooks' directory of your module, in a file named
'hook_<label>.php'. This file must contain a hook function named
'<module>_hook_<label>'. Hooks for the Delegate plugin are labeled 'loginpage'.

Hooks for the Delegate module login page are passed a reference to an array that
contains data for the login page template. Any extra data that should be passed
to the template, can be added to this array.

A simple hook that prints 'Hello, World!' to the page could look like::

    // foobar/hooks/hook_loginpage.php
    function foobar_hook_loginpage(&$hookinfo)
    {
        assert('is_array($hookinfo)');
        assert('array_key_exists("info", $hookinfo)');

        $hookinfo['info'][] = '<p>Hello, World!</p>';
    }

Any data added to the ``$hookinfo`` array is available in the Delegate template.
The default template for the login page renders all contents of
``$hookinfo['info']`` as is.


Configuration
=============

SP
--
To enable use of the delegate module for your SimpleSAMLphp IdP, you'll have to
edit the metadata configuration file for your IdP,
`config/saml20-idp-hosted.php`. The metadata array for your IdP needs two
additional keys::

    'auth' => 'delegate'
    'class' => 'sspmod_delegate_IdP_ProxyIdP'

The IdP will also need a placeholder SP configuration::

    // metadata/saml20-sp-hosted.php
    $metadata['__DYNAMIC:1__'] = array('host'  => '__DEFAULT__');

In addition, each SP needs additional configuration. This is done in the SP
metadata configuration file, `metadata/saml20-sp-remote.php`. For each array of
SP metadata, you'll need to consider adding the keys:

* ``delegate:remote_idp`` – optional

  This setting enables one or more remote IdPs for the SP.

  The value should be an array of IdPs that should be enabled for the SP. Each
  item in the array is a key that references metadata in
  ``metadata/saml20-idp-remote.php``.

  If no remote IdPs are used, the value should be set to ``null`` or the array
  key omitted.

* ``delegate:backends`` - optional

  This setting enables one or more authentication sources for the SP.

  The value should be an array of authentication sources that should be enabled
  for the SP. Each item in the array is a key that references an authentication
  source in ``config/authsources.php``.

  Note that the delegate module expects authentication sources to have a
  ``name`` key that contains an array of human readable names for each language
  that your SimpleSAMLphp installation supports. [#name]_

  If no module backends are used, the value should be set to ``null`` or the
  array key omitted.

* ``delegate:supplementary`` – optional

  Add additional links that should show up as authentication sources. See the
  paragraph about `supplementary flaps`_.

  If no supplementary links should be added, the key should be omitted, or the
  value set to ``null`` or an empty array.

* ``delegate:fallback`` - optional

  This setting enables fallback between authentication sources.

  The value is an array of fallback mappings. Both the key and value of each
  key-value pair is a reference to authentication sources in
  ``config/authsources.php``. If a mapping between *foo* and *bar* is defined,
  then delegate will try to authenticate with the same credentials at *bar* if
  *foo* fails.

  Note that this setting is **not** transient. This means that you cannot set a
  fallback from *foo* to *bar*, and one from *bar* to *baz* and have an indirect
  fallback from *foo* to *baz*. Each *backend* can only have **one** fallback.

  If no fallback between authentication sources are used, the value should be
  set to ``null`` or the array key omitted.

* ``delegate:contactMail`` – optional

  The value is a string with a support email addess for the SP.

  If no contact address should be presented to the end user, the value should be
  set to ``null`` or the array key omitted.

* ``delegate:contactURL`` – optional

  The value is a string with an URL to support pages for the SP.

  If no contact address should be presented to the end user, the value should be
  set to ``null`` or the array key omitted.

At least one ``delegate:backends`` *or* one ``delegate:remote_idp`` must be
configured for each SP.

Remote IdP
----------
For each remote IdP that is used with delegate, the following additional
configuration should be set in the metadata configuration file,
``metadata/saml20-idp-remote.php``. For each array of SP metadata, you'll need
to consider adding the following keys:

``name``
    See `Common settings`_: `name`_.

``delegate:description``
    See `Common settings`_: `description`_.

``delegate:logo``
    See `Common settings`_: `logo`_.

``OrganizationDisplayName``
    Fallback setting for the `name`_.

Backends
--------
For each supported authentication source that is used as a backend with
delegate, the following additional configuration should be considered in the
authentication source config, ``config/authsources.php``:

``name``
    See `Common settings`_: `name`_.

``delegate:description``
    See `Common settings`_: `description`_.

``delegate:logo``
    See `Common settings`_: `logo`_.

``delegate:login_text``
    An HTML string or localized array of HTML strings that should be added to
    the login page, regardless of which authentication source is currently
    selected.

``delegate:info_text``
    An HTML string or localized array of HTML strings that should be added to
    the login page, but only showed when the authentication source is selected.

Supplementary flaps
-------------------
Supplementary flaps can be defined on a per-SP-basis, in
``metadata/saml20-sp-remote.php``. Supplementary flaps behave like remote IdPs
in the interface, but are only links. This can be used to put other, unsupported
login mechanisms on the login page.

To add a supplementary link for a given SP, the key ``delegate:supplementary``
should be added to the SP-metadata config array.

The value of this setting should be an array of arrays, where each array
contains the following key-value pairs:

``name``
    See `Common settings`_: `name`_.

``delegate:flap_description``
    See `Common settings`_: `description`_.

``delegate:flap_logo``
    See `Common settings`_: `logo`_.

``delegate:flap_id``
    A unique ID for this link. The ID must not match the EntityId of any
    ``delegate:remote_idps`` for this SP, nor any of the ``delegate:backends``
    for this SP.

``delegate:url``
    The URL for the supplementary link. The URL can be a string, or an array of
    localized strings.

Common settings
---------------
These are common settings that can be added to both an LDAP auth source, the
metadata for a remote SAML2 IdP, or `supplementary flaps`_ in the SP metadata.

Name
~~~~
The ``name``-setting is mandatory, and is the human readable name of a
authentication source, remote IdP or supplementary link.

The value should be an array of localized strings, or a string. If this setting
is omitted or set to ``null``, then:

* Remote IdPs will use the EntityId as name
* Authn sources will attempt to use the ``OrganizationDisplayName``-setting, or
  the AutorityId as name.

Description
~~~~~~~~~~~
The description is optional, and used listing available backends, remote idps
and supplementary links on the login page.

The setting is altered by setting a ``delegate:flap_description``-key in the
array of settings for an auth source, remote idp, or the `supplementary
flaps`_-setting for a remote sp.

The value should be an array of localized strings, or a string. If this setting
is omitted or set to ``null``, the default is to use the `name`_-setting for
this purpose.

If no remote IdPs are used, the value should be set to ``null`` or the array key
omitted.

Logo
~~~~~
Logos are optional, and are used alongside the `description`_.

A logo will be placed next to (or replace) the `description`_ on the login page.
The setting is altered by setting a ``delegate:flap_logo``-key in the array of
settings for an auth source, remote idp, or the `supplementary flaps`_-setting
for a remote sp.

The value should be the url whe the logo can be fetched by the browser. The url
can be local, e.g. ``/logos/mylogo.png``. It is also possible to use a localized
array in order to use different logos in different languages.

The ``alt``-text of the logo will be the same as the `name`_-setting.

If no logo should be used, the value should be set to ``null`` or the array key
omitted.

IdP example configuration
-------------------------
This section contains example snippets for the configuration files and metadata
files with the information needed by delegate. In addition, it serves as a
checklist of configuration files that you will have to edit in order to set up
an IdP with delegate.

* Example IdP metadata::

    // metadata/saml20-idp-hosted.php
    $metadata['https://idp.example.org'] = array(
        'host' => 'idp.example.org',
        'privatekey' => 'idp.key',
        'certificate' => 'idp.crt',
        'auth' => 'delegate',
        'class' => 'sspmod_delegate_IdP_ProxyIdP',
    );

* Example SP metadata::

    // metadata/saml20-sp-remote.php
    $metadata['https://sp.example.org'] = array(
        'metadata-set' => 'saml20-sp-remote',
        'entityid' => 'https://sp.example.org',
        'AssertionConsumerService' =>
            'https://sp.example.org/simplesaml/module.php/saml/sp/saml2-acs.php/idp.example.org',
        'SingleLogoutService' =>
            'https://sp.example.org/simplesaml/module.php/saml/sp/saml2-logout.php/idp.example.org',
        'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
        'attributes' => array('cn', 'mail'),
        'delegate:remote_idp' => array('https://foo.example.org'),
        'delegate:backends' => array('foo'),
        'delegate:fallback' => array('foo' => 'bar'),
        'delegate:contactMail' => 'support@sp.example.org',
        'delegate:contactURL' => 'https://sp.example.org/support',
        'name' => array(
            'no' => 'Min SP',
            'en' => 'My SP',
        ),
        delegate:supplementary => array(
            array(
                'delegate:flap_id' => 'supplementary',
                'delegate:url => '/login-using-something.php',
                'name' => 'Something',
                'delegate:flap_description' => 'Log in using Something',
            ),
        ),
    )

* Authentication source example::

    // config/authsources.php
    $config['foo'] = array(
        'ldap:LDAP',
        'hostname' => 'ldap.example.org',
        'enable_tls' => true,
        'dnpattern' => 'uid=%username%,cn=foo,dc=example,dc=org',
        'search.base' => 'cn=foo,dc=example,dc=org',
        'search.attributes' => array('uid',),
        'search.enable' => true,
        'name' => array(
            'en' => 'Local foo users at example.org',
        ),
        'delegate:flap_logo' => 'http://ldap.example.com/foo_logo.png',
        'delegate:login_text' => array(
            'en' => 'Read about foo<a href="/foo.html">here</a>',
        ),
        'delegate:info_text' => array(
            'en' => 'I will be hidden if another source is selected',
        );
    );
    $config['bar'] = array(
        'ldap:LDAP',
        'hostname' => 'ldap.example.org',
        'enable_tls' => true,
        'dnpattern' => 'uid=%username%,cn=bar,dc=example,dc=org',
        'search.base' => 'cn=bar,dc=example,dc=org',
        'search.attributes' => array('uid',),
        'search.enable' => true,
        'name' => array(
            'en' => 'Local bar users at example.org',
        ),
    );

* Remote IdP metadata::

    $metadata['https://foo.example.org'] = array (
        'metadata-set' => 'saml20-idp-remote',
        'entityid' => 'https://foo.example.org',
        'SingleSignOnService' =>
            'https://foo.example.org/simplesaml/saml2/idp/SSOService.php',
        'SingleLogoutService' =>
            'https://foo.example.org/simplesaml/saml2/idp/SingleLogoutService.php',
        'certificate' => 'foo.crt',
        'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
        'name' => array(
            'en' => 'Users at Foo',
        ),
        'delegate:flap_logo' => 'http://foo.example.org/foo_logo.png',
    );

* Dummy SP metadata::

    // metadata/saml20-sp-hosted.php
    $metadata['__DYNAMIC:1__'] = array(
        'host'  => '__DEFAULT__'
    );


Template & Theme Structure
--------------------------
When designing the delegate-module, we put emphasis on making the code base as
modularized as possible, and maintaining very loose couplings between the
module's business logic, and how this functionality is actually rendered for
end users.

Our ambitions were that integrating the module into an existing application with
an already defined look or theme should be as easy as possible, while at the
same time providing an out-of-the-box-ready theme to easily implement the module
if one wishes to do so.

The module's login.php template is designed for use with the simplesamlphp default
theme, and will render a page reminiscent of that theme's style.
When the delegate_login page is rendered, the required .js and .css-files will be
automatically injected through the module's htmlinject-hook, regardless of which
theme is set as active, in order to maintain the module's core functionality.

The delegate-flaps theme is a more "stylized" version of the same functionality,
adding some extra HTML-elements for an improved user interface.
If the pre-packaged delegate-flaps theme is set as active, the htmlinject hook
will also inject this theme's .js/.css dependencies.

Incorporating the functionality from the delegate-flaps theme into your own theme,
is as easy as copying the login.php template located in the delegate-flaps/delegate
folder, into <your theme>/delegate/login.php, and including references to the
delegate-flaps.css and delegate-flaps.js files in your header.php or through
your own htmlinject hook.

If you want to create your login page from scratch, this can also be achieved
without much hassle. Part of our motivation for including the delegate-flaps
theme, was giving developers a concrete example on how to accomplish this.


.. Footnotes
.. [#negotiate] The *Negotiate* module for SimpleSAMLphp is written to enable
                Kerberos authentication with the Delegate module.
.. [#name] Authentication source names are displayed to the end-user when
           presented with the choice to log out of an unsupported backend.
