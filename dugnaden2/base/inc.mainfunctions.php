<?php

/** Autoloader */
function __autoload($name)
{
	// trenger ikke å gjennomføre noen sjekk på inndata
	// skal være hardkodet uansett (ingen user_func eller noe)
	// bruker essentials::load_module
	essentials::load_module($name);
}


/**
 * Dumpe en verdi
 */
function dump($value)
{
	header("Content-Type: text/plain");
	var_dump($value);
	die;
}

/**
 * for å starte _SESSION og evt. bruke egen verdi
 * kjøres kun om session ikke allerede er startet
 */
function sess_start($value = false)
{
	if (session_id() != "") return false;
	
	if (!empty($value) && (!isset($_COOKIE[session_name()]) || $value != $_COOKIE[session_name()]))
	{
		session_id($value);
	}
	
	ess::$b->dt("sess_start_pre");
	
	// sett slik at __autoload behandler mulige objekt pent
	$GLOBALS['load_module_ignore'] = true;
	session_start();
	unset($GLOBALS['load_module_ignore']);
	
	ess::$b->dt("sess_start_post");
	return true;
}


/**
 * Hent ut en bestemt verdi fra en array hvis den finnes
 * @param $array
 * @param $item_name
 * @param $default
 */
function arrayval(&$array, $item_name, $default = NULL)
{
	if (!isset($array[$item_name])) return $default;
	return $array[$item_name];
}

function postval($name, $default = "")
{
	if (!isset($_POST[$name])) return $default;
	return $_POST[$name];
}

function getval($name, $default = "")
{
	if (!isset($_GET[$name])) return $default;
	return $_GET[$name];
}

function requestval($name, $default = "")
{
	if (!isset($_REQUEST[$name])) return $default;
	return $_REQUEST[$name];
}


/**
 * Funksjon for å kontrollere SID
 * @param bool $redirect redirect hvis ugyldig
 */
function validate_sid($redirect = true)
{
	global $_base;
	if (!login::$logged_in || ((!isset($_POST['sid']) || $_POST['sid'] != login::$info['ses_id']) && (!isset($_GET['sid']) || $_GET['sid'] != login::$info['ses_id'])))
	{
		$_base->page->add_message("Ugyldig forespørsel.", "error");
		if ($redirect) redirect::handle();
		return false;
	}
	return true;
}

/**
 * Rette på HTML før output
 * Støtter en array med tekst
 * @param array $content
 * @return array
 */
function parse_html_array($content)
{
	// generer unik string som kan brukes som seperator
	$seperator = ":seperator:".uniqid().":";
	
	// hent ut nøklene
	$keys = array_keys($content);
	
	// fiks html
	$content = explode($seperator, parse_html(implode($seperator, $content)));
	
	// legg til nøklene igjen og returner
	return array_combine($keys, $content);
}

/**
 * Rette på HTML før output.
 * 
 * @param string $content
 * @return string
 */
function parse_html($content)
{
	global $__server;
	
	// fiks entities
	$content = str_replace(array(
			"&rpath;",
			"&spath;",
			"&path;",
			"&staticlink;"
		),
		array(
			ess::$s['rpath'],
			ess::$s['spath'],
			ess::$s['path'],
			STATIC_LINK
		),
		$content);
	
	return $content;
}


/**
 * Krever HTTPS tilkobling (redirect hvis ikke)
 * 
 * @param boolean $mode (true for ja, false for ikke)
 */
