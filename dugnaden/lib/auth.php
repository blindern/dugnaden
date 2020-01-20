<?php

// For details about this setup, see
// https://simplesamlphp.org/docs/stable/simplesamlphp-sp

use SimpleSAML\Auth\Simple;

function get_auth_simple()
{
    require_once("/var/simplesamlphp/lib/_autoload.php");
    return new Simple('default-sp');
}

function require_admin()
{
    $as = get_auth_simple();
    if (!$as->isAuthenticated() && $_SERVER['REQUEST_METHOD'] == 'POST') {
        die("Du ville normalt nå blitt sendt til logg inn siden, men siden du har forsøkt å fullføre et skjema har vi avbrutt pålogging. Det anbefales at du åpner en ny fane og logger inn med den, for så å gå tilbake til denne og oppdatere siden og sende inn skjemaet på nytt.");
    }
    $as->requireAuth();

    $attributes = $as->getAttributes();
    if (!in_array('dugnaden', $attributes['groups'])) {
        die('Du må være i gruppen "dugnaden" for å administrere dugnadsystemet!');
    }
}

function check_is_admin()
{
    $as = get_auth_simple();
    if ($as->isAuthenticated()) {
        $attributes = $as->getAttributes();
        return in_array('dugnaden', $attributes['groups']);
    }

    return false;
}
