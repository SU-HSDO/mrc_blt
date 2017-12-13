<?php
/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * Remember to remove the IdPs you don't use from this file.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote 
 */

/*
 * Stanford University idp
 */
$metadata['https://idp.stanford.edu/'] = array(
  'name' => array(
    'en' => 'Stanford University WebLogin',
  ),
  'description'         => 'Stanford University WebLogin',
  'SingleSignOnService' => 'https://idp.stanford.edu/idp/profile/SAML2/Redirect/SSO',
  'certFingerprint'     => '2B:41:A2:66:6A:4E:3F:40:C6:30:55:6A:1F:EC:C3:E3:0B:CE:EE:8F'
);
