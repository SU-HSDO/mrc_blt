# H&S MRC

Acquia build tool for MRC.

## Getting Started

This project is based on BLT, an open-source project template and tool that enables building, testing, and deploying Drupal installations following Acquia Professional Services best practices.

To set up your local environment and begin developing for this project, refer to the [BLT onboarding documentation](http://blt.readthedocs.io/en/latest/readme/onboarding/). Note the following properties of this project:
* Primary development branch: develop



## Resources

* [JIRA](https://stanfordits.atlassian.net/projects/HSDO/summary)
* [GitHub](https://github.com/SU-HSDO)
* [Acquia Cloud subscription](https://cloud.acquia.com/app/develop/applications/da258bdd-13f9-40de-bce9-ebd55e5060dc)


# BLT SAML Setup

## Initialize simple saml with BLT
* blt simplesamlphp:init
* blt simplesamlphp:build:config

## Create certs
* openssl req -x509 -sha256 -nodes -days 3652 -newkey rsa:2048 -keyout saml.pem -out saml.crt
* put certs on acquia /home/{site}/saml
    * /home/{site}/saml/saml.cert
    * /home/{site}/saml/saml.pem

## Acquia Configs
* Change the `$ah_options` array as follow:
```
$ah_options = [
  'database_name' => '{site}',
  'session_store' => [
    'prod' => 'database',
    'test' => 'database',
    'dev'  => 'database',
  ],
];
```
* Create a secret salt `tr -c -d '0123456789abcdefghijklmnopqrstuvwxyz' </dev/urandom | dd bs=32 count=1 2>/dev/null;echo` and put in acquia_config.php
* Create a unique password and put in acquia_config.php 
* Protect the saml admin pages with:
```
$config['admin.protectindexpage'] = true;
$config['admin.protectmetadata'] = true;
```
* Tell SAML where the cert files are. `$config['certdir'] = '/home/{site}/saml/';`
* Prevent varnish from caching. Add the snippet to acquia_config.php
```
// Prevent Varnish from interfering with SimpleSAMLphp.
// SSL terminated at the ELB/balancer so we correctly set the SERVER_PORT
// and HTTPS for SimpleSAMLphp baseurl configuration.
$protocol = 'http://';
$port = ':80';
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
  $_SERVER['SERVER_PORT'] = 443;
  $_SERVER['HTTPS'] = 'true';
  $protocol = 'https://';
  $port = ':' . $_SERVER['SERVER_PORT'];
}
```
* Tell SAML to translate the urn keys. Add snippet to acquia_config.php
```
$config['authproc.sp'] = array(
  10 => array(
    'class' => 'core:AttributeMap', 'removeurnprefix', 'oid2name',
  ),
  90 => 'core:LanguageAdaptor',
);
```

## Authsources
* Set the entityID ot the production url `'entityID' => 'https://{site-prod}.stanford.edu',`
* Set the ipd in the `default-sp` array. `'idp' => 'https://idp.stanford.edu/',`
* Tell the default-sp to use the certs. in the `default-sp` array add
```
'privatekey' => 'saml.pem',
'certificate' => 'saml.crt'
```

# Move configs to acquia.
* Copy simplesamlphp/config/acquia_config.php onto acquia /home/{site}/saml
* Replace all contents in the acquia_config.php with the snippet below
```
if (file_exists('/home/{site}/saml/acquia_configs.php')) {
  include_once '/home/{site}/saml/acquia_configs.php';
}
```

# Meta Data
* After configs are placed on acquia server, do a blt deploy.
* Go to {site}dev.prod.acquia-sites.com/simplesaml (or your appropriate url for the site)
* Log in using the password as configured in the acquia_config.php
* Verify php installation at /simplesaml/module.php/core/frontpage_config.php
* Go to /simplesaml/module.php/saml/sp/metadata.php/default-sp?output=xhtml and copy the metadata
* Create a new SAML manager at https://spdb.stanford.edu/spconfigs/new
* Paste the above XML into the metadata xml
* Change the entityID to the exact same entityID as configured in the authsources.
* Wait up to 15 minutes.
* In simplesamlephp/metadata replace all contents with 
```
// Load file on acquia server.
if (file_exists('/home/hsmrc/saml/saml20-idp-remote.php')) {
  include_once '/home/hsmrc/saml/saml20-idp-remote.php';
}
```
* Include the following in the /home/{site}/saml20-idp-remote.php on Acquia Server
```
$metadata['https://idp.stanford.edu/'] = array(
  'name' => array(
    'en' => 'Stanford University WebLogin',
  ),
  'description'         => 'Stanford University WebLogin',
  'SingleSignOnService' => 'https://idp.stanford.edu/idp/profile/SAML2/Redirect/SSO',
  'certFingerprint'     => '{fingerprint}'
);
```
* go to the page /simplesaml/module.php/core/authenticate.php and test using the `default-sp` source
* verify you get a valid response with your information.

# Drupal
* Add and enable simplesamlphp_auth module
* Configure as desired on page /admin/config/people/simplesamlphp_auth
* Basic Settings:
    * Authentication source should be `default-sp`
    * Log in link is what the user will click on. like "Stanford Login"
    * Check "Register users"
* Local authentication:
    * Check "Allow authentication with local Drupal accounts"
    * Uncheck "Allow SAML users to set Drupal passwords"
* User info and syncing
    * Unique identifier should be `uid`
    * username can either be `uid` or `displayName`
    * Email should be `eduPersonPrincipalName`
