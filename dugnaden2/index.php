<?php

require "base/base.php";

class dugnaden
{
	/**
	 * @var pagedata
	 */
	protected $pagedata;
	
	public function __construct()
	{
		$this->pagedata = new pagedata();
	}
	
	public function handle_request()
	{
		// finn korrekt handler (første "fil/mappe" i adressen)
		switch (arrayval($this->pagedata->path_parts, 0))
		{
			case "":          $this->handle_index();    break;
			case "login":     $this->handle_login();    break;
			case "logout":    $this->handle_logout();   break;
			case "profil":    $this->handle_profile();  break;
			
			default:
				page_not_found();
		}
		
		ess::$b->page->load();
	}
	
	protected function handle_index()
	{
		echo '
<h1>Forsiden</h1>
<p>Forsiden..</p>';
		
		if (login::$logged_in)
		{
			echo '
<p>Innlogget som '.htmlspecialchars_utf8(login::$user->generate_person_name()).' - <a href="'.ess::$s['rpath'].'/logout">Logg ut</a></p>';
		}
		
		else
		{
			echo '
<p><a href="'.ess::$s['rpath'].'/login">Logg inn</a></p>';
		}
	}
	
	/**
	 * Behandle logg inn forespørsel
	 */
	protected function handle_login()
	{
		// allerede logget inn?
		if (login::$logged_in) redirect::handle("", redirect::ROOT);
		
		// har vi brukerid og passord?
		if (isset($_POST['uid']) && isset($_POST['pass']))
		{
			// forsøk å logg inn
			switch (login::do_login($_POST['uid'], $_POST['pass']))
			{
				case LOGIN_ERROR_USER_OR_PASS:
					ess::$b->page->add_message("Feil ID eller passord.", "error");
				break;
				
				// ikke aktivert
				case LOGIN_ERROR_ACTIVATE:
					ess::$b->page->add_message("Denne brukeren er deaktivert og kan ikke logge inn.", "error");
				break;
				
				default:
					if (!login::$logged_in)
					{
						ess::$b->page->add_message("Ukjent innloggingsfeil.", "error");
					}
					else
					{
						// logget inn
						if (isset($_GET['orign']))
						{
							redirect::handle($_GET['orign'], redirect::SERVER, login::$info['ses_secure']);
						}
						
						redirect::handle("", redirect::ROOT, login::$info['ses_secure']);
					}
			}
			
			redirect::handle();
		}
		
		$this->show_login_form();
	}
	
	/**
	 * Vis logg inn skjema
	 */
	protected function show_login_form()
	{
		$result = ess::$b->db->query("SELECT u_id, u_fornavn, u_etternavn FROM users WHERE u_activated != 0 ORDER BY u_fornavn");
		$users = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$users[] = $row;
		}
		
		echo '
<h1>Logg inn</h1>
<form action="" method="post">
	<p>Velg person
		<select name="uid">';
		
		foreach ($users as $user)
		{
			echo '
			<option value="'.$user['u_id'].'">'.htmlspecialchars_utf8(user::generate_person_name_static($user['u_fornavn'], $user['u_etternavn'])).'</option>';
		}
		
		echo '
		</select>
	</p>
	<p>Passord <input type="text" name="pass" class="styled" /></p>
	<p><input type="submit" value="Logg inn" /></p>
</form>';
	}
	
	/**
	 * Behandle logg ut forespørsel
	 */
	protected function handle_logout()
	{
		if (!login::$logged_in) redirect::handle("", redirect::ROOT);
		login::logout();
		
		redirect::handle("", redirect::ROOT);
	}
	
	/**
	 * Behandle profil forespørsel
	 */
	protected function handle_profile()
	{
		access::no_guest();
		
		// mangler bruker?
		if (!isset($this->pagedata->path_parts[1]))
		{
			redirect::handle("/profil/".login::$user->id, redirect::ROOT);
		}
		
		// en annen bruker?
		$u_id = $this->pagedata->path_parts[1];
		if ($u_id != login::$user->id)
		{
			if (!is_numeric($u_id)) redirect::handle("", redirect::ROOT);
			$u_id = (int) $u_id;
			
			$user = user::get($u_id);
			if (!$user) redirect::handle("", redirect::ROOT);
		}
		else
		{
			$user = login::$user;
		}
		
		if (isset($this->pagedata->path_parts[2]))
		{
			switch ($this->pagedata->path_parts[2])
			{
				case "edit":
					return $this->profile_edit($user);
				break;
				
				default:
					redirect::handle("", redirect::ROOT);
			}
		}
		
		$this->profile_view($user);
	}
	
	protected function profile_edit(user $user)
	{
		echo '
<p>Redigerer profilen til '.htmlspecialchars_utf8($user->generate_person_name()).'.</p>';
	}
	
	protected function profile_view(user $user)
	{
		echo '
<p>Viser profilen til '.htmlspecialchars_utf8($user->generate_person_name()).'.</p>';
	}
}

$dugnad = new dugnaden();
$dugnad->handle_request();