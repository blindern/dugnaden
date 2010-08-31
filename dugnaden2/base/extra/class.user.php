<?php

/**
 * Brukersystemet
 */
class user
{
	/** Samling av brukerobjekter */
	protected static $users = array();
	
	/** ID til brukeren */
	public $id;
	
	/** Data om brukeren */
	public $data;
	
	/** Levende/aktivert? */
	public $active;
	
	/**
	 * Params til brukeren
	 * @var params_update
	 */
	public $params;
	
	/**
	 * Hent brukerobjekt
	 * @param integer $u_id
	 * @param boolean $is_login_user settes kun av login klassen
	 */
	public static function get($u_id, $is_login_user = false)
	{
		// allerede lastet inn?
		if (isset(self::$users[$u_id]))
		{
			$user = self::$users[$u_id];
			if ($is_login_user) login::$user = $user;
			return $user;
		}
		
		$user = new user($u_id, $is_login_user);
		if (!$user->data) return false;
		
		// lagre objektet for evt. senere bruk
		self::$users[$user->id] = $user;
		
		return $user;
	}
	
	/**
	 * Opprett objekt av en bruker
	 * @param integer $u_id
	 * @param boolean $is_login_user settes kun av login klassen
	 */
	public function __construct($u_id, $is_login_user = false)
	{
		global $_base, $_game;
		$u_id = (int) $u_id;
		$this->id = $u_id;
		
		// hent brukerdata
		$result = $_base->db->query("
			SELECT users.*
			FROM users
			WHERE users.u_id = $u_id");
		
		// lagre data
		$this->data = mysql_fetch_assoc($result);
		mysql_free_result($result);
		
		// fant ikke brukeren?
		if (!$this->data)
		{
			return;
		}
		
		// levende/aktivert?
		$this->active = $this->data['u_activated'] != 0;
		
		// koble mot login?
		if ($is_login_user)
		{
			login::$user = $this;
		}
		
		// fjern variablene som skal lastes når de blir benyttet
		unset($this->params);
		
		return;
	}
	
	/**
	 * Fiks objektet hvis det har vært serialized
	 */
	public function __wakeup()
	{
		// slett objektene på nytt hvis de ikke er initialisert med __get
		if (!isset($this->params)) unset($this->params);
	}
	
	/**
	 * Last inn objekter først når de skal benyttes
	 */
	public function __get($name)
	{
		switch ($name)
		{
			// params
			case "params":
				$this->params = new params_update($this->data['u_params'], "users", "u_params", "u_id = $this->id");
				return $this->params;
			break;
		}
	}
	
	/**
	 * Aktiver brukeren
	 */
	/*public function activate()
	{
		global $_game, $__server;
		
		// er aktivert?
		if ($this->data['u_access_level'] != 0) return false;
		$this->data['u_access_level'] = 1;
		
		// aktiver brukeren
		ess::$b->db->query("UPDATE users SET u_access_level = 1 WHERE u_id = $this->id AND u_access_level = 0");
		if (ess::$b->db->affected_rows() == 0) return false;
		
		putlog("CREWCHAN", "%bAktivering%b: Brukeren {$this->data['u_email']} ({$this->player->data['up_name']}) er nå aktivert igjen {$__server['path']}/min_side.php?u_id=$this->id");
		return true;
	}*/
	
	/**
	 * Deaktiver brukeren
	 */
	/*public function deactivate($reason, $note, player $by_up = null)
	{
		global $_game, $__server;
		if (!$by_up) $by_up = $this->player;
		
		// er ikke aktivert?
		if ($this->data['u_access_level'] == 0) return false;
		
		// deaktivere spilleren?
		if ($this->player->active)
		{
			$this->player->deactivate($reason, $note, $by_up);
		}
		
		$this->data['u_access_level'] = 0;
		$this->data['u_deactivated_time'] = time();
		$this->data['u_deactivated_up_id'] = $by_up->id;
		$this->data['u_deactivated_reason'] = empty($reason) ? NULL : $reason;
		$this->data['u_deactivated_note'] = empty($note) ? NULL : $note;
		
		// deaktiver brukeren
		ess::$b->db->query("UPDATE users SET u_access_level = 0, u_deactivated_time = {$this->data['u_deactivated_time']}, u_deactivated_up_id = $by_up->id, u_deactivated_reason = ".ess::$b->db->quote($reason).", u_deactivated_note = ".ess::$b->db->quote($note)." WHERE u_id = $this->id AND u_access_level != 0");
		if (ess::$b->db->affected_rows() == 0) return false;
		
		// logg ut alle øktene
		ess::$b->db->query("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_u_id = $this->id AND ses_active = 1");
		
		if ($by_up->id == $this->player->id) $info = 'deaktiverte seg selv';
		else
		{
			$info = 'ble deaktivert';
			if (login::$logged_in) $info .= ' av '.login::$user->player->data['up_name'];
		}
		putlog("CREWCHAN", "%bDeaktivering%b: Brukeren {$this->data['u_email']} ({$this->player->data['up_name']}) $info {$__server['path']}/min_side.php?u_id=$this->id");
		return true;
	}*/
	
	/**
	 * Endre tilgangsnivå
	 * @param integer $level nytt tilgangsnivå
	 * @param bool $no_update_up ikke oppdatere det visuelle tilgangsnivået til spilleren?
	 */
	/*public function change_level($level, $no_update_up = NULL)
	{
		global $_game;
		$level = (int) $level;
		
		ess::$b->db->begin();
		
		// forsøk å endre tilgangsnivået fra nåværende
		ess::$b->db->query("UPDATE users SET u_access_level = $level WHERE u_id = $this->id AND u_access_level = {$this->data['u_access_level']}");
		if (!ess::$b->db->affected_rows() > 0) return false;
		$this->data['u_access_level'] = $level;
		
		// endre spilleren også?
		if ($this->player->active && !$no_update_up)
		{
			ess::$b->db->query("UPDATE users_players SET up_access_level = $level WHERE up_id = {$this->player->id}");
			
			// endre rankliste?
			/*if ($level < $_game['access_noplay'] && $this->player->data['up_access_level'] >= $_game['access_noplay'])
			{
				// øk tallplasseringen til de under spilleren
				ess::$b->db->query("
					UPDATE users_players, (SELECT up_id ref_up_id FROM users_players WHERE up_points = {$this->player->data['up_points']} AND up_id != {$this->player->id} AND up_access_level < {$_game['access_noplay']} LIMIT 1) ref
					SET up_rank_pos = up_rank_pos + 1 WHERE ref_up_id IS NULL AND up_points < {$this->player->data['up_points']}");
			}
			elseif ($level >= $_game['access_noplay'] && $this->player->data['up_access_level'] < $_game['access_noplay'])
			{
				// senk tallplasseringen til de under spilleren
				ess::$b->db->query("
					UPDATE users_players, (SELECT up_id ref_up_id FROM users_players WHERE up_points = {$this->player->data['up_points']} AND up_id != {$this->player->id} AND up_access_level < {$_game['access_noplay']} LIMIT 1) ref
					SET up_rank_pos = up_rank_pos - 1 WHERE ref_up_id IS NULL AND up_points < {$this->player->data['up_points']}");
			}*-/
			ess::$b->db->query("UPDATE users_players_rank SET upr_up_access_level = $level WHERE upr_up_id = $this->id");
			ranklist::update();
			
			$this->player->data['up_access_level'] = $level;
		}
		
		ess::$b->db->commit();
		
		return true;
	}*/
	
	/**
	 * Endre passord
	 */
	public function set_password($pass, $plain = null)
	{
		// hash passordet
		$hash = password::hash($pass, $this->id, "user");
		$plain = $plain ? ess::$b->db->quote($pass) : "NULL";
		
		// oppdater databasen
		ess::$b->db->query("UPDATE users SET u_pass = ".ess::$b->db->quote($hash).", u_pass_plain = $plain WHERE u_id = $this->id");
	}
	
	/**
	 * Generer passord
	 */
	public function generate_password()
	{
		$list = "abcdefghijklmnopqrstuvwxyz0123456789";
		
		$pass = "";
		for ($i = 0; $i < 6; $i++)
		{
			$pass .= substr($list, rand(0, strlen($list)-1), 1);
		}
		
		$this->set_password($pass, true);
		return $pass;
	}
	
	/**
	 * Generer tekst for navnet på personen
	 */
	public function generate_person_name($full_name = null)
	{
		return self::generate_person_name_static($this->data['u_fornavn'], $this->data['u_etternavn'], $full_name);
	}
	
	/**
	 * Generer tekst for navnet på personen
	 */
	public static function generate_person_name_static($first_name, $last_name, $full_name = null)
	{
		if ($full_name === null && login::$logged_in) $full_name = true;
		return $first_name . " " . ($full_name ? $last_name : mb_substr($last_name, 0, 4).'...');
	}
}