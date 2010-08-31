<?php

class dugnaden
{
	/**
	 * Maks antall personer en dugnad kan inneholde (en person kan ikke velge dette som en dugnad ved bytte)
	 */
	const MAX_PEOPLE = 27;
	
	/**
	 * Minst antall personer en dugnad kan inneholde (en person kan ikke fravike denne dugnaden hvis antallet er så lavt)
	 */
	const MIN_PEOPLE = 10;
	
	/**
	 * Beløpet på en bot
	 */
	const SKIP_FEE = 500;
	
	/*
	 * 
	 * admin request:
	 *   case "Annulere bot":
	 *   case "Rette beboernavn":
	 *   case "Endre status":
	 *   case "Innstillinger":
	 *   case "Tildele dugnad":
	 *   case "Semesterstart":
	 *   case "Dugnadslederstyring":
	 *   case "Skifte passord":
	 *   case "Endre Buatelefon":
	 *   case "Infoliste":
	 *   case "Botliste":
	 *   case "Neste dugnadsliste":
	 *   case "Oppdatere siste":
	 *   case "Justere status": (fall-through)
	 *   case "Se over forrige semester": (fall-through)
	 *   case "Dugnadsliste":
	 *   case "Dagdugnad":
	 *   case "Dugnadskalender":
	 *   case "Innkalling av nye":
	 *   case "Nye beboere": (fall-through)
	 *   case "Importer beboere":
	 *   case "upload":
	 * 
	 * 
	 * normal request:
	 *   case "admin":
	 *   case "Bytte dugnad":
	 *   case "Bytte passord":
	 *   case "Se dugnadslisten uten passord":
	 * 
	 */
}




class dugnaden_request
{
	public static function admin()
	{
		
				/* User wants to enter admin mode
				------------------------------------------------------------ */

				list($title, $navigation, $content) = do_admin();

				/* Updating all soon to be elefants to elefants if
				 * it is after 15. March or 15. of Oct.:
				---------------------------------------------------------- */
				
				$blivende_updates = update_blivende_elephants();
				
				if($blivende_updates)
				{
					$content .= "<p>Gratulerer til ". $blivende_updates ." beboer". ($blivende_updates > 1 ? "e" : "") ." som endelig er elefant".
									($blivende_updates > 1 ? "er" : "") ."!<br />Eventuelt tilknyttede dugnader er slettet..</p>";
				}
	}
	
	public static function change_dugnad()
	{
		#			case "Bytte dugnad":

				/* User is updating info
				------------------------------------------------------------ */
				
				$valid_login = valid_login();
				
				if((int) $formdata["beboer"] == -1 || $valid_login < 1)
				{
					/* Showing default menu
					------------------------------------------------------------ */
				
					$title = "Bytte Dugnad";
					$navigation = "<a href='index.php?beboer=". $formdata["beboer"] ."'>Hovedmeny</a> &gt; Dugnad";
					
					if($valid_login == 0)
					{
						$content = "<p class='failure'>Passordet er ikke korrekt, pr&oslash;v igjen.</a>";
					}
					elseif($valid_login == -1)
					{
						$content = "<p class='failure'>Du har ikke tastet inn ditt passord, vennligst pr&oslash;v igjen.</a>";
					}
					else
					{
						$content = "<p class='failure'>Du har ikke valgt navnet ditt fra nedtrekksmenyen.</a>";					
					}
					
					$page_array = file_to_array("./layout/menu_main.html");
					$content .= output_default_frontpage();
				
				}
				else
				{
					/* VALID LOGIN - showing screen to allow user to change dugnadsdates
					-------------------------------------------------------------------------------- */
					
					global $dugnad_is_empty, $dugnad_is_full;				
					list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

					$content  = update_dugnads();
					update_beboer_room($formdata["beboer"], $formdata["room"]);
			
					$title = "Bytte dugnadsdatoer";
					$navigation = "<a href='index.php'>Hovedmeny</a> &gt; Profilen til ". get_beboer_name($formdata["beboer"], true);
					
					$file = file_to_array("./layout/menu_beboerctrl.html");
					$file["gutta"] = get_dugnadsledere() . $file["gutta"];
					$content .= implode($file);
					
					$content .= "	<div class='bl'><div class='br'><div class='tl'><div class='tr'>		
									<form action='index.php' method='post'>
									<input type='hidden' name='do' value='Bytte dugnad' />
									<input type='hidden' name='beboer' value='". $formdata['beboer'] ."' />
									<input type='hidden' name='pw' value='". $formdata['pw'] ."' />";

					$content .= show_beboer_ctrlpanel($formdata["beboer"]) ."</form></div></div></div></div>";
	
				}
	}
	
	public static function show_list()
	{
			
				/* Showing fill list
				------------------------------------------------------------ */
				
				global $dugnad_is_empty, $dugnad_is_full;				
				list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

				$title = "Komplett dugnadsliste";
				$navigation = "<a href='index.php'>Hovedmeny</a> &gt; Dugnadslisten";
			
				$admin_access = false;
				$content = output_full_list($admin_access);
				break;
			
	}
	
	public static function show_main()
	{
		
	}
}