function force_https($mode = true)
{
	// skal være https - er ikke
	if ($mode && !HTTPS)
	{
		// endre til https hvis serveren støtter det
		global $__server;
		if ($__server['https_support'])
		{
			redirect::handle("https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], redirect::ABSOLUTE);
		}
	}
	
	// skal ikke være https - er https
	elseif (!$mode && HTTPS)
	{
		// endre til http
		redirect::handle("http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], redirect::ABSOLUTE);
	}
}

/**
 * Formattere ord (flertallsendinger)
 *
 * @param mixed $single
 * @param mixed $multiple
 * @param int $num
 * @return mixed
 */
function fword($single, $multiple, $num)
{
	return $num == 1 ? $single : $multiple;
}

/**
 * Formattere ord (flertalls-endinger) gjennom sprintf
 * @param string $single
 * @param string $multiple
 * @param int $num
 * @return string
 */
function fwords($single, $multiple, $num)
{
	return sprintf($num == 1 ? $single : $multiple, $num);
}

/**
 * Sett opp en setning basert på en liste (med komma og "og")
 */
function sentences_list($sentences, $combine = ", ", $combine_last = " og ")
{
	$last = array_pop($sentences);
	if (count($sentences) == 0) return $last;
	
	return implode($combine, $sentences).$combine_last.$last;
}

/**
 * Kontroller datoformat
 * @param string input string
 * @param string format
 * @return mixed matches
 */
function check_date($input, $format = "%d\\.%m\\.%y %h:%i:%s")
{
	static $replaces = array(
		"%d2" => "(0[1-9]|[1-2][0-9]|3[0-1])",
		"%m2" => "(0[1-9]|1[0-2])",
		"%y2" => "([0-1][0-9])",
		"%y4" => "(20[0-1][0-9])",
		"%h2" => "([0-1][0-9]|2[0-3])",
		"%i2" => "([0-5][0-9])",
		"%s2" => "([0-5][0-9])",
		"%d" => "(0?[1-9]|[1-2][0-9]|3[0-1])",
		"%m" => "(0?[1-9]|1[0-2])",
		"%y" => "((?:20)?[0-1][0-9])",
		"%h" => "([0-1]?[0-9]|2[0-3])",
		"%i" => "([0-5]?[0-9])",
		"%s" => "([0-5]?[0-9])"
	);
	static $replaces_from = false;
	static $replaces_to = false;
	if (!$replaces_from)
	{
		$replaces_from = array_keys($replaces);
		$replaces_to = array_values($replaces);
	}
	
	$format = str_replace($replaces_from, $replaces_to, $format);
	
	$matches = false;
	preg_match("/^".str_replace("/", "\\/", $format)."$/U", $input, $matches);
	
	return $matches;
}

/**
 * Ugyldig side (404)
 */
function page_not_found($more_info = NULL)
{
	$more_info = empty($more_info) ? '' : $more_info;
	
	// siden finnes ikke (404)
	header("HTTP/1.1 404 Not Found");
	
	// har vi hentet inn page?
	if (isset(ess::$b->page))
	{
		ess::$b->page->add_title("404 Not Found");
		
		echo '
<h1>404 Not found</h1>
<p>Siden du ba om finnes ikke.</p>
<dl class="dl_50px">
	<dt>Adresse</dt>
	<dd>'.htmlspecialchars($_SERVER['REQUEST_URI']).'</dd>
</dl>'.$more_info;
		
		ess::$b->page->load();
	}
	
	// sett opp html etc
	echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<title>404 Not Found</title>
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
<h1>404 Not Found</h1>
<p>Siden du ba om finnes ikke.</p>
<dl>
	<dt>Adresse</dt>
	<dd>'.htmlspecialchars($_SERVER['REQUEST_URI']).'</dd>
</dl>'.$more_info.'
<p class="hsws"><a href="http://www.henrist.net">HenriSt Websystem</a></p>
</body>
</html>';
	
	die;
}


/**
 * Formattere små tall (tar ikke høyde for whitespace og er ment for output av integers og floats og ikke tekstinput
 * @param float $float
 * @param integer $decimals
 * @return string
 */
function format_num($float, $decimals = 0)
{
	return number_format($float, $decimals, ",", " ");
}

// TODO: Har vi noe bruk for denne?
function parse_intval($number)
{
	$negative = preg_match('/^\s*\-/', $number);
	$number = preg_replace('/[^0-9\.E\+]/', '', $number);
	$number = preg_replace('/^0*/', '', $number);
	
	// E tall?
	$matches = false;
	if (preg_match('/^([0-9])(?:\.([0-9]+))?E\+([0-9]+)$/D', $number, $matches))
	{
		$number = $matches[1];
		
		// før desimaltallet
		$e = intval($matches[3]);
		$matches[2] = str_pad($matches[2], $e, "0", STR_PAD_RIGHT);
		for ($i = 0; $i < $e; $i++)
		{
			$number .= substr($matches[2], $i, 1);
		}
	}
	
	$number = preg_replace('/[^0-9\.]/', '', $number);
	$number = explode(".", $number, 2);
	$number = $number[0];
	if (empty($number)) $number = 0;
	return ($negative ? '-' : '').$number;
}

// sjekk for gyldig e-postadresse
function validemail($address)
{
	return preg_match("/^[a-zA-Z_\\-][\\w\\.\\-_]*[a-zA-Z0-9_\\-]@[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z]$/Di", $address);
}


function htmlspecialchars_utf8($string, $quote_style = null, $double_encode = null)
{
	return htmlspecialchars($string, $quote_style, "UTF-8", $double_encode);
}