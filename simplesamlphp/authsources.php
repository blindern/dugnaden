<?php

$proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$config = array(
    'admin' => array(
        'core:AdminPassword',
    ),
    'default-sp' => array(
        'saml:SP',
        'entityID' => $proto . '://' . $host . '/dugnaden/saml/module.php/saml/sp/metadata.php/default-sp',
        'idp' => 'https://foreningenbs.no/simplesaml/saml2/idp/metadata.php',
    ),
);
