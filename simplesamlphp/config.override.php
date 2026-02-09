<?php

// we run behind a reverse proxy (nginx) that does the ssl job..
// we don't need to bother checking $_SERVER['HTTPS']
$proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'http';
if ($proto == "https") {
    // ugly hack for getting the redirects to work
    $_SERVER['SERVER_PORT'] = 443;
    $_SERVER['HTTPS'] = 'on';
}

$config['baseurlpath'] = $proto . '://' . $_SERVER['HTTP_HOST'] . '/dugnaden/saml/';
$config['auth.adminpassword'] = trim(file_get_contents("/run/secrets/simplesamlphp-admin-password"));
$config['secretsalt'] = trim(file_get_contents("/run/secrets/simplesamlphp-secretsalt"));
$config['technicalcontact_name'] = 'IT-gruppa';
$config['technicalcontact_email'] = 'it-gruppa@foreningenbs.no';
$config['timezone'] = 'Europe/Oslo';
$config['session.cookie.name'] = 'saml_dugnaden_sid';
$config['session.cookie.path'] = '/dugnaden/';
$config['session.cookie.secure'] = ($proto === 'https');
$config['session.cookie.samesite'] = ($proto === 'https') ? 'None' : 'Lax';
$config['session.authtoken.cookiename'] = 'SimpleSAMLAuthToken_dugnaden';
$config['language.default'] = 'no';
$config['module.enable'] = ['core' => true, 'saml' => true];
