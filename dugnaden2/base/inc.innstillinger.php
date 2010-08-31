<?php

// innstillinger

error_reporting(E_ALL);

// for kryptering
#define("CRYPT_KEY", "123");
#define("CRYPT_MODULE", MCRYPT_DES);
#define("CRYPT_MODE", MCRYPT_MODE_ECB);

// sett konstanter
define("LOGIN_TYPE_TIMEOUT", 0);
define("LOGIN_TYPE_BROWSER", 1);
define("LOGIN_TYPE_ALWAYS", 2);
define("LOGIN_ERROR_USER_OR_PASS", 0);
define("LOGIN_ERROR_ACTIVATE", 1);


global $_lang;
$_lang = array(
	"seconds" => array(
		"full" => array("sekund", "sekunder"),
		"partial" => array("sek", "sek"),
		"short" => array("s", "s")
	),
	"minutes" => array(
		"full" => array("minutt", "minutter"),
		"partial" => array("min", "min"),
		"short" => array("m", "m")
	),
	"hours" => array(
		"full" => array("time", "timer"),
		"partial" => array("time", "timer"),
		"short" => array("t", "t")
	),
	"days" => array(
		"full" => array("dag", "dager"),
		"partial" => array("dag", "dager"),
		"short" => array("d", "d")
	),
	"weeks" => array(
		"full" => array("uke", "uker"),
		"partial" => array("uke", "uker"),
		"short" => array("u", "u")
	),
	"weekdays" => array(
		"søndag",
		"mandag",
		"tirsdag",
		"onsdag",
		"torsdag",
		"fredag",
		"lørdag"
	),
	"months" => array(
		1 => "januar",
		"februar",
		"mars",
		"april",
		"mai",
		"juni",
		"juli",
		"august",
		"september",
		"oktober",
		"november",
		"desember"
	)
);

global $__page;
$__page = array(
	"title" => "Blindern Studenterhjem",
	"title_direction" => "left",
	"title_split" => " &raquo; ",
	"keywords_default" => array(),
	"description_default" => "",
	"theme" => "bs"
);

// tidssone
global $__server;
date_default_timezone_set($__server['timezone']);