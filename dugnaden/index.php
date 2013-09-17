<?php

	header("Content-type: text/html; charset=utf-8");

	include "./script/default.php";

	$formdata = get_formdata();
	// print_r($formdata);
	
	if(!empty($formdata["do"]) )
	{

		/* User had selected some action
		------------------------------------------------------ */
		
		switch($formdata["do"])
		{
			case "admin":

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
				
				break;
				
			case "Bytte dugnad":

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
				
				break;
				
			case "Bytte passord":

				/* User wants to change password
				------------------------------------------------------------ */

				$valid_login = valid_login();
				
				if((int) $formdata["beboer"] == -1 || $valid_login < 1)
				{
					/* Invalid login - showing default menu
					------------------------------------------------------------ */
				
					$title = "Bytte passord";
					$navigation = "<a href='index.php?beboer=". $formdata["beboer"] ."'>Hovedmeny</a> &gt; Passord";
					
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
				
					if(!empty($formdata["pw_2"]) && !empty($formdata["pw_b"]) )
					{
						if(!strcmp($formdata["pw_2"], $formdata["pw_b"]) )
						{
							$query = "UPDATE bs_beboer SET beboer_passord = '". $formdata["pw_2"] ."' WHERE beboer_id = '". $formdata["beboer"] ."'";
							@run_query($query);
							
							if(mysql_errno() == 0)
							{
								$feedback = rounded_feedback_box("green", "Ditt nye passord er lagret.");
							}
							else
							{
								$feedback = rounded_feedback_box("red", "Beklager, passordet ble ikke lagret. Ta kontakt med en dugnadsleder.");
							}

							$show_menu = true;
								
						}
						else
						{
							$feedback = rounded_feedback_box("red", "Passordene du valgte stemmer ikke overens, de m&aring; v&aelig;re like...");
						}
					}
					else
					{
						if((empty($formdata["pw_2"]) && !empty($formdata["pw_b"]) ) || (!empty($formdata["pw_2"]) && empty($formdata["pw_b"]) ) )
						{
							$feedback = rounded_feedback_box($color, "Du har ikke fylt inn begge feltene..");
						}
					}

					if(empty($show_menu) )
					{
						$beboer_navn = get_beboer_name($formdata["beboer"], true);
						$title = "Endre passord til ". $beboer_navn;
						$navigation = "<a href='index.php'>Hovedmeny</a> &gt; Passord";
		
						$page_array = file_to_array("./layout/form_pw.html");
						
						$content .= $feedback . $page_array["head"] . "<input type='hidden' name='beboer' value='". $formdata["beboer"] ."' /><input type='hidden' name='pw' value='". $formdata["pw"] ."' />".  $page_array["hidden"] . $beboer_navn . $page_array["beboer_navn"];
					}
					else
					{
						/* Password was either saved or it failed, either way - show the main menu
						--------------------------------------------------------------------------------- */

						$title = "Bytte passord";
						$navigation = "<a href='index.php?beboer=". $formdata["beboer"] ."'>Hovedmeny</a> &gt; Passord";
						
						$page_array = file_to_array("./layout/menu_main.html");
						$content = $feedback . output_default_frontpage();
					}
				}

				break;				
				
			case "Se dugnadslisten uten passord":
			
				/* Showing fill list
				------------------------------------------------------------ */
				
				global $dugnad_is_empty, $dugnad_is_full;				
				list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

				$title = "Komplett dugnadsliste";
				$navigation = "<a href='index.php'>Hovedmeny</a> &gt; Dugnadslisten";
			
				$admin_access = false;
				$content = output_full_list($admin_access);
				break;
			
			default:
			
				/* Default action
				------------------------------------------------------------ */
				$title = "Dugnadsordningen p&aring; nett ". VERSION;
				$navigation = "Hovedmeny";

				$content = output_default_frontpage();
				break;
		}
	}
	else
	{
		$title = "Dugnadsordningen p&aring; nett ". VERSION;
		$navigation = "Hovedmeny";

		$content = output_default_frontpage();
	}

?><html>
<head>
<meta http-equiv=Content-Type content="text/html; charset=UTF-8">

<title>Dugnadsordningen - Blindern Studenterhjem</title>

<meta name="keywords" content="Blindern Studenterhjems dugnadsordning p&aring; nettet." />
<meta name="author" content="Dugnadslederne H. W. Basberg" />

<meta name="robots" content="noindex, nofollow" />

<link href="./css/default<?php print $paper; ?>.css" rel="stylesheet" type="text/css">

</head>

<body <?php if(DEVELOPER_MODE) print "id='red'"; ?>>

<?php
print "			<div class=\"main\">
	<div class=\"navBar\">
		<div class=\"navBar_menu\">". $navigation ."</div>
		<div class=\"navBar_heading\">". $title ." - ". get_usage_count() ."</div>
	</div>
	<div class=\"content\">
		". $content ."
	</div>
</div>\n\n";

/*if(!strcmp($formdata["do"], "admin"))
{
	print "<div class='footer_info'>Ta kontakt med <a target='top' href='http://www.gatada.com/people/johan/contact.php'>Johan H. W. Basberg</a> hvis du har sp&oslash;rsm&aring;l om denne tjenesten.</div>";
}*/
	
print $append_myphpadmin_link /*."<p class=\"footer_info\">Viser ". $using_layout ."</p>"*/;

if (isset($GLOBALS['queries']) && DEVELOPER_MODE && $_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR'])
{
	echo '
	<pre id="queries_list">Spørringer:

'.implode("


", $GLOBALS['queries']).'
</pre>';
}

?>
</body>
</html>
