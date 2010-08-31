<?php

// grunnpath
define("ROOT", dirname(dirname(__FILE__)));

// IP-adresse for å hoppe over lockdown status på hovedserveren
// regex
define("ADMIN_IP", "/(10.8.0.10|127.0.0.1)/");

// er HTTPS aktivert?
define("HTTPS", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "s" : ""));

// last inn innstillinger spesifikt for serveren
$local_settings = dirname(__FILE__) . "/inc.innstillinger_local.php";
if (!file_exists($local_settings))
{
	// forsøk å opprett fil utifra malen
	$template = '<?php


// hvilken versjon dette dokumentet er
// endre denne kun på forespørsel
// brukes til å hindre siden i å kjøre dersom nye innstillinger legges til
// slik at de blir lagt til her før siden blir mulig å bruke igjen
// (først etter at nye innstillinger lagt til, skal versjonen settes til det som samsvarer med de nye innstillingene)
$local_settings_version = 1.0;



// linjene som er kommentert med # er eksempler på andre oppsett



define("DEBUGGING", true);

// hovedserveren?
// settes kun til true på sm serveren
// dette gjør at den utelukker enkelte statistikk spesifikt for serveren, aktiverer teststatus av funksjoner osv.
define("MAIN_SERVER", false);

// testversjon på hovedserveren?
// kun avjørende hvis MAIN_SERVER er true
// deaktiverer opplasting av bilder på testserveren, benytter egen test-cache versjon og litt annet
define("TEST_SERVER", false);

// HTTP adresse til static filer
#define("STATIC_LINK", "http".HTTPS."://hsw.no/static");
define("STATIC_LINK", "/static");




global $__server;
$__server = array(
	"absolute_path" => "http".HTTPS."://".$_SERVER[\'HTTP_HOST\'],
	"relative_path" => "", // hvis siden ligger i noen undermapper, f.eks. /blindern
	"session_prefix" => "bs_",
	"cookie_prefix" => "bs_",
	"cookie_path" => "/",
	"cookie_domain" => "",
	"https_support" => false, // har vi støtte for SSL (https)?
	"http_path" => "http://".$_SERVER[\'HTTP_HOST\'], // full HTTP adresse, for videresending fra HTTPS
	"https_path" => false, // full HTTPS adresse, false hvis ikke støtte for HTTPS, eks: "https://hsw.no"
	"timezone" => "Europe/Oslo"
);
$__server[\'path\'] = $__server[\'absolute_path\'].$__server[\'relative_path\'];




// mappestruktur
// merk at adresse på windows må ha to \\.

// HTTP-adresse til lib-mappen (hvor f.eks. MooTools plasseres)
define("LIB_HTTP", $__server[\'path\'] . "/lib");

// HTTP adresse til hvor bildemappen er plassert
define("IMGS_HTTP", $__server[\'path\'] . "/imgs");

// mappe hvor vi skal cache for fil-cache (om ikke APC er til stede)
define("CACHE_FILES_DIR", "/tmp");
define("CACHE_FILES_PREFIX", "blinderncache_");



// databaseinnstillinger
define("DBHOST", "127.0.0.1");
#define("DBHOST", ":/var/lib/mysql/mysql.sock"); // linux

// brukernavn til MySQL
define("DBUSER", "brukernavn");

// passord til MySQL
define("DBPASS", "passord");

// MySQL-databasenavn som inneholder dataen
define("DBNAME", "databasenavn");



// kommenter eller fjern neste linje ETTER at innstillingene ovenfor er korrigert
die("Innstillingene må redigeres får serveren kan benyttes. Se base/inc.innstillinger_local.php.");';
	
	// forsøk å lagre malen for innstillinger
	if (!file_put_contents($local_settings, $template))
	{
		die("Kunne ikke opprette fil for lokale innstillinger. Forsøk og opprett og gi tilgang til base/inc.innstillinger_local.php.");
	}
}

global $local_settings_version, $__server;
require $local_settings;

$__server['spath'] = ($__server['https_path'] ? $__server['https_path'] : $__server['http_path']).$__server['relative_path'];
$__server['rpath'] = $__server['relative_path'];

// kontroller versjonen til de lokale innstillingene
if ($local_settings_version < 1.0)
{
	header("HTTP/1.0 503 Service Unavailiable");
	echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<title>StreetzMafia</title>
<style>
<!--
body { font-family: tahoma; font-size: 14px; }
h1 { font-size: 23px; }
.hsws { color: #CCCCCC; font-size: 12px; }
.subtitle { font-size: 16px; font-weight: bold; }
-->
</style>
</head>
<body>
<h1>Oppdateringer på server er nødvendig</h1>
<p>De lokale innstillingene er ikke oppdatert mot nyeste endringer og må oppdateres før siden kan benyttes.</p>
<p>Se diff i SVN for info.</p>
<p class="hsws"><a href="http://hsw.no/">hsw.no</a></p>
</body>
</html>';
	die;
}