<?php

class ess
{
	/**
	 * Snarvei til $_base utenom globals
	 * @var essentials
	 */
	public static $b;
	
	/**
	 * Snarvei til $__server utenom globals
	 */
	public static $s;
	
	/**
	 * Snarvei til $_game utenom globals
	 */
	public static $g;
	
	/**
	 * Hent ut verdi fra session
	 */
	public static function session_get($name, $default = null)
	{
		global $__server;
		sess_start();
		
		if (!isset($_SESSION[$__server['session_prefix'].$name])) return $default;
		
		return $_SESSION[$__server['session_prefix'].$name];
	}
	
	/**
	 * Lagre verdi i session
	 */
	public static function session_put($name, $value)
	{
		global $__server;
		sess_start();
		
		$_SESSION[$__server['session_prefix'].$name] = $value;
	}
}

global $__server;
ess::$b = new essentials();
ess::$s = &$__server;

class essentials
{
	public $time_start = 0;
	public $db_debug = false;
	public $data = array();
	
	/** For å holde tidsoversikt i scriptet */
	public $time_debug = array();
	
	/**
	 * @var base
	 */
	public $base;
	
	/**
	 * @var db_wrap
	 */
	public $db;
	
	/**
	 * @var page
	 */
	public $page;
	
	/**
	 * @var date
	 */
	public $date;
	
	/** Constructor */
	public function __construct()
	{
		mb_internal_encoding("UTF-8");
		
		// sørg for at $_base blir til dette objektet
		global $_base;
		$_base = $this;
		
		unset($this->db);
		unset($this->page);
		unset($this->date);
		
		$this->time_start = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		
		define("SCRIPT_START", microtime(true));
		header("X-Powered-By: hsw.no");
		
		if (defined("BASE_LOADED"))
		{
			define("ESSENTIALS_ONLY", false);
		}
		else
		{
			define("ESSENTIALS_ONLY", true);
		}
		
		// sett opp nødvendige variabler
		if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
		if (!isset($_SERVER['REQUEST_METHOD'])) $_SERVER['REQUEST_METHOD'] = "CRON";
		if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
		if (!isset($_SERVER['HTTP_HOST'])) $_SERVER['HTTP_HOST'] = "127.0.0.1";
		if (!isset($_SERVER['HTTP_USER_AGENT'])) $_SERVER['HTTP_USER_AGENT'] = '';
		if (!isset($_SERVER['HTTP_REFERER'])) $_SERVER['HTTP_REFERER'] = '';
		define("PHP_SELF", $_SERVER['SCRIPT_NAME']);
		
		// hent innstillinger
		require "inc.innstillinger_pre.php";
		
		// set timeout
		if (MAIN_SERVER) @set_time_limit(10);
		else @set_time_limit(120);
		
		// fiks gpc variabler
		$this->fix_gpc();
		
		$this->dt("mainfunctions_settings_pre");
		
		// hent flere filer
		require "inc.mainfunctions.php";
		require "inc.innstillinger.php";
		
		$this->dt("post");
		
		// sett opp exception handler
		set_exception_handler(array("sysreport", "exception_handler"));
		
		// skal vi debugge databasen eller ikke?
		if (isset($_COOKIE['show_queries_info']))
		{
			$this->db_debug = true;
		}
		
		$this->init_time();
		
		$this->dt("post_ess");
	}
	
	/** Lagre scripttid (debug time) */
	public function dt($name)
	{
		$this->time_debug[] = array($name, microtime(true));
	}
	
	/** Fiks get, post, cookie */
	public function fix_gpc()
	{
		// fiks alle gpc variablene
		if (get_magic_quotes_gpc())
		{
			foreach ($_GET as $name => $val) $_GET[$name] = stripslashes_all($val);
			foreach ($_POST as $name => $val) $_POST[$name] = stripslashes_all($val);
			foreach ($_REQUEST as $name => $val) $_REQUEST[$name] = stripslashes_all($val);
		}
	}
	
	/**
	 * Fiks objektet hvis det har vært serialized
	 */
	public function __wakeup()
	{
		// slett objektene på nytt hvis de ikke er initialisert med __get
		if (!isset($this->db)) unset($this->db);
		if (!isset($this->page)) unset($this->page);
		if (!isset($this->date)) unset($this->date);
	}
	
	/** Hent inn moduler */
	public function __get($module)
	{
		switch ($module)
		{
			case "db":
				// hent inn databasemodulen
				self::load_module("db_wrap");
				$this->db = $this->db_debug ? new db_wrap_debug() : new db_wrap();
				
				// koble til databasen
				$this->db->connect(DBHOST, DBUSER, DBPASS, DBNAME);
				
				return $this->db;
			break;
			
			case "page":
				// hent inn sidemodulen
				$this->page = new page();
				
				return $this->page;
			break;
			
			case "date":
				// hent inn tidsbehandling
				$this->date = new date();
				
				return $this->date;
			break;
			
			default:
				throw new HSException("Ukjent modul: $module");
		}
	}
	
