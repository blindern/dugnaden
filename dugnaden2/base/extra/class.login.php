<?php

login::init();

/**
 * Funksjoner for innlogging/utlogging
 * @static
 */
class login
{
	/** Er brukeren logget inn? */
	public static $logged_in = NULL;
	
	/** Informasjon om sesjonen */
	public static $info;
	
	/**
	 * Referanse til user objektet
	 * @var user
	 */
	public static $user = NULL;
	
	/** Ekstra data som settes i session som kun gjelder innloggingen */
	public static $data;
	
	/**
	 * Init funksjonen
	 * Sjekker om brukeren er logget inn og henter nødvendig informasjon
	 */
	public static function init()
	{
		// allerede kjørt? kjøres kun én gang
		if (!is_null(self::$logged_in)) return;
		
		// tøm
		self::trash();
		
		// ajax?
		$ajax = defined("SCRIPT_AJAX");
		
		// skjekk om brukeren er logget inn
		self::check_status($ajax);
		
		// ikke logget inn?
		if (!self::$logged_in)
		{
			sess_start();
			
			// slett mulige sessions
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in']);
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'user']);
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']);
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'data']);
		}
	}
	
	/**
	 * Nullstill informasjon
	 */
	public static function trash()
	{
		// sett opp standardvariabel
		self::$logged_in = false;
		self::$info = NULL;
		self::$user = false;
		self::$data = NULL;
	}
	
	/**
	 * Sjekk om brukeren er logget inn
	 * @param boolean $ajax bruk data fra $_SESSION ?
	 */
	public static function check_status($ajax = false)
	{
		global $__server;
		
		// ajax?
		if ($ajax)
		{
			// er ikke session starta?
			if (!session_id())
			{
				// har ikke session?
				if (!isset($_COOKIE[session_name()]) || !isset($_COOKIE[$__server['cookie_prefix'] . "s"])) return;
				
				// start session
				sess_start();
			}
			
			// har vi ikke brukerinfo?
			if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in'])) return;
			
			// kontroller at brukeren fremdeles kan være logget inn
			if ($_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['ses_expire_time'] <= time())
			{
				self::logout();
				return;
			}
			
			self::$logged_in = $_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in'];
			self::$info = $_SESSION[$GLOBALS['__server']['session_prefix'].'login_info'];
			self::$user = $_SESSION[$GLOBALS['__server']['session_prefix'].'user'];
			if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'data'])) self::$data = &$_SESSION[$GLOBALS['__server']['session_prefix'].'data']; // if-test kan fjernes over tid grunnet overgangsfase
			
			// ajax sjekk fullført
			return;
		}
		
		// finnes cookies?
		if (isset($_COOKIE[$__server['cookie_prefix'] . "s"]))
		{
			$secure = $_COOKIE[$__server['cookie_prefix'] . "s"];
			if ($secure == 1)
			{
				force_https();
			}
			
			// sjekk at vi har alle cookies
			if (isset($_COOKIE[$__server['cookie_prefix'] . "id"]) && substr_count($_COOKIE[$__server['cookie_prefix'] . "id"], ":") == 1 && isset($_COOKIE[$__server['cookie_prefix'] . "h"]))
			{
				// finn sid, uid og hash
				list($sid, $uid) = explode(":", $_COOKIE[$__server['cookie_prefix'] . "id"]);
				$hash = $_COOKIE[$__server['cookie_prefix'] . "h"];
				
				$sid = intval($sid);
				$uid = intval($uid);
				
				// finn ut om dette finnes i databasen
				$result = ess::$b->db->query("SELECT
						ses_id, ses_u_id, ses_hash, ses_expire_type, ses_expire_time, ses_phpsessid, ses_last_ip, ses_last_time, ses_secure,
						u_online_time, u_online_ip, u_activated
					FROM sessions, users
					WHERE ses_u_id = u_id AND ses_u_id = $uid AND ses_id = $sid AND ses_active = 1 AND ses_hash = ".ess::$b->db->quote($hash)." AND ses_expire_time > ".(time()));
				
				if (mysql_num_rows($result) > 0)
				{
					self::$info = mysql_fetch_assoc($result);
					mysql_free_result($result);
					self::$info['ses_secure'] = self::$info['ses_secure'] == 1;
					
					// start session
					sess_start(self::$info['ses_phpsessid']);
					
					// deaktivert?
					if (self::$info['u_activated'] == 0)
					{
						// logg ut alle øktene
						self::logout(true);
						
						// hent begrunnelse og info
						$result = ess::$b->db->query("SELECT u_id, u_fornavn, u_etternavn, u_email FROM users WHERE u_id = $uid");
						$_SESSION[$GLOBALS['__server']['session_prefix'].'login_error'] = array("deactivated", mysql_fetch_assoc($result));
						
						redirect::handle("logginn", redirect::ROOT);
					}
					
					// ny IP-adresse?
					if ($_SERVER['REMOTE_ADDR'] != self::$info['ses_last_ip'] && self::$info['ses_last_ip'] != "0.0.0.0" && !empty(self::$info['ses_last_ip']))
					{
						// logg ut økten
						self::logout();
						
						// lagre i sessions slik at det kan hentes ut til logg inn skjemaet
						$_SESSION[$GLOBALS['__server']['session_prefix'].'logginn_id'] = $uid;
						
						// info og redirect
						ess::$b->page->add_message("Du har fått ny IP-adresse og har blitt automatisk logget ut.");
						redirect::handle("logginn.php?orign=".urlencode($_SERVER['REQUEST_URI']), redirect::ROOT);
					}
					
					// bruker ikke sikker tilkobling slik det skal?
					if (!$secure && self::$info['ses_secure'] && $__server['https_support'])
					{
						// endre secure cookie
						$cookie_expire = self::$info['ses_expire_type'] == LOGIN_TYPE_BROWSER ? 0 : time()+31536000;
						setcookie($__server['cookie_prefix'] . "s", 1, $cookie_expire, $__server['cookie_path'], $__server['cookie_domain']);
						
						force_https();
					}
					
					/*// skal være tvunget til https?
					if ($__server['https_support'] && !self::$info['ses_secure'] && ((self::$info['u_access_level'] != 0 && self::$info['u_access_level'] != 1) || self::$info['u_force_ssl'] != 0))
					{
						// endre secure cookie
						$cookie_expire = self::$info['ses_expire_type'] == LOGIN_TYPE_BROWSER ? 0 : time()+31536000;
						setcookie($__server['cookie_prefix'] . "s", 1, $cookie_expire, $__server['cookie_path'], $__server['cookie_domain']);
						
						// endre session
						ess::$b->db->query("UPDATE sessions SET ses_secure = 1 WHERE ses_id = $sid"); 
						
						// krev https
						force_https();
						self::$info['ses_secure'] = true;
					}*/
					
					// oppdater brukeren
					$expire = self::$info['ses_expire_type'] == LOGIN_TYPE_ALWAYS ? time()+31536000 : (self::$info['ses_expire_type'] == LOGIN_TYPE_BROWSER ? time()+86400 : time()+900);
					self::$info['ses_expire_time'] = $expire;
					$time = time();
					
					$last_ip = ess::$b->db->quote($_SERVER['REMOTE_ADDR']);
					
					$extra = "";
					if (session_id() != self::$info['ses_phpsessid'])
					{
						$phpsessid = ess::$b->db->quote(session_id());
						$extra .= ", ses_phpsessid = $phpsessid";
					}
					
					ess::$b->db->query("UPDATE sessions SET ses_expire_time = $expire, ses_hits = ses_hits + 1, ses_last_time = $time, ses_last_ip = $last_ip$extra WHERE ses_u_id = $uid AND ses_id = $sid");
					
					// hent inn brukeren
					self::$logged_in = true;
					self::load_user($uid);
					
					$upd_u = array();
					if ($_SERVER['REMOTE_ADDR'] != self::$info['ses_last_ip'])
					{
						$upd_u[] = "u_online_ip = $last_ip";
						self::$user->data['u_online_ip'] = $_SERVER['REMOTE_ADDR'];
					}
					elseif ($_SERVER['REMOTE_ADDR'] != self::$info['u_online_ip'])
					{
						$upd_u[] = "u_online_ip = $last_ip";
						self::$user->data['u_online_ip'] = $_SERVER['REMOTE_ADDR'];
					}
					
					self::$user->data['u_online_time'] = $time;
					$upd_u[] = "u_online_time = $time";
					
					if (count($upd_u) > 0) ess::$b->db->query("UPDATE users SET ".implode(",", $upd_u)." WHERE u_id = ".self::$user->id);
				}
				else
				{
					// fant ingen tilsvarende rad - slett session og cookies
					self::logout();
				}
			}
			else
			{
				// mangler alle cookies
				self::logout();
			}
		}
		
		else
		{
			sess_start();
		}
	}
	
	/**
	 * Last inn brukeren
	 */
	protected static function load_user($u_id)
	{
		if (!self::$logged_in) return;
		
		// last inn brukeren
		if (!user::get($u_id, true))
		{
			self::logout();
		}
		
		// lagre session
		$_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in'] = true;
		$_SESSION[$GLOBALS['__server']['session_prefix'].'login_info'] = &self::$info;
		$_SESSION[$GLOBALS['__server']['session_prefix'].'user'] = self::$user;
		if (!isset($_SESSION[$GLOBALS['__server']['session_prefix'].'data'])) $_SESSION[$GLOBALS['__server']['session_prefix'].'data'] = array();
		self::$data = &$_SESSION[$GLOBALS['__server']['session_prefix'].'data'];
	}
	
	/**
	 * Logg ut en bruker
	 * Fjernet alle sessions og cookies
	 * @param boolean $all_sessions fjerne innlogginger fra andre steder også?
	 * @return boolean sessions ble slettet?
	 */
	// logg ut en bruker (slett session og cookie)
	public static function logout($all_sessions = false)
	{
		global $__server;
		
		sess_start();
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'logged_in']);
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'user']);
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']);
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'user_info']);
		
		// fjern cookies
		if (isset($_COOKIE[$__server['cookie_prefix'] . "h"])) setcookie($__server['cookie_prefix'] . "h", false, 0, $__server['cookie_path'], $__server['cookie_domain']);
		if (isset($_COOKIE[$__server['cookie_prefix'] . "s"])) setcookie($__server['cookie_prefix'] . "s", false, 0, $__server['cookie_path'], $__server['cookie_domain']);
		if (isset($_COOKIE[$__server['cookie_prefix'] . "id"])) setcookie($__server['cookie_prefix'] . "id", false, 0, $__server['cookie_path'], $__server['cookie_domain']);
		
		// må være innlogget/ha sesjonsinfo for å slette sessions
		if (!isset(self::$info['ses_u_id'])) return false;
		
		// slett session
		if ($all_sessions)
		{
			// slett alle sessions til denne brukeren
			ess::$b->db->query("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_u_id = ".self::$info['ses_u_id']." AND ses_active = 1");
		}
		elseif (isset(self::$info['ses_id']) && isset(self::$info['ses_hash']))
		{
			// slett kun den aktive session
			ess::$b->db->query("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_u_id = ".self::$info['ses_u_id']." AND ses_hash = ".ess::$b->db->quote(self::$info['ses_hash'])." AND ses_id = ".self::$info['ses_id']." AND ses_active = 1");
		}
		
		return true;
	}
	
	/**
	 * Behandle logg inn forespørsel
	 * @param int $u_id brukerid
	 * @param string $pass
	 * @param integer $expire_type
	 * @param boolean $hash_pass skal passordet krypteres?
	 * @param boolean $secure_only skal vi fortsette å bruke ssl etter innlogging?
	 * @return boolean
	 */
	public static function do_login($u_id, $pass, $expire_type = LOGIN_TYPE_TIMEOUT, $hash_pass = true, $secure_only = false, $skip_pass = null)
	{
		// se om vi finner brukeren
		if (!$skip_pass) $pass = $hash_pass ? ess::$b->db->quote(password::hash($pass, $u_id, "user")) : ess::$b->db->quote($pass);
		$pass = $skip_pass ? '' : " AND u_pass = $pass";
		
		$result = ess::$b->db->query("
			SELECT u_id, u_online_time, u_online_ip, u_activated
			FROM users
			WHERE u_id = ".intval($u_id)."$pass");
		$user = mysql_fetch_assoc($result);
		
		// fant ikke brukeren?
		if (mysql_num_rows($result) == 0)
		{
			return LOGIN_ERROR_USER_OR_PASS;
		}
		
		// ikke aktivert?
		if ($user['u_activated'] == 0)
		{
			global $uid;
			$uid = $user['u_id'];
			return LOGIN_ERROR_ACTIVATE;
		}
		
		// e-post og passord stemte, logg inn personen
		self::do_login_handle($user['u_id'], $user, $expire_type, $secure_only);
		return -1;
	}
	
	/**
	 * Logg inn en bruker
	 * @param string $email kan også være brukerid
	 * @param string $pass
	 * @param integer $expire_type
	 * @param boolean $md5 skal passordet krypteres?
	 * @param boolean $secure_only skal vi fortsette å bruke ssl etter innlogging?
	 * @return boolean
	 */
	public static function do_login_handle($u_id, $user = NULL, $expire_type = LOGIN_TYPE_TIMEOUT, $secure_only = false)
	{
		global $__server;
		
		// må hente data?
		$u_id = (int) $u_id;
		if (!$user)
		{
			$result = ess::$b->db->query("
				SELECT u_id, u_online_time, u_online_ip, u_activated
				FROM users
				WHERE u_id = $u_id");
			$user = mysql_fetch_assoc($result);
		}
		
		if (!$user || $u_id != $user['u_id']) return false;
		
		// ikke aktivert?
		if ($user['u_activated'] == 0) return false;
		
		// lag unik id
		$hash = uniqid("");
		
		// timeout tid
		$timeout = 900;
		
		// secure only
		$secure_only = $__server['https_support'] && $secure_only;
		
		$expire_type = (int) $expire_type;
		$expire = $expire_type == LOGIN_TYPE_BROWSER ? time()+60*60*48 : ($expire_type == LOGIN_TYPE_TIMEOUT ? time()+$timeout : time()+31536000);
		
		// legg til session
		$ip = ess::$b->db->quote($_SERVER['REMOTE_ADDR']);
		ess::$b->db->query("INSERT INTO sessions SET ses_u_id = {$user['u_id']}, ses_hash = ".ess::$b->db->quote($hash).", ses_expire_time = $expire, ses_expire_type = $expire_type, ses_created_time = ".time().", ses_created_ip = $ip, ses_last_ip = $ip, ses_secure = ".($secure_only ? 1 : 0));
		
		// hent session id
		$ses_id = ess::$b->db->insert_id();
		
		// sett cookie
		$cookie_expire = $expire_type == LOGIN_TYPE_BROWSER ? 0 : time()+31536000;
		setcookie($__server['cookie_prefix'] . "id", "$ses_id:{$user['u_id']}", $cookie_expire, $__server['cookie_path'], $__server['cookie_domain'], $secure_only);
		setcookie($__server['cookie_prefix'] . "h", $hash, $cookie_expire, $__server['cookie_path'], $__server['cookie_domain'], $secure_only, true);
		setcookie($__server['cookie_prefix'] . "s", ($secure_only ? 1 : 0), $cookie_expire, $__server['cookie_path'], $__server['cookie_domain']);
		
		self::$logged_in = true;
		self::$info = array(
			"ses_id" => $ses_id,
			"ses_u_id" => $user['u_id'],
			"ses_hash" => $hash,
			"ses_expire_type" => $expire_type,
			"ses_expire_time" => $expire,
			#"ses_browsers" => $_SERVER['HTTP_USER_AGENT'],
			"ses_phpsessid" => session_id(),
			"ses_last_ip" => $_SERVER['REMOTE_ADDR'],
			"ses_last_time" => time(),
			"ses_secure" => $secure_only,
			"u_online_time" => $user['u_online_time'],
			"u_online_ip" => $user['u_online_ip'],
			#"u_access_level" => $user['u_access_level'],
			#"u_force_ssl" => $user['u_force_ssl']
		);
		
		// last inn bruker
		self::load_user($user['u_id']);
		
		return true;
	}
	
	/**
	 * Finnes det noe sesjonsdata?
	 * @param $name
	 */
	public static function data_exists($name)
	{
		return isset(self::$data[$name]);
	}
	
	/**
	 * Fjern sesjonsdata
	 * @param $name
	 */
	public static function data_remove($name)
	{
		if (isset(self::$data[$name]))
		{
			unset(self::$data[$name]);
			return true;
		}
		return false;
	}
	
	/**
	 * Lagre sesjonsdata
	 * @param $name
	 * @param $value
	 */
	public static function data_set($name, $value)
	{
		self::$data[$name] = $value;
	}
	
	/**
	 * Hent sesjonsdata
	 * @param string $name
	 * @param mixed $default_value
	 */
	public static function data_get($name, $default_value = null)
	{
		return isset(self::$data[$name]) ? self::$data[$name] : $default_value;
	}
}