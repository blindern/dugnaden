<?php


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
	"absolute_path" => "http".HTTPS."://".$_SERVER['HTTP_HOST'],
	"relative_path" => "/dugnaden2", // hvis siden ligger i noen undermapper, f.eks. /blindern
	"session_prefix" => "bs_",
	"cookie_prefix" => "bs_",
	"cookie_path" => "/",
	"cookie_domain" => "",
	"https_support" => false, // har vi støtte for SSL (https)?
	"http_path" => "http://".$_SERVER['HTTP_HOST'], // full HTTP adresse, for videresending fra HTTPS
	"https_path" => false, // full HTTPS adresse, false hvis ikke støtte for HTTPS, eks: "https://hsw.no"
	"timezone" => "Europe/Oslo"
);
$__server['path'] = $__server['absolute_path'].$__server['relative_path'];




// mappestruktur
// merk at adresse på windows må ha to \.

// HTTP-adresse til lib-mappen (hvor f.eks. MooTools plasseres)
define("LIB_HTTP", $__server['path'] . "/lib");

// HTTP adresse til hvor bildemappen er plassert
define("IMGS_HTTP", $__server['path'] . "/imgs");

// mappe hvor vi skal cache for fil-cache (om ikke APC er til stede)
define("CACHE_FILES_DIR", "c:/windows/temp");
define("CACHE_FILES_PREFIX", "blinderncache_");



// databaseinnstillinger
define("DBHOST", "127.0.0.1");
#define("DBHOST", ":/var/lib/mysql/mysql.sock"); // linux

// brukernavn til MySQL
define("DBUSER", "blindern");

// passord til MySQL
define("DBPASS", "dugnaden");

// MySQL-databasenavn som inneholder dataen
define("DBNAME", "blindern");



// kommenter eller fjern neste linje ETTER at innstillingene ovenfor er korrigert
#die("Innstillingene må redigeres får serveren kan benyttes. Se base/inc.innstillinger_local.php.");