	/**
	 * Hent inn tilleggscript (gjerne slags moduler)
	 * Sørger for at samme script ikke blir lastet inn flere ganger
	 * @param string $name delvis navn på scriptet
	 * @param string $type type script (class, func, div)
	 */
	public static function load_module($name, $type = "class")
	{
		static $loaded = array();
		
		// allerede lastet inn? (for å slippe require_once)
		if (in_array($name, $loaded)) return;
		
		// type må være class eller func
		if ($type != "class" && $type != "func" && $type != "div")
		{
			if (isset($GLOBALS['load_module_ignore'])) return;
			throw new HSException("Ugyldig type: $type");
		}
		
		if (isset($GLOBALS['load_module_ignore']) && !file_exists(ROOT."/base/extra/".$type.".".$name.".php")) return;
		$loaded[] = $name;
		
		require ROOT."/base/extra/$type.$name.php";
	}
	
	/** Sett opp tidsvariabler */
	private function init_time()
	{
		global $__server;
		
		// sett opp tidssone
		$this->timezone = new DateTimeZone($__server['timezone']);
	}
}

function stripslashes_all($var)
{
	if (is_array($var))
	{
		foreach ($var as $key => $val)
		{
			$var[$key] = stripslashes_all($val);
		}
	}
	else
	{
		$var = stripslashes($var);
	}
	return $var;
}

/**
 * Utvidelse av DateTime objektet
 */
class DateTimeHSW extends DateTime
{
	/**
	 * Formatter dato og tidspunkt
	 *
	 * @param format $format
	 * @return string
	 */
	public function format($format = 0)
	{
		// standard formattering
		if ($format === date::FORMAT_NORMAL)
		{
			global $_base;
			return $_base->date->format($this);
		}
		
		// med sekunder
		if ($format === date::FORMAT_SEC)
		{
			global $_base;
			return $_base->date->format($this, true);
		}
		
		// uten tidspunkt
		if ($format === date::FORMAT_NOTIME)
		{
			global $_base;
			return $_base->date->format($this, false, false);
		}
		
		// kun måned?
		if ($format === date::FORMAT_MONTH)
		{
			global $_lang;
			return $_lang['months'][$this->format("n")];
		}
		
		// kun ukedag?
		if ($format === date::FORMAT_WEEKDAY)
		{
			global $_lang;
			return $_lang['weekdays'][$this->format("w")];
		}
		
		// la DateTime ta seg av formatteringen
		return parent::format($format);
	}
}

/**
 * Tidssystem
 */
class date
{
	/** Formattere dato normalt (med tidspunkt men uten sekunder) */ 
	const FORMAT_NORMAL = 0;
	
	/** Formattere dato med sekunder */
	const FORMAT_SEC = 1;
	
	/** Formattere dato uten tidspunkt */
	const FORMAT_NOTIME = 2;
	
	/** Formattere kun med navn på måned */
	const FORMAT_MONTH = 3;
	
	/** Formattere kun med navn på ukedag */
	const FORMAT_WEEKDAY = 4;
	
	/** Variabel for å holde tidssone objektet */
	public $timezone = NULL;
	
	/** Variabel for å holde nåværende tidspunkt objektet */
	public $now = NULL;
	
	/** Constructor */
	public function __construct()
	{
		$this->timezone = new DateTimeZone("Europe/Oslo");
	}
	
	/**
	 * Hent ut tidsobjekt fra unixtime
	 * @param int unix timestamp $time
	 * @return DateTimeHSW
	 */
	public function get($time = NULL)
	{
		// akkurat nå?
		if ($time === NULL)
		{
			// benytte lagret tidspunkt?
			if ($this->now)
			{
				// kopier objektet og returner det
				return clone $this->now;
			}
			
			global $_base;
			$time = $_base->time_start;
		}
		
		$date = new DateTimeHSW("@".((int)$time));
		$date->setTimezone($this->timezone);
		
		// lagre for muligens senere bruk?
		if ($time === NULL)
		{
			$this->now = $date;
			
			// kopier objektet og returner det
			return clone $this->new;
		}
		
		return $date;
	}
	
	/**
	 * Hent ut tidsobjekt fra tekst
	 * @param string $time
	 * @return DateTimeHSW
	 */
	public function parse($time)
	{
		$date = new DateTimeHSW($time);
		$date->setTimezone($this->timezone);
		
		return $date;
	}
	
	/**
	 * Formatter dato og tidspunkt
	 *
	 * @param DateTime objekt $date
	 * @param vise sekunder $show_seconds
	 * @param vise timer $show_hour
	 * @param tidspunkt som bold $bold
	 * @return string
	 */
	public function format(DateTime $date, $show_seconds = false, $show_hour = true, $bold = false)
	{
		global $_lang;
		
		$hour = '';
		if ($show_hour)
		{
			$hour = 'H:i'.($show_seconds ? ':s' : '');
			$hour = $bold ? ' \\<\\b\\>'.$hour.'\\<\\/\\b\\>' : ' '.$hour;
		}
		
		return $date->format("j. ") . $_lang['months'][$date->format("n")] . $date->format(" Y$hour");
	}
}

/** Egen exception type */
class HSException extends Exception {}
