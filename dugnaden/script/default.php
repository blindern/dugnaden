<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set("Europe/Oslo");

require_once("auth.php");
require_once("config.php");

// --------------------------------------------------------------------------------------- */

$link     = mysql_connect($srv, $usr, $pas);
mysql_set_charset("utf8", $link);
$database = mysql_select_db($db, $link);

function get_formdata()
{

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        foreach ($_POST as $key => $value) {
            $formdata[$key] = $value;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
        foreach ($_GET as $key => $value) {
            $formdata[$key] = $value;
        }
    } else {
        return null;
    }

    return $formdata;
}


/* ******************************************************************************************** *
 *  get_file_content($filename)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_file_content($filename)
{
    $fp = fopen($filename, "r");

    $content = fread($fp, filesize($filename));

    fclose($fp);

    return $content;
}


/* ******************************************************************************************** *
 *  do_admin()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function do_admin()
{
    global $formdata;
    require_admin();

    switch ($formdata["admin"]) {



            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Annulere bot
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Accessed at the beginning of each semester. Lets the entire dugnadsordning be configured.
         *
         *       1. Setting the name of the dugnadsleaders
         *
         *       2. Accessing the calendar to configure which saturdays are valid dugnads
         *
         *       3. Importing students
         *
         *       4. Setting password on the dugnadsordning
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Annulere bot":

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; $title";

            $page = file_to_array("./layout/admin_annulerebot.html");

            $page["hidden"] = " <input type='hidden' name='admin'    value='" . $formdata["admin"] . "' />
                                <input type='hidden' name='do'        value='admin' />" . $page["hidden"];

            /* If user is admin and logged in correctly, update the dugnadsledere... */

            $valid_login = valid_admin_login();

            if (($valid_login == 1 || $valid_login == 2)) {
                if ((int) $formdata["beboer"] > 0) {
                    /* A BEBOER IS CHOSEN TO RECEIVE AN ANNULERING
                    ------------------------------------------------------------------ */

                    $query = "INSERT INTO bs_bot (bot_beboer, bot_annulert) VALUES ('" . $formdata["beboer"] . "', '-1')";
                    @run_query($query);

                    if (@mysql_affected_rows() == 1) {
                        $content .= "<div class='success'>Ny annulering ble tilf&oslash;yd for " . get_beboer_name($formdata["beboer"], true) . "...</div>";
                    } else {
                        $content .= "<div class='failure'>Beklager, det oppstod en feil - annuleringen ble <u>ikke</u> lagret...</div>";
                    }
                    $formdata["beboer"] = null;
                }

                if (isset($formdata["del_dl"])) {
                    /* -------------------------------------------------------------------------------- Annulering of bot .. */

                    /* Used to enumerate the dungad_gjort column */

                    // deltager_gjort == 0: Normal dugnad, carried out by the beboer
                    // deltager_gjort == 1: Bot og ny dugnad
                    // deltager_gjort == 2: Kun ny dugnad
                    // deltager_gjort == 3: Kun bot

                    // Set the $done_type accoringly:

                    $done_type = array(0, 0, 0, 0);
                    $deleted_bots    = 0;
                    $negated_bots    = 0;

                    foreach ($formdata["del_dl"] as $value) {
                        $query = "SELECT bot_id, bot_annulert, bot_registrert, bot_deltager, deltager_gjort
                                    FROM bs_bot, bs_deltager
                                    WHERE     bot_annulert = 0 AND
                                            bot_id = '" . $value . "' AND
                                            deltager_id = bot_deltager
                                    LIMIT 1";

                        $result = @run_query($query);

                        if (@mysql_num_rows($result) == 1) {

                            /* We found a bot we can annulere, as it has not been annulert before..

                               A) If bot_registrert == 1 -> then we have to add a negative bot to negate the wrongly given bot:

                                       1. Update bot_annulert -> 1

                                       2. Add a bot with negative ONE_BOT (set bot_annulert = -1; to avoid recursive negating - hiding the annuleringsbot)

                               B) .. or the bot has yet not been registered, meaning it can simply be deleted:

                                    3. Delete the bot itself.

                                    4. Change the type of punishment given to the beboer for the day the bot was given.

                            --------------------------------------------------------------------------------------------------------- */

                            /* getting the deltager_id from the query above: */
                            $row = @mysql_fetch_array($result);

                            if ((int) $row["bot_registrert"] == 1) {
                                /* A) ---- 1. */

                                $negated_bots++;

                                $query = "UPDATE bs_bot SET bot_annulert = '1' WHERE bot_id = '" . $value . "'";
                                @run_query($query);

                                /* A) ---- 2. */

                                $query = "INSERT INTO bs_bot (bot_deltager, bot_annulert) VALUES ('" . $row['bot_deltager'] . "', '-1')";
                                @run_query($query);
                            } else /* just delete the bot as it has not yet been fakturert beboer */ {
                                /* B) ---- 3. */

                                $deleted_bots++;

                                $query = "DELETE FROM bs_bot WHERE bot_id = '" . $value . "'";
                                @run_query($query);

                                /* B) ---- 4. */

                                $query = "UPDATE bs_deltager SET deltager_gjort = '" . $done_type[$row["deltager_gjort"]] . "' WHERE deltager_id = '" . $row["bot_deltager"] . "'";
                                @run_query($query);
                            }
                        }

                        if (@mysql_errno()) {
                            $page["feedback"] = "<div class='failure'>Det oppstod en feil under annulering av boten...</div>" . $page["feedback"];
                            $error = true;
                        }
                    }

                    if ($deleted_bots + $negated_bots) {
                        $ord_negert  = ($negated_bots > 1 ? "b&oslash;ter" : "bot");
                        $ord_slettet = ($deleted_bots > 1 ? "b&oslash;ter" : "bot");

                        $page["feedback"] = "<div class='success'>" . ($deleted_bots ? "Det ble slettet " . $deleted_bots . " ufakturert" . ($deleted_bots > 1 ? "e" : null) . " " . $ord_slettet . ". " : null) .
                            ($negated_bots ? "Det ble annulert " . $negated_bots . " " . $ord_negert . "." : null) . "</div>" . $page["feedback"];
                    }
                }
            }

            /* Generate first month of the semester, so bots from the previous semester is not shown...
            ---------------------------------------------------------------------------------------------------------------- */

            $year    = date("Y", time());
            $month    = date("m", time());
            $day    = date("d", time());

            if ($month > 7) {
                /* We now know we are in the autumn semester
                -------------------------------------------------------- */
                $month_start = "08";
            } else {
                /* .. Spring
                -------------------------------------------------------- */
                $month_start = "01";
            }

            $start_date = $year . "." . $month_start . ".01 00:00:00";

            /* If admin uses SUPERUSER password, show all bots ever given to beboers:
            ---------------------------------------------------------------------------------------------------------------- */

            $query = "SELECT bot_id, bot_annulert, bot_registrert, beboer_id, dugnad_dato
                        FROM bs_dugnad, bs_bot, bs_deltager, bs_beboer
                        WHERE bot_deltager = deltager_id AND
                                beboer_id = deltager_beboer AND
                                deltager_dugnad = dugnad_id AND
                                bot_annulert >= '0'
                        ORDER BY beboer_for, beboer_etter, dugnad_dato";

            $result = @run_query($query);

            /* Adding all bot given to beboere ...
            ---------------------------------------------------------------------------------------------------------------- */

            $line_count = 0;
            $bot_count = 0;
            $annulert_count = 0;

            $min_dugnadsdato = $year . "." . $month . "." . $day . " 00:00:00";

            if (@mysql_num_rows($result)) {
                while ($row = @mysql_fetch_array($result)) {
                    // Setting text AND background color: requires a double if
                    if ((int) $row["bot_annulert"] == 1) {
                        // This item is disabled (annulert)
                        $row_Text_and_Background = "_disabled";
                        $desc = "(annulert)";
                    } else {
                        $row_Text_and_Background = null;
                        $desc = null;
                    }

                    if ($line_count++ % 2) {
                        $row_Text_and_Background .= "_odd";
                    }

                    $all_dl .= "<div class='row" . $row_Text_and_Background . "'><div class='check_left'><input type='checkbox' name='del_dl[]' " . ((int) $row["bot_annulert"] == 1 ? "checked='checked' disabled " : null) . "value='" . $row['bot_id'] . "'></div><div class='name_wide" . (!(int) $row["bot_registrert"] ? "_success" : null) . "'>" . $line_count . ". " . get_beboerid_name($row['beboer_id']) . "</div><div class='when'>" . get_simple_date($row["dugnad_dato"], true) . " " . $desc . "</div></div>\n";

                    if (!$row["bot_annulert"]) $bot_count++;
                    else $annulert_count++;

                    if ($row["dugnad_dato"] < $min_dugnadsdato) {
                        $min_dugnadsdato = $row["dugnad_dato"];
                    }
                }
            } else {
                $all_dl = "<p class='failure'>Ingen b&oslash;ter er tilgjengelig for annulering.</p>";
            }

            /* Just som simple count statistics .. */

            if ($bot_count) {
                $simple_stats = "Totalt har dugnaden dratt inn " . $bot_count * ONE_BOT . " kroner ";
            }

            /* How many bots have been cancelled? */

            if ($annulert_count) {
                $ord_negert  = ($negated_bots > 1 ? "b&oslash;ter" : "bot");
                $simple_stats .= ($bot_count ? " og " : null) . $annulert_count . " " . $ord_negert . " har blitt annulert ";
            }

            if ($bot_count + $annulert_count) {
                $page["semester_total"] = $simple_stats . ($valid_login == 1 ? " dette semesteret alene." : " siden " . get_simple_date($min_dugnadsdato, true) . ".") . $page["semester_total"];
            }


            $page["botliste"] = $all_dl . $page["botliste"];

            $admin_buttons = "\t\t\t\t\t\t\t<div class='bl_green'>
                                <div class='br_green'>
                                    <div class='tl_green'>
                                        <div class='tr_green'>
                                            Tilf&oslash;y ny annulering manuelt fordi boten ikke finnes i Botlisten over: " . get_vedlikehold_beboer_select() . "
                                        </div>
                                    </div>
                                </div>
                            </div>\n";


            /* SHOW THE LIST OF ALL ANNULERTE WITHOUT AN ASSOCIATED DUGNAD
            ----------------------------------------------------------------- */

            $query = "SELECT beboer_for AS fornavn, beboer_etter AS etternavn

                        FROM bs_bot, bs_beboer

                        WHERE     bot_annulert = -1
                            AND bot_beboer = beboer_id";

            $bot_result = @run_query($query);

            while (list($fornavn, $etternavn, $dato) = @mysql_fetch_row($bot_result)) {
                // This item is disabled (annulert)
                $row_Text_and_Background = "_disabled";

                if ($line_count++ % 2) {
                    $row_Text_and_Background .= "_odd";
                }

                $annulert_liste .= "<div class='row" . $row_Text_and_Background . "'><div class='check_left'><input type='checkbox' name='x' value='33' checked='checked' disabled='disabled'></div><div class='name_wide'>" . $line_count . ". " . $fornavn . " " . $etternavn . "</div><div class='when'>(Etterbehandlet)</div></div>";
            }

            $page["ny_annulering"] = $annulert_liste . $admin_buttons . $page["ny_annulering"];

            $content .= implode($page);

            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Rette beboernavn
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Accessed at the beginning of each semester. Lets the entire dugnadsordning be configured.
         *
         *       1. Setting the name of the dugnadsleaders
         *
         *       2. Accessing the calendar to configure which saturdays are valid dugnads
         *
         *       3. Importing students
         *
         *       4. Setting password on the dugnadsordning
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Rette beboernavn":

            /* User has not chosen a valid action
            ------------------------------------------------------------ */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; $title";

            $page = file_to_array("./layout/menu_name.html");

            $page["hidden"] = "<input type='hidden' name='admin' value='" . $formdata["admin"] . "'><input type='hidden' name='do' value='admin'>" . $page["hidden"];

            $valid_login = valid_admin_login();


            if ($valid_login == 1 && verify_person_id($formdata["beboer"]) && isset($formdata["first"]) && isset($formdata["last"]) && strcmp($formdata["go"], "Tilbake")) {
                /* Admin has logged in and entered new values for a beboer, time to save them: */

                $query = "UPDATE bs_beboer SET beboer_for = '" . $formdata["first"] . "', beboer_etter = '" . $formdata["last"]
                    . "'    WHERE beboer_id = '" . $formdata["beboer"] . "'";

                @run_query($query);

                if (@mysql_errno() == 0) {
                    $feedback .= "<div class='success'>Vellykket oppdatering, n&aring; heter beboeren " . get_beboerid_name($formdata["beboer"]) . ".</div>";

                    $formdata["beboer"] = "-1";
                    $formdata["beboer"] = null;
                    $valid_login = false;
                } else {
                    $feedback .= "<div class='failure'>Det oppstod en feil, navnet ble ikke oppdatert...</div>";
                }
            }

            if ($valid_login != 1 || (!isset($formdata["beboer"]) || (int) $formdata["beboer"] == -1) || (isset($formdata["go"]) && !strcmp($formdata["go"], "Tilbake"))) {
                /* Either wrong password, no password, no beboer selected og the button "Tilbake" has been clicked: */

                if ($valid_login != 1) {
                    $feedback .= "<div class='failure'>Du har ikke rettigheter til denne funksjonen.</div>";
                }

                if (!strcmp($formdata["beboer"], "-1")) {
                    $feedback .= "<div class='failure'>Du m&aring; velge en beboer med feil i navnet fra nedtrekksmenyen...</div>";
                } elseif (isset($formdata["go"]) && !strcmp($formdata["go"], "Tilbake")) {
                    $formdata["beboer"] = "-1";
                }

                $dugnadsleder = false;
                $page["beboer_bytte"] = "1. " . get_beboer_select($dugnadsleder) . "&nbsp;&nbsp;&nbsp;&nbsp; <input type='submit' value='OK'><br />" .
                    "<div class='hint'>2. Fyll inn nytt etternavn og fornavn...</div>" . $page["beboer_bytte"] . $page["beboer_bytte"];
            } else {
                $page["hidden"] = "<input type='hidden' name='beboer' value='" . $formdata["beboer"] . "'>" . $page["hidden"];

                $page["beboer_bytte"] = "<div class='hint'>1. Fyll inn nytt fornavn og etternavn</div><br />
                                        2. <input type='input' name='last' value='" . get_beboerid_name($formdata["beboer"], false, true) . "'>,
                                        <input type='input' name='first' value='" . get_beboerid_name($formdata["beboer"], false) . "'>
                                        <input type='submit' name='admin' value='Rette beboernavn'>
                                        <input type='submit' name='go' value='Tilbake'>" . $page["beboer_bytte"];
            }

            $content = $feedback . implode($page);
            break;


            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Innstillinger
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Accessed at the beginning of each semester. Lets the entire dugnadsordning be configured.
         *
         *       1. Setting the name of the dugnadsleaders
         *
         *       2. Accessing the calendar to configure which saturdays are valid dugnads
         *
         *       3. Importing students
         *
         *       4. Setting password on the dugnadsordning
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Endre status":

            if (valid_admin_login() == 1) {
                if (get_innstilling("open_season", "1")) {
                    set_innstilling("open_season", "0");
                } else {
                    set_innstilling("open_season", "1");
                }

                $show_password = " (" . get_innstilling("pw_undergruppe") . ")";
            }

            // fall through

        case "Innstillinger":

            /* User has not chosen a valid action
            ------------------------------------------------------------ */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; $title";

            $page = file_to_array("./layout/admin_mainmenu.html");

            $page["hidden"] =    '<input type="hidden" name="do" value="admin" />' . $page["hidden"];
            $page["open_season_status"] = (get_innstilling("open_season", "1") ?
                "<span class='success'>Passordet" . $show_password . " er aktivt.</span>" :
                "<span class='failure'>Passordet" . $show_password . " er inaktivt.</span>") . $page["open_season_status"];

            $page["fri_url"] =    "<a href='" . DUGNADURL . "fri/'>" . DUGNADURL . "fri/</a>" . $page["fri_url"];

            $content .= implode($page);

            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Tildele dugnad
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Gives all beboere 2 new dugnads.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Tildele dugnad":

            $page = file_to_array("./layout/admin_tildeledugnad.html");
            $valid_login = valid_admin_login();
            $c = 0;

            $query = "SELECT dugnad_id FROM bs_dugnad WHERE dugnad_slettet = '0' AND " . get_dugnad_range() . " ORDER BY dugnad_dato";
            $result = @run_query($query);
            $dugnad_count = @mysql_num_rows($result);


            $query = "SELECT DISTINCT beboer_id AS id, beboer_spesial AS spesial
                        FROM bs_beboer
                        WHERE beboer_spesial = '0' OR beboer_spesial = '8'";
            $result = @run_query($query);
            $beboer_count = @mysql_num_rows($result);

            /* Calculates how many deltagere do we need on each dugnad. */
            $per_dugnad = (int) (($beboer_count * 2) / $dugnad_count) + ((($beboer_count * 2) % $dugnad_count) > 0);

            $content .= "<div class='failure'>" . $beboer_count . " dugnadspliktige beboere fordelt p&aring; " . $dugnad_count . " l&oslash;rdager gir " . $per_dugnad . " barn per dugnad.<br /></div>";

            if (truncateAllowed() == false) {
                $content .= "<p class='failure'>Denne operasjonenen er ikke tillatt etter at dugnadsperioden har startet.</p>";
            } elseif (isset($_POST['performit'])) {
                $beboerGiven = 0;
                $dugnadGiven = 0;
                $forceCount = 0;

                while (list($beboer_id, $special) = @mysql_fetch_row($result)) {
                    if ($special == 8) {
                        $forceCount = 1;
                    } else {
                        $forceCount = 2;
                    }

                    $dugnadGiven += forceNewDugnads($beboer_id, $forceCount, $per_dugnad);
                    $beboerGiven += 1;
                }

                $content .= "<div class='success'>Totalt ble " . $beboerGiven . " dugnadspliktige beboere tildelt " . sprintf("%.5f", ($dugnadGiven / $beboerGiven)) . " dugnader i snitt.<br /></div>";
            } else {
                $page["pw_line"] = "<p>
                                        <input type='hidden' name='performit' value='1' />
                                        <input type='submit' name='admin' value='Tildele dugnad'>
                                        <input type='submit' name='admin' value='Semesterstart'>
                                    </p>" . $page["pw_line"];
            }

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; <a href='index.php?do=admin&admin=Semesterstart'>Semesterstart</a> &gt; $title";

            $content .= implode($page);
            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Semesterstart
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Accessed at the beginning of each semester. Lets the entire dugnadsordning be configured.
         *
         *       1. Setting the name of the dugnadsleaders
         *
         *       2. Accessing the calendar to configure which saturdays are valid dugnads
         *
         *       3. Importing students
         *
         *       4. Setting password on the dugnadsordning
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Semesterstart":

            /* User has not chosen a valid action
            ------------------------------------------------------------ */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; $title";

            $page = file_to_array("./layout/menu_semesterstart.html");

            if (truncateAllowed() == false) {
                // $page["disable_kalender"] = "disabled='disabled'" . $page["disable_kalender"];
                $page["disable_import"] = "disabled='disabled'" . $page["disable_import"];
                $page["disable_tildele"] = "disabled='disabled'" . $page["disable_tildele"];
            }

            $content .= implode($page);

            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Administrere dugnadslederne
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Accessed at the beginning of each semester. Lets the entire dugnadsordning be configured.
         *
         *       1. Setting the name of the dugnadsleaders
         *
         *       2. Accessing the calendar to configure which saturdays are valid dugnads
         *
         *       3. Importing students
         *
         *       4. Setting password on the dugnadsordning
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Dugnadslederstyring":

            /* User has not chosen a valid action
            ------------------------------------------------------------ */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; $title";

            $page = file_to_array("./layout/admin_dugnadsledere.html");

            /* If user is admin and logged in correctly, update the dugnadsledere... */

            $valid_login = valid_admin_login();

            if ($valid_login == 1) {
                if ((int) $formdata["beboer"] != -1) {
                    if (set_dugnadsleder($formdata["beboer"]) != 0) {
                        $content .= "<div class='failure'>Det oppstod en feil under tilf&oslash;yelse av ny dugnadsleder...</div>";
                    }

                    $formdata["beboer"] = null;
                }

                if (isset($formdata["del_dl"])) {
                    /* Deleting dugnadsledere .. */

                    foreach ($formdata["del_dl"] as $value) {
                        if (delete_dugnadsleder($value) != 0) {
                            $content .= "<div class='failure'>Det oppstod en feil under sletting av dugnadsleder...</div>";
                        }
                    }
                }
            } else {
                $content .= "<div class='failure'>Du har ikke rettigeter til denne funksjonen.</div>";
            }

            $page["hidden"] = " <input type='hidden' name='admin'    value='Dugnadslederstyring'>
                                <input type='hidden' name='do'        value='admin'>" . $page["hidden"];

            $result = get_result("dugnadsleder");

            /* Adding all dugnadsledere */

            while ($row = @mysql_fetch_array($result)) {
                $all_dl .= "<input type='checkbox' name='del_dl[]' value='" . $row['value'] . "'>" . get_beboerid_name($row['value']) . "<br />";
            }

            $page["dugnadslederne"] = $all_dl . $page["dugnadslederne"];

            /* Showing all beboere in a drop-down box */

            $dugnadsleder = true;
            $page["andre_beboere"] = get_beboer_select($dugnadsleder) . $page["andre_beboere"];

            $content .= implode($page);

            break;


            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Skifte passord
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Lets the user change the admin password.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Skifte passord":

            /* User has not chosen a valid action
            ------------------------------------------------------------ */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; $title";

            $page = file_to_array("./layout/admin_pw.html");
            $page["hidden"] = "<input type='hidden' name='do' value='admin'><input type='hidden' name='admin' value='Skifte passord'>" . $page["hidden"];

            if (!empty($formdata["pw_2"]) && !empty($formdata["pw_b"])) {
                $valid_login = valid_admin_login();

                if ($valid_login == 1 && !strcmp($formdata["pw_2"], $formdata["pw_b"])) {
                    if (set_password($formdata["pw_2"], $formdata["bytte"]) == 0) {
                        /* Password was successfully changed .. */
                        $page["feedback"] = "<p class='success'>Du har endret passordet som brukes av " . ucfirst($formdata["bytte"]) . ". Gi beskjed!</p>" . $page["feedback"];
                    } else {
                        /* Password was NOT changed .. */
                        $page["feedback"] = "<p class='failure'>Det oppstod en feil under oppdatering av passordet. Passordet er uendret.</p>" . $page["feedback"];
                    }
                } elseif ($valid_login) {
                    $page["feedback"] = "<p class='failure'>Beklager, det nye passordet ble ikke bekreftet. Pr&oslash;v igjen...</p>" . $page["feedback"];
                } else {
                    $page["feedback"] = "<p class='failure'>Du har ikke rettigheter til denne funksjonen.</p>" . $page["feedback"];
                }
            }

            $content = implode($page);

            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Endre Buatelefon / Skifte telefon / Oppdatere buatelefon / Festforening buatelefon / mobil til bua
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Lets the admin change the number used to dial Festforeningen
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Endre Buatelefon":

            /* User has not chosen a valid action
            ------------------------------------------------------------ */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; $title";

            $page = file_to_array("./layout/admin_buatelefon.html");

            if (!empty($formdata["buatelefon"])) {
                if (valid_admin_login() == 1) {
                    if (update_buatelefon($formdata["buatelefon"]) == 1) {
                        /* Password was successfully changed .. */
                        $page["hidden"] = "<p class='success'>Buatelefonene ble oppdatert til " . get_buatelefon() . ".</p>" . $page["hidden"];
                    } else {
                        /* Password was NOT changed .. */
                        $page["hidden"] = "<p class='failure'>Beklager, det oppstod en feil under oppdatering. Buatelefonen forble uendret.</p>" . $page["hidden"];
                    }
                } else {
                    $page["hidden"] = "<p class='failure'>Du har ikke rettighet til denne funksjonen.</p>" . $page["hidden"];
                }
            } else {
                $page["hidden"] = "<p class='failure'>Vennligst tast inn et nytt telefonnummer f&oslash;r du oppdaterer.</p>" . $page["hidden"];
            }

            $page["hidden"] = "<input type='hidden' name='do' value='admin'><input type='hidden' name='admin' value='Endre Buatelefon'>" . $page["hidden"];

            $page["telefon"] = "<span class='success'>" . get_buatelefon() . "</span>" . $page["telefon"];

            $content = implode($page);

            break;


            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Infoliste
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Generates a list with notes with dugnadsinfo targeted at the beboer. This should be distributed at semesterstart.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Infoliste":

            $valid_login = valid_admin_login();

            if ($valid_login == 1) {
                global $paper;
                $paper = "_paper";

                $formdata["view"] = "Infoliste";

                // DISABLING LOGIN FOR VAKTGRUPPESJEFEN, FESTFORENINGEN AND RYDDEVAKTSJEFEN
                // THIS WILL AUTOMATICALLY BE ENABLED WHEN ALL SATURDAYS AGAIN ARE ADDED!
                set_innstilling("open_season", "0");

                $element_count = 0;

                $flyer = file_to_array("./layout/flyer_passord.html");

                $query = "SELECT

                                beboer_id,
                                beboer_passord,
                                beboer_for,
                                beboer_etter,
                                (rom_nr + 0) AS rom_int,
                                rom_nr AS rom_alpha,
                                rom_type

                            FROM bs_beboer

                                LEFT JOIN bs_rom
                                    ON rom_id = beboer_rom

                            ORDER BY rom_int, rom_alpha, beboer_etter, beboer_for";

                $result = @run_query($query);

                $dugnadsledere = get_dugnadsledere();
                while ($row = @mysql_fetch_array($result)) {
                    $undone_dugnads = get_undone_dugnads($row["beboer_id"]);

                    if (!empty($undone_dugnads)) {
                        $new_flyer = $flyer;

                        $new_flyer["rom_info"] = get_public_lastname($row["beboer_etter"], $row["beboer_for"], false, true) . "<br />" .
                            (!strcmp($row["rom_int"], $row["rom_alpha"]) ? $row["rom_int"] : $row["rom_alpha"]) . $row["rom_type"] . $new_flyer["rom_info"];

                        if ($element_count++ % 2) {
                            $new_flyer["format_print"] = "_break" . $new_flyer["format_print"];
                        }

                        $new_flyer["gutta"] = $dugnadsledere . $new_flyer["gutta"];
                        $new_flyer["dugnad_url"] = DUGNADURL . $new_flyer["dugnad_url"];
                        $new_flyer["dugnad_dugnad"] = $undone_dugnads . $new_flyer["dugnad_dugnad"];
                        $new_flyer["passord"] = $row["beboer_passord"] . $new_flyer["passord"];

                        $content .= implode($new_flyer);
                    }
                }
            } else {
                $feedback = "<div class='failure'>Beklager, du har ikke rettigheter til denne operasjonen.</div>";
            }

            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Botliste
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Displayes the bots still not added to the rent, or the complete list if Superadmin password is used.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Botliste":

            /* SHOWING THE PAPER LAYOUT - ALL BOTS
            -------------------------------------------------------------------------------- */

            $valid_login = valid_admin_login();
            $doit = !empty($formdata["prepare"]) || !empty($formdata["printok"]);

            if ($valid_login == 2 && $doit) {

                $query = "SELECT bot_id
                            FROM     bs_bot,
                                    bs_deltager,
                                    bs_dugnad

                            WHERE
                                    bot_registrert = 0 AND
                                    bot_deltager = deltager_id AND
                                    deltager_dugnad = dugnad_id AND
                                    dugnad_checked = 1";

                $result = @run_query($query);

                if (@mysql_num_rows($result)) {
                    while (list($id) = @mysql_fetch_row($result)) {
                        $query = "UPDATE bs_bot SET bot_registrert = '1' WHERE bot_registrert = '0' AND bot_id = " . $id;
                        @run_query($query);
                    }
                }

                // REMOVNIG ALL ANNULERINGER THAT HAS NO ASSOCIATED DUGNAD
                $query = "UPDATE bs_bot SET bot_registrert = '1' WHERE bot_beboer <> 0 AND bot_registrert = '0'";
                @run_query($query);

                $feedback .= "<div class='success'>Botlisten er t&oslash;mt og klargjort for ny faktureringsperiode.</div>";

                /* User has not chosen a valid action
                ------------------------------------------------------------ */

                $title = "Admin";
                $navigation = "<a href='index.php'>Hovedmeny</a> &gt; Admin";

                $content = $feedback . get_file_content("./layout/menu_admin.html");
            } elseif (($valid_login == 1 || $valid_login == 2) && $doit) {
                global $paper;
                $paper = "_paper";


                if ($valid_login == 2) {
                    /* Use this filter only if the user is not logging in with the SUPERUSER password. */

                    $not_SUPERUSER = "AND bot_registrert <= '0'";
                }

                $formdata["view"] = "Botliste";

                $line_count = 0;

                $max_time = 0;
                $min_time = time();

                $flyer = file_to_array("./layout/flyer_botlist.html");

                /* ADDING ALL BOTS AND ANNULERINGER THAT ARE
                   FROM DUGNADS/DELTAGELSE THAT STILL EXSITS.
                   ---------------------------------------------------------- */

                $query = "SELECT    bot_id,
                                    bot_annulert,

                                    dugnad_id AS id,
                                    dugnad_dato AS dato,

                                    deltager_notat AS notat,

                                    beboer_for,
                                    beboer_etter

                            FROM bs_bot, bs_deltager,bs_dugnad, bs_beboer

                            WHERE
                                    deltager_beboer = beboer_id
                                AND deltager_dugnad = dugnad_id
                                AND    bot_deltager = deltager_id

                                " . $not_SUPERUSER . "
                                AND deltager_gjort <> '0'
                                AND dugnad_checked = '1'

                            ORDER BY beboer_etter, beboer_for";

                $result = @run_query($query);

                while ($row = @mysql_fetch_array($result)) {

                    $dug_time = make_unixtime($row["id"]);

                    if ($dug_time < $min_time) {
                        $min_time = $dug_time;
                        $min_date = $row["dato"];
                    }

                    if ($dug_time > $max_time) {
                        $max_time = $dug_time;
                        $max_date = $row["dato"];
                    }

                    $entries .= "<div class='row" . (++$line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $line_count . ". " . $row["beboer_etter"] . ", " . $row["beboer_for"]  . "</div>\n<div class='when'>" . get_simple_date($row["dato"], true) . "</div><div class='note'>" . $row["notat"] . "&nbsp;</div><div class='note'>" . (!strcmp($row["bot_annulert"], "-1") ? "-" : null) . ONE_BOT . " kroner&nbsp;" . (!strcmp($row["bot_annulert"], "-1") ? "(ettergitt)" : null) . "</div><div class='spacer'>&nbsp;</div></div>\n\n";

                    $c++;
                }

                /* ADDING ALL THAT ARE ONLY ANNULERING, WITH
                   DELTAGELSE == 0
                   ---------------------------------------------------------- */

                $query = "SELECT    bot_id,
                                    bot_annulert,

                                    beboer_for,
                                    beboer_etter

                            FROM bs_bot, bs_beboer

                            WHERE    bot_beboer = beboer_id

                                " . $not_SUPERUSER . "

                            ORDER BY beboer_etter, beboer_for";

                $result = @run_query($query);

                while ($row = @mysql_fetch_array($result)) {

                    $dug_time = "Etterbehandlet";
                    $entries .= "<div class='row" . (++$line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $line_count . ". " . $row["beboer_etter"] . ", " . $row["beboer_for"]  . "</div>\n<div class='when'>" . $dug_time . "</div><div class='note'></div><div class='note'>" . (!strcmp($row["bot_annulert"], "-1") ? "-" : null) . ONE_BOT . " kroner&nbsp;" . (!strcmp($row["bot_annulert"], "-1") ? "(ettergitt)" : null) . "</div><div class='spacer'>&nbsp;</div></div>\n\n";

                    $c++;
                }



                /* Creating the page
                ------------------------------------------------------------- */

                $flyer["time_space"] = get_simple_date($min_date, true) . " til " . get_simple_date($max_date, true) . $flyer["time_space"];
                $flyer["dugnad_dugnad"] = $entries . $flyer["dugnad_dugnad"];

                $content .= implode($flyer);
            } else {
                $title = "Vise botlisten";
                $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Botliste";

                $admin_login = file_to_array("./layout/admin_login_botlist.html");

                $bot_count = get_bot_count();

                if ($bot_count == 0) {
                    $admin_login["bot_count"] = "<div class='failure'>Det er for tiden ingen nye b&oslash;ter &aring; skrive ut.</div>" . $admin_login["bot_count"];
                } else {
                    $admin_login["bot_count"] = "<div class='success'>Det er registrert " . $bot_count . ($bot_count > 1 ? " nye b&oslash;ter som er klare " : " ny bot som er klar") . " for fakturering.</div>" . $admin_login["bot_count"];
                }

                $admin_login["hidden"] = "<input type='hidden' name='admin' value='Botliste'>" . $admin_login["hidden"];

                $content = $feedback . implode($admin_login);
            }
            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Neste dugnadsliste
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Generates the list for the next dugnad.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Neste dugnadsliste":

            /* SHOWING THE PAPER LAYOUT - DUGNADSLISTE
            -------------------------------------------------------------------------------- */

            $valid_login = valid_admin_login();

            if ($valid_login == 1 && isset($_POST['dugnadsleder'])) {
                global $paper;
                $paper = "_dugnadsliste";

                $formdata['view'] = "Dugnadsliste";

                $query = "SELECT dugnad_id
                            FROM bs_dugnad
                            WHERE dugnad_dato > CURDATE()
                            AND dugnad_slettet ='0' AND dugnad_type = 'lordag' ORDER BY dugnad_dato  LIMIT 1 ";

                $result = @run_query($query);
                $row = @mysql_fetch_array($result);

                $fullname = false;
                $content = "<h1 class='big'>Dugnad" . (!empty($formdata["dugnadsleder"]) && (int) $formdata["dugnadsleder"] != -1 ? " med " . ($name = get_beboerid_name($formdata["dugnadsleder"], $fullname)) . ($name == "Karl-Martin" ? " - 971 59 266" : ($name == "Theodor Tinius" ? " - 400 41 458" : "")) : "sinnkalling") . "</h1>

                <p>
                    M&oslash;t i peisestuen if&oslash;rt antrekk som passer til b&aring;de innend&oslash;rs-
                    og utend&oslash;rsarbeid. Møt tidsnok for å unngå bot.
                </p>\n\n";

                $show_expired_days = false;
                $editable = false;
                $dugnadsliste_full_name = true;

                $content .= show_day($row["dugnad_id"], $show_expired_days, $editable, $dugnadsliste_full_name) . '
                <p>Ta kontakt med dugnadsleder ved spørsmål.</p>';
            } elseif (isset($_POST['dugnadsleder'])) {
                $feedback .= "<div class='failure'>Du har ikke rettigheter til denne funksjonen.</div>";
            } else {
                $title = "Neste dugnadsliste";
                $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Botliste";

                $admin_login = file_to_array("./layout/admin_login_dugnadlist.html");


                /* Making the select drop-down box with all dugnadsledere .. */

                $select = "<select name='dugnadsleder'><option value='-1'>Velg dugnadsleder</option>";

                $result = get_result("dugnadsleder");

                while ($row = @mysql_fetch_array($result)) {
                    $fullname = false;
                    $select .= "<option value='" . $row["value"] . "'>" . get_beboerid_name($row["value"], $fullname) . "</option>";
                }

                $select .= "</select>";

                $admin_login["dugnadledere"] = $select . $admin_login["dugnadledere"];
                $admin_login["hidden"] = "<input type='hidden' name='admin' value='Neste dugnadsliste'>" . $admin_login["hidden"];

                $content = $feedback . implode($admin_login);
            }

            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Oppdatere siste
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Updates the beboerstatus for the last arranged dugnad.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Oppdatere siste":



            /* ---------------------------------------------------------------------------------------------------------------------------- *
             * STRAFFEDUGNADSLAPPER
             * ---------------------------------------------------------------------------------------------------------------------------- */


            if (strcmp($formdata["view"], "Straffedugnadslapper")) {
                /* User want to update dugnad status
                ------------------------------------------------------------ */

                $title = "Oppdatere siste dugnadliste";
                $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Ajourf&oslash;re";

                $valid_login = valid_admin_login();

                if ($valid_login == 1 && !empty($formdata["newn"]) && get_beboer_name($formdata["newn"]) && empty($formdata["notat"])) {
                    /* Valid beboer - showing notat form ..
                    ------------------------------------------------ */
                    $admin_login = file_to_array("./layout/admin_notat.html");

                    $show = (!empty($formdata["show"]) ? "<input type='hidden' name='show' value='" . $formdata["show"] . "'>\n" : null);

                    if (isset($formdata["next"])) {
                        $show .= "<input type='hidden' name='next' value='go'>\n";
                    } elseif (isset($formdata["prev"])) {
                        $show .= "<input type='hidden' name='prev' value='go'>\n";
                    }

                    $sort_by = (!empty($formdata["sorts"]) ? $formdata["sorts"] : "last");

                    $admin_login["hidden"] = "<input type='hidden' name='do' value='admin'>\n" .
                        "<input type='hidden' name='sorts' value='" . $sort_by . "'>\n" .
                        "<input type='hidden' name='admin' value='" . $formdata["admin"] . "'>\n" .
                        "<input type='hidden' name='beboer' value='" . $formdata["newn"] . "'>\n" . $show .
                        $admin_login["hidden"];

                    $admin_login["beboer"] = get_beboer_name($formdata["newn"]) . $admin_login["beboer"];

                    $content = implode($admin_login);
                } elseif ($valid_login == 1) {

                    /* Behandling av siste dugnadsliste
                    ---------------------------------------------------------- */

                    global $dugnad_is_empty, $dugnad_is_full;
                    list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

                    $feedback .= update_dugnads();

                    /* Updating reference list
                    ------------------------------------------------ */
                    list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();


                    if (!empty($formdata["deln"])) {
                        delete_note($formdata["deln"]);
                    } elseif (!empty($formdata["notat"]) && !empty($formdata["beboer"]) && get_beboer_name($formdata["beboer"])) {
                        /* Valid beboer - adding notat ..
                        ------------------------------------------------ */

                        if (insert_note($formdata["beboer"], $formdata["notat"], $formdata["mottaker"])) {
                            $feedback .= "<p class='success'>Nytt notat ble lagret.</p>";
                        } else {
                            $feedback .= "<p class='failure'>Det oppstod en feil, nytt notat ble ikke lagret.</p>";
                        }
                    }

                    $result = @run_query("
                        SELECT dugnad_id, dugnad_dato, dugnad_type
                        FROM bs_dugnad
                        WHERE dugnad_slettet = '0'
                        ORDER BY dugnad_dato");
                    $all_dugnads = [];
                    while ($row = mysql_fetch_assoc($result)) {
                        $all_dugnads[$row['dugnad_id']] = $row;
                    }

                    $dugnad_id = null;
                    if (empty($formdata["show"]) || !isset($all_dugnads[$formdata['show']])) {
                        foreach ($all_dugnads as $dugnad) {
                            if ($dugnad_id === null || strtotime($dugnad['dugnad_dato']) < time()) {
                                $dugnad_id = $dugnad['dugnad_id'];
                            }
                        }
                    } else {
                        $dugnad_id = $formdata['show'];

                        if (isset($formdata["prev"])) {
                            $prev = null;
                            foreach ($all_dugnads as $dugnad) {
                                if ($dugnad['dugnad_id'] == $dugnad_id) {
                                    if ($prev) {
                                        $dugnad_id = $prev;
                                    }
                                    break;
                                }
                                $prev = $dugnad['dugnad_id'];
                            }
                        } elseif (isset($formdata["next"])) {
                            $next = null;
                            foreach ($all_dugnads as $dugnad) {
                                if ($next) {
                                    $dugnad_id = $dugnad['dugnad_id'];
                                    break;
                                }
                                if ($dugnad['dugnad_id'] == $dugnad_id) {
                                    $next = true;
                                }
                            }
                        }
                    }

                    if ($dugnad_id) {
                        if (isset($formdata["done"]) && !strcmp($formdata["done"], "Merke dagen som ferdigbehandlet")) {
                            $query = "UPDATE bs_dugnad SET dugnad_checked = '1' WHERE dugnad_id = '" . $dugnad_id . "'";
                            @run_query($query);
                        } elseif (isset($formdata["done"]) && !strcmp($formdata["done"], "Angre ferdigbehandling")) {
                            $query = "UPDATE bs_dugnad SET dugnad_checked = '0' WHERE dugnad_id = '" . $dugnad_id . "'";
                            @run_query($query);
                        }

                        $show_status = update_status_on_all($dugnad_id);


                        if (get_straff_count($dugnad_id) > 0) {
                            $straff = "<input type='submit' name='view' value='Straffedugnadslapper'> ";
                        }

                        /* Top navigational buttons
                        ----------------------------------------------------------------- */
                        $content  = "<form action='index.php' method='post'>
                                        <input type='hidden' name='do' value='admin'>
                                        <input type='hidden' name='admin' value='Oppdatere siste'>
                                        <input type='hidden' name='show' value='" . $dugnad_id . "'>
                                        <input type='submit' name='prev' value='&larr;'><input type='submit' name='next' value='&rarr;'> " . $straff . $nav_status . "</form>";


                        /* The form to the actual list of beboere
                        ----------------------------------------------------------------- */

                        $content  .= "<form action='index.php' method='post'>
                                        <input type='hidden' name='do' value='admin'>
                                        <input type='hidden' name='admin' value='Oppdatere siste'>
                                        <input type='hidden' name='show' value='" . $dugnad_id . "'>";



                        $content .= admin_show_day($dugnad_id, false);

                        if ((int) status_of_dugnad($dugnad_id) == 0) {
                            $done_caption = "Merke dagen som ferdigbehandlet";
                        } else {
                            $done_caption = "Angre ferdigbehandling";
                        }

                        $content .= "<div class='row_explained'><input type='reset' class='check_space' value='Nullstille endringer' />&nbsp;&nbsp;&nbsp;<input type='submit' value='Oppdatere dugnadsbarna'>&nbsp;&nbsp;&nbsp;<input type='submit' name='done' value='" . $done_caption . "'></div></form>";
                    } else {
                        $content = "<p class='failure'>Det oppstod en feil, viser derfor hele dugnadslisten.</p>" . output_full_list($valid_login); // true for admin
                    }
                } else {
                    $feedback .= "<p class='failure'>Du har ikke rettigheter til denne funksjonen.</p>";
                }
            } else {

                /* SHOWING THE PAPER LAYOUT - OF ALL STRAFFEDUGNADS
                -------------------------------------------------------------------------------- */

                global $paper;
                $paper = "_paper";

                $item_count = 0;

                $flyer_template = file_to_array("./layout/flyer_bot.html");

                $query = "SELECT dugnad_id AS id, dugnad_dato AS dato FROM bs_dugnad WHERE dugnad_dato >= (SELECT dugnad_dato FROM bs_dugnad WHERE dugnad_id = ${formdata['show']}) AND dugnad_slettet ='0' ORDER BY dugnad_dato LIMIT 1";

                $result    = @run_query($query);
                $denne_dugnaden    = @mysql_fetch_array($result);

                $week            = date("W", make_unixtime($denne_dugnaden["id"]));

                $query = "SELECT
                                    beboer_id,
                                    beboer_for,
                                    beboer_etter,

                                    rom_nr,
                                    rom_type,

                                    nydag.dugnad_dato AS straff_dato,

                                    bot_id

                            FROM (bs_dugnad d1, bs_beboer, bs_deltager denne)

                                LEFT JOIN bs_rom
                                    ON rom_id = beboer_rom

                                LEFT JOIN bs_deltager straff
                                    ON straff.deltager_beboer = beboer_id
                                        AND straff.deltager_notat = 'Straff fra uke " . $week . ".'

                                LEFT JOIN bs_bot
                                    ON bot_deltager = denne.deltager_id

                                LEFT JOIN bs_dugnad nydag
                                    ON nydag.dugnad_id = straff.deltager_dugnad

                            WHERE
                                    denne.deltager_dugnad = '" . $denne_dugnaden["id"] . "'
                                AND beboer_id = denne.deltager_beboer
                                AND d1.dugnad_id = denne.deltager_dugnad
                                AND denne.deltager_gjort <> '0'

                            ORDER BY beboer_for, beboer_etter";

                $result = @run_query($query);


                while ($row = @mysql_fetch_array($result)) {
                    /* NEW DUGNAD
                    ----------------------------------- */
                    $item_count++;
                    $flyer = $flyer_template;

                    $flyer["rom_info"] = get_public_lastname($row["beboer_etter"], $row["beboer_for"], false, true) . "<br />" . $row["rom_nr"] . $row["rom_type"] . $flyer["rom_info"];
                    $flyer["dugnad_dato"] = get_simple_date($denne_dugnaden["dato"], true) . $flyer["dugnad_dato"];

                    if (empty($row["bot_id"])) {
                        $flyer["dugnad_bot"] = ". " . $flyer["dugnad_bot"];
                        $add_and = "derfor ";
                    } else {
                        $flyer["dugnad_bot"] = "og har derfor bli ilagt bot p&aring; " . ONE_BOT . " kroner. Boten blir f&oslash;rt opp p&aring; husleien." . $flyer["dugnad_bot"];
                        $add_and = "ogs&aring ";
                    }

                    if (!empty($row["straff_dato"])) {
                        $flyer["dugnad_straff"] = "Du har " . $add_and . " blitt satt opp p&aring; straffedugnad " . get_simple_date($row["straff_dato"], true) . "." . $flyer["dugnad_straff"];
                    }

                    $remaining_dugnads = get_undone_dugnads($row["beboer_id"]);

                    if (!empty($remaining_dugnads)) {
                        $flyer["dugnad_dugnad"] = "<p>Minner om at du er satt p&aring; f&oslash;lgende dugnadsdager: " . $remaining_dugnads . ".</p>" . $flyer["dugnad_dugnad"];
                    } else {
                        $flyer["dugnad_dugnad"] = "<p>Du har ingen gjenst&aring;ende dugnader dette semesteret.</p>" . $flyer["dugnad_dugnad"];
                    }

                    if ($item_count % 3 == 0) {
                        $flyer["page_break"] = "_break" . $flyer["page_break"];
                    }

                    $content .= implode($flyer);
                }
            }


            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * ALIAS FOR "Dugnadsliste"
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Jumps to Dugnadsliste
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Justere status":
            /* fall through to Dugnadsliste */

        case "Se over forrige semester":
            /* fall through to Dugnadsliste */

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Dugnadsliste
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Generates the next dugnadslist.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Dugnadsliste":

            /* User want to change entries
            ------------------------------------------------------------ */

            $title = "Administrere dugnadsliste";
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Dugnadsliste";

            $valid_login = valid_admin_login();

            if ($valid_login && !empty($formdata["newn"]) && get_beboer_name($formdata["newn"]) && empty($formdata["notat"])) {
                /* Valid beboer - adding note..
                ------------------------------------------------ */
                $admin_login = file_to_array("./layout/admin_notat.html");

                $show = (!empty($formdata["show"]) ? "<input type='hidden' name='show' value='" . $formdata["show"] . "'>\n" : null);

                if (isset($formdata["next"])) {
                    $show .= "<input type='hidden' name='next' value='go'>\n";
                } elseif (isset($formdata["prev"])) {
                    $show .= "<input type='hidden' name='prev' value='go'>\n";
                }

                $sort_by = (!empty($formdata["sorts"]) ? $formdata["sorts"] : "last");

                $admin_login["hidden"] = "<input type='hidden' name='do' value='admin'>\n" .
                    "<input type='hidden' name='sorts' value='" . $sort_by . "'>\n" .
                    "<input type='hidden' name='admin' value='" . $formdata["admin"] . "'>\n" .
                    "<input type='hidden' name='beboer' value='" . $formdata["newn"] . "'>\n" . $show .
                    $admin_login["hidden"];

                $admin_login["beboer"] = get_beboer_name($formdata["newn"]) . $admin_login["beboer"];

                $content = implode($admin_login);
            } elseif ($valid_login) {
                $feedback .= update_dugnads();

                if (!empty($formdata["deln"])) {
                    delete_note($formdata["deln"]);
                } elseif (!empty($formdata["notat"]) && !empty($formdata["beboer"]) && get_beboer_name($formdata["beboer"])) {
                    if (insert_note($formdata["beboer"], $formdata["notat"], $formdata["mottaker"])) {
                        $feedback .= "<p class='success'>Nytt notat ble lagret.</p>";
                    } else {
                        $feedback .= "<p class='failure'>Det oppstod en feil, nytt notat ble ikke lagret.</p>";
                    }
                }

                if (!empty($formdata["delete_person"])) {
                    /* Some user is to be deleted .. */
                    $feedback .= delete_beboer_array($formdata["delete_person"]);
                }

                if (!empty($formdata["delete"])) {
                    foreach ($formdata["delete"] as $beboer_dugnad) {
                        $beboer_split = explode("_", $beboer_dugnad);

                        if (!delete_beboer($beboer_id) && $success) {
                            $success = false;
                            $failed++;
                        } else {
                            $deleted++;
                        }
                    }

                    if ($success) {
                        $feedback .= "<p class='success'>Slettet " . $deleted . " beboere fra dugnadsordningen.</p>";
                    } else {
                        $feedback .= "<p class='failure'>Av totalt " . $deleted + $failed . " var det " . $failed . " som ikke ble slettet.</p>";
                    }
                }

                global $dugnad_is_empty, $dugnad_is_full;
                list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

                $content = $feedback . output_full_list($valid_login); // true for admin
            } else {
                $feedback .= "<p class='failure'>Du har ikke rettigheter til denne funksjonen.</p>";
            }

            break;


            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Dagdugnad
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Lets vedlikeholdssjefen himself arrange who and when beboere has a dagdugnad (a dugnad with him).
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Dagdugnad":

            /* User want to change entries
            ------------------------------------------------------------ */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; " . $formdata["admin"];

            $valid_login = valid_admin_login();

            if (isset($formdata["act"]) && !strcmp($formdata["act"], "Vis dugnadskalenderen")) {
                /* user wants to see the dugnadskalender. So be it: forwarding. */
                redirect(DUGNADURL . "?do=admin&admin=Dugnadskalender");
                exit();
            } elseif (($valid_login == 1 || $valid_login == 2) && !empty($formdata["newn"]) && get_beboer_name($formdata["newn"]) && empty($formdata["notat"])) {
                /* Valid beboer - adding note..
                ------------------------------------------------ */
                $admin_login = file_to_array("./layout/admin_notat.html");

                $show = (!empty($formdata["show"]) ? "<input type='hidden' name='show' value='" . $formdata["show"] . "'>\n" : null);

                if (isset($formdata["next"])) {
                    $show .= "<input type='hidden' name='next' value='go'>\n";
                } elseif (isset($formdata["prev"])) {
                    $show .= "<input type='hidden' name='prev' value='go'>\n";
                }

                $sort_by = (!empty($formdata["sorts"]) ? $formdata["sorts"] : "last");

                $admin_login["hidden"] = "<input type='hidden' name='do' value='admin'>\n" .
                    "<input type='hidden' name='sorts' value='" . $sort_by . "'>\n" .
                    "<input type='hidden' name='admin' value='" . $formdata["admin"] . "'>\n" .
                    "<input type='hidden' name='beboer' value='" . $formdata["newn"] . "'>\n" . $show .
                    $admin_login["hidden"];

                $admin_login["beboer"] = get_beboer_name($formdata["newn"]) . $admin_login["beboer"];

                $content = implode($admin_login);
            } elseif ($valid_login == 1 || $valid_login == 2) {

                /* VALID LOGIN  - SHOWING NORMAL DAGDUGNAD PAGE
                ------------------------------------------------------------- */


                $feedback .= update_dugnads();

                if (!empty($formdata["deln"])) {
                    delete_note($formdata["deln"]);
                } elseif (!empty($formdata["notat"]) && !empty($formdata["beboer"]) && get_beboer_name($formdata["beboer"])) {
                    if (insert_note($formdata["beboer"], $formdata["notat"], $formdata["mottaker"])) {
                        $feedback .= "<p class='success'>Nytt notat ble lagret.</p>";
                    } else {
                        $feedback .= "<p class='failure'>Det oppstod en feil, nytt notat ble ikke lagret.</p>";
                    }
                } elseif (!empty($formdata["beboer"]) && get_beboer_name($formdata["beboer"])) {
                    /* valid beboer; adding a Vedlikeholdsdugnad */

                    $query = "INSERT INTO bs_deltager (deltager_beboer, deltager_dugnad, deltager_gjort, deltager_type, deltager_notat)
                                VALUES ('" . $formdata["beboer"] . "', '-2', '0', '0', 'Opprettet av Vedlikehold')";
                    @run_query($query);
                }

                global $dugnad_is_empty, $dugnad_is_full;
                list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

                /*
                $box = file_to_array("./layout/box_green.html");
                $box["content"] = "<h2>37 beboere har dugnad 28. januar.</h2>". $box["content"];
                */

                $content = $feedback . output_vedlikehold_list(); // true for admin
            } else {
                $feedback .= "<p class='failure'>Du har ikke rettigheter til denne funksjonen.</p>";
            }

            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Dugnadskalender
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Used to delete the days a dugnad will not be arranged.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Dugnadskalender":

            /* User want to configure the semester
            ------------------------------------------------------------ */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; <a href='index.php?do=admin&admin=Semesterstart'>Semesterstart</a> &gt; $title";

            $valid_login = valid_admin_login();

            switch ($formdata["saturdays"]) {
                case "add":

                    if ($valid_login == 1) {
                        if (truncateAllowed()) {
                            $query = "TRUNCATE TABLE bs_dugnad";
                            @run_query($query);

                            $content .= "<p class='success'>Tilf&oslash;yde " . get_saturdays() . " l&oslash;rdager for dette semesteret.</p>";

                            // ENABLING LOGIN FOR VAKTGRUPPESJEFEN, FESTFORENINGEN AND RYDDEVAKTSJEFEN
                            // THIS WILL AUTOMATICALLY BE DISABLED WHEN INFOLISTE IS PRINTED!

                            set_innstilling("open_season", "1");
                            $msg = "<p class='txt_bottom'><span class='failure'>MERK:</span> Passordet (<b>" . get_innstilling("pw") . "</b>) som du gir til Ryddevaktsjefen, Vaktgruppesjefen og Festforeningen er n&aring; operativt helt til <a href='index.php?do=admin&admin=Infoliste'>Infolisten</a> blir skrevet ut. Du kan <a href='index.php?do=admin&admin=Skifte%20passord'>skifte dette passordet</a> fra <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a>.</p>";
                        } else {
                            $content .= "<p class='failure'>Denne operasjonenen er ikke tillatt etter at dugnadsperioden har startet.</p>";
                        }
                    } else {
                        $content .= "<p class='failure'>Du har ikke tastet inn korrekt passord.</p>";
                    }
                    break;

                case "remove":

                    if ($valid_login == 1) {
                        $query = "TRUNCATE TABLE bs_deltager";
                        @run_query($query);

                        if (@mysql_errno() == 0) {
                            $query = "TRUNCATE TABLE bs_dugnad";
                            @run_query($query);

                            $content .= "<p class='success'>Alle l&oslash;rdager er slettet.</p>";


                            // DISABLING LOGIN FOR VAKTGRUPPESJEFEN, FESTFORENINGEN AND RYDDEVAKTSJEFEN
                            // THIS WILL AUTOMATICALLY BE ENABLED WHEN ALL SATURDAYS AGAIN ARE ADDED!

                            set_innstilling("open_season", "0");
                            $msg = "<p><span class='failure'>MERK:</span> Ryddevaktsjefen, Vaktgruppesjefen og Festforeningen kan ikke logge inn med passordet (<b>" . get_innstilling("pw") . "</b>) f&oslash;r nye l&oslash;rdager er tilf&oslash;yd kalenderen igjen.</p>";
                        } else {
                            $content .= "<p class='failure'>Beklager, det oppstod en feil under sletting av l&oslash;rdagene.</p>";
                        }
                    } else {
                        $content .= "<p class='failure'>Du har ikke tastet inn korrekt passord.</p>";
                    }

                    break;

                case "idle":

                    if ($valid_login == 1) {

                        /* Updating
                            --------------------------------------------------------------- */
                        $content .= update_saturdays_status();
                        break;
                    } else {
                        $content .= "<p class='failure'>Du har ikke tastet inn korrekt passord.</p>";
                    }
                default:
                    /* Not deleting, adding og updating...
                        --------------------------------------------------------------- */

                    break;
            }

            $content .= "<form action='index.php' method='post'>" . show_all_saturdays();
            $content .= get_file_content("./layout/form_update.html") . "</form>" . $msg;

            if (truncateAllowed($future_check) == false) {

                $warning = "<p>&nbsp;</p>\n\t\t\t\t\t\t\t<div class='bl_red'>
                                <div class='br_red'>
                                    <div class='tl_red'>
                                        <div class='tr_red'>
                                            <b>MERK</b>: Sletter du alle l&oslash;rdager, slettes ogs&aring; alle tilknyttede dugnader. Nye dugnader m&aring; derfor alltid tildeles etter sletting.
                                        </div>
                                    </div>
                                </div>
                            </div>\n";

                $content .= $warning;
            }

            break;


            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Innkalling av nye - dugnadsinnkalling til importerete beboere - importering - dugnadslapper
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Based on a text list exported from word, new beboere is imported into the database.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Innkalling av nye":

            $title = "Innkallingslapper";
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt;" . $title;

            $valid_login = valid_admin_login();

            if ($valid_login == 1) {
                $query = "SELECT DISTINCT deltager_beboer
                            FROM bs_deltager
                            WHERE deltager_notat = '" . $formdata["nyinnkalling"] . "'";

                $result_deltager = run_query($query);

                if (mysql_num_rows($result_deltager)) {
                    global $paper;
                    $paper = "_paper";

                    $formdata["view"] = "Infoliste";

                    $element_count = 0;

                    $flyer = file_to_array("./layout/flyer_passord.html");

                    while (list($beboer_id) = mysql_fetch_row($result_deltager)) {
                        $query = "SELECT

                                        beboer_id,
                                        beboer_passord,
                                        beboer_for,
                                        beboer_etter,
                                        (rom_nr + 0) AS rom_int,
                                        rom_nr AS rom_alpha,
                                        rom_type

                                    FROM bs_beboer

                                        LEFT JOIN bs_rom
                                            ON rom_id = beboer_rom

                                    WHERE beboer_id = '" . $beboer_id . "'

                                    ORDER BY rom_int, rom_alpha, beboer_etter, beboer_for";

                        $result = @run_query($query);

                        $dugnadsledere = get_dugnadsledere();
                        while ($row = @mysql_fetch_array($result)) {
                            $undone_dugnads = get_undone_dugnads($row["beboer_id"]);

                            if (!empty($undone_dugnads)) {
                                $new_flyer = $flyer;

                                $new_flyer["rom_info"] = get_public_lastname($row["beboer_etter"], $row["beboer_for"], false, true) . "<br />" .
                                    (!strcmp($row["rom_int"], $row["rom_alpha"]) ? $row["rom_int"] : $row["rom_alpha"]) . $row["rom_type"] . $new_flyer["rom_info"];

                                if ($element_count++ % 2) {
                                    $new_flyer["format_print"] = "_break" . $new_flyer["format_print"];
                                }

                                $new_flyer["gutta"] = $dugnadsledere . $new_flyer["gutta"];
                                $new_flyer["dugnad_url"] = DUGNADURL . $new_flyer["dugnad_url"];
                                $new_flyer["dugnad_dugnad"] = $undone_dugnads . $new_flyer["dugnad_dugnad"];
                                $new_flyer["passord"] = $row["beboer_passord"] . $new_flyer["passord"];

                                $content .= implode($new_flyer);
                            }
                        }
                    }
                } else {
                    $content .= "<p class='success'>Beklager, men den valgte tilf&oslash;yningen inneholder ingen beboere..</p>";
                    $ops = true;
                }
            }


            if ($valid_login != 1 || $ops) {
                $page = file_to_array("./layout/form_innkallingnyeste.html");

                $page["importeringsliste"] = make_last_beboere_select() . $page["importeringsliste"];

                $content .= implode($page);
            }


            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * ALIAS FOR "Importer beboere"
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Jumps to Importer beboere
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Nye beboere":
            /* fall through to Dugnadsliste */

            $formdata["admin"] = "Importer beboere";


            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * Importer beboere / Importere beboere
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Part 1 / 2: Import of beboere, see "upload" for final part.
         *
         * This screen only displays the import window, the "upload" below is the section actually importing the data.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "Importer beboere": // done

            /* User want to cut & paste the beboere for the semester...
            ----------------------------------------------------------------------- */

            $title = $formdata["admin"];
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; <a href='index.php?do=admin&admin=Semesterstart'>Semesterstart</a> &gt; $title";

            $query = "SELECT dugnad_id
                        FROM bs_dugnad
                        LIMIT 1";

            $result = @run_query($query);

            if (@mysql_num_rows($result) == 0) {
                $feedback .= "<div class='failure'>Det er ikke generert noen dugnadsdager, dette gj&oslash;res fra <a href='?do=admin&admin=Dugnadskalender'>Dugnadskalenderen</a>.</div>";
            }

            $content = $feedback;

            $page = file_to_array("./layout/form_import.html");

            if (truncateAllowed() == false) {
                $page["disable_slett"] = "disabled='disabled'" . $page["disable_slett"];
                $page["disable_slett_start"] = "<span class='disabled'>" . $page["disable_slett_start"];
                $page["disable_slett_end"] = "</span>" . $page["disable_slett_end"];
            }

            $content = $content . implode($page);

            break;

            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * upload
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Part 2 / 2: See Importer beboere for part 1.
         *
         * Based on a text list exported from word, new beboere is imported into the database.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        case "upload": // done

            /* User want to import data
            ------------------------------------------------------------ */

            $title = "Lagring av importerte beboere";
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Lagring";

            $valid_login = valid_admin_login();

            if ($valid_login == 1) {

                if (!empty($formdata["list"])) {

                    // DELETING ALL FROM DATABASE - IF USER HAS CHECKED THE BOX

                    // Setting it to true after a dugnad has been
                    // arranged. It will only be false if the user
                    // tries to delete beboere after a dugnadsperiode has started.

                    $do_it = true;


                    if (!strcmp($formdata["delappend"], "del")) {

                        if (truncateAllowed()) {
                            // Fetching all elephants and other special people:
                            $special_query = "SELECT beboer_for, beboer_etter, beboer_spesial FROM bs_beboer WHERE beboer_spesial = '2' OR beboer_spesial = '6'";
                            $special_result = @run_query($special_query);

                            // Fetching all elephants and other special people:
                            $dugnad_query = "SELECT beboer_id FROM bs_beboer, bs_innstillinger WHERE innstillinger_felt = 'dugnadsleder' AND innstillinger_verdi = beboer_id";
                            $dugnad_result = @run_query($dugnad_query);

                            $query = "TRUNCATE TABLE bs_admin_access";
                            @run_query($query);

                            $query = "TRUNCATE TABLE bs_notat";
                            @run_query($query);

                            $query = "TRUNCATE TABLE bs_bot";
                            @run_query($query);

                            $query = "TRUNCATE TABLE bs_deltager";
                            @run_query($query);

                            $query = "DELETE FROM bs_innstillinger WHERE innstillinger_felt = 'dugnadsleder'";
                            @run_query($query);

                            $query = "TRUNCATE TABLE bs_beboer";
                            @run_query($query);
                        } else {
                            $content .= "<p class='failure'>Denne operasjonenen er ikke tillatt etter at dugnadsperioden har startet.</p>";
                            $do_it = false;
                        }
                    }

                    // --------------------------------------------------------- ADDING NEW DATA!

                    if ($do_it && store_data($formdata["list"], "/")) {
                        $content .= "<p class='success'>Oppdatering av databasen var vellykket.</p>";

                        // ADDING SPECIAL STATUS TO ALL THAT HAD IT BEFORE DELETING ALL!
                        if (isset($special_result) && @mysql_num_rows($special_result)) {
                            $first_miss = true;

                            while (list($fornavn, $etternavn, $status) = @mysql_fetch_row($special_result)) {
                                $update_special = "UPDATE bs_beboer SET beboer_spesial = '" . $status . "' WHERE beboer_for = '" . $fornavn . "' AND beboer_etter = '" . $etternavn . "'";
                                @run_query($update_special);

                                if (@mysql_affected_rows() == 0) {
                                    if ($first_miss) {
                                        $content .= "\n<p>Beboere med dugnadsfri:\n";
                                        $first_miss = false;
                                    }

                                    $content .= "<li>" . $fornavn . " " . $etternavn . " " . ($status == 2 ? "(Elefant)" : "(Dugnadsfri)") . "</li>\n";
                                }
                            }
                            $content .= "</p>\n\n";

                            // ADDING DUGNADSLEDERS!!
                            $first_miss = true;
                            while (list($id) = @mysql_fetch_row($dugnad_result)) {
                                $update_special = "INSERT INTO bs_innstillinger (innstillinger_felt, innstillinger_verdi) VALUES ('dugnadsleder', '" . $id . "')";
                                @run_query($update_special);

                                if (@mysql_affected_rows() == 0) {
                                    if ($first_miss) {
                                        $content .= "\n<p>Dugnadsledere som ikke ble gjenkjent:\n";
                                        $first_miss = false;
                                    }

                                    $content .= "<li>" . $fornavn . " " . $etternavn . " " . ($status == 2 ? "(Elefant)" : "(m&aring; eventuelt legges til manuelt)") . "</li>\n";
                                }
                            }
                            $content .= "</p>\n\n";
                        }

                        if (!strcmp($formdata["delappend"], "append")) {
                            /* Adding dugnads to newly added beboere:
                            ------------------------------------------------- */

                            $txt_lines = split("\*\*", clean_txt($formdata["list"]));
                            $c = 0;

                            $dugnadGiven = 0;
                            $beboerGiven = 0;

                            foreach ($txt_lines as $line) {
                                $c++;
                                $splits = split("/", $line);

                                /* First name and last name is divided with a character different from each column: */
                                list($last, $first) = split(",", $splits[0]);

                                $first = trim($first);
                                $last  = trim($last);

                                $person_id = get_person_id($last, $first);

                                $dugnadGiven += forceNewDugnads($person_id, 2, 25, "IMP" . get_usage_count(false));
                                $beboerGiven += 1;
                            }

                            $content .= "<div class='success'>Totalt ble " . $beboerGiven . " <b>ny" . ($beboerGiven > 1 ? "e" : null) . "</b> dugnadspliktig" . ($beboerGiven > 1 ? "e" : null) . " beboer" . ($beboerGiven > 1 ? "e" : null) . " tildelt " . sprintf("%.5f", ($dugnadGiven / $beboerGiven)) . " dugnad" . ($dugnadGiven / $beboerGiven == 1 ? null : "er") . " i snitt.<br /></div>";

                            // Letting the user easily printout the latest import of beboere

                            $query = "SELECT
                                            DISTINCT deltager_notat AS notat

                                        FROM bs_deltager
                                        WHERE
                                            deltager_notat LIKE 'IMP%'

                                        ORDER BY deltager_notat DESC";

                            $result = @run_query($query);
                            list($impValue) = @mysql_fetch_row($result);

                            $content .= "<form method='post' action='index.php'>
                                            <input type='hidden' name='do' value='admin'>
                                            <input type='hidden' name='print' value='lastimport'>
                                            <input type='hidden' name='nyinnkalling' value='" . $impValue . "'>
                                            <h1>Innkalling til disse nye beboerne</h1>
                                            <p class='txt'>
                                                Klikk p&aring; knappen for &aring; skrive ut dugnadsinnkalling til
                                                disse beboerne. Har du ikke har tilgang til en skriver n&aring;    ,
                                                s&aring; kan lappene skrives ut p&aring; et senere tidspunkt
                                                fra <i>Admin</i> &gt; <i>Innstillinger</i>.
                                            </p>
                                            <input type='submit' name='admin' value='Innkalling av nye'>
                                        </form><p>&nbsp;</p>";



                            $content .= get_file_content("./layout/admin_mainmenu.html");

                            /* -------------------------------------------------
                                Done adding dugnads                           */
                        } else {
                            $content .= get_file_content("./layout/menu_semesterstart.html");
                        }
                    } else {
                        if ($do_it) {
                            $content .= "Det skjedde en feil under lagring av dugnadsliste, vennligst pr&oslash;v igjen...";
                            $content .= get_file_content("./layout/form_import.html");
                        } else {
                            $content .= get_file_content("./layout/menu_semesterstart.html");
                        }
                    }
                } else {
                    $content = "Det oppstod en feil under oppdatering av databasen, vennligst <a href='index.php?do=admin&admin=Importere nye dugnadsbarn'>pr&oslash;v igjen</a>...";
                    $content = get_file_content("./layout/form_import.html");
                }
            } else {
                $content = "Passordet er ikke riktig, vennligst <a href='index.php?do=admin&admin=Importer%20beboere'>pr&oslash;v igjen</a>...";
            }

            break;


            /* -------------------------------------------------------------------------------------------------------------------------------------------- *
         * default
         * -------------------------------------------------------------------------------------------------------------------------------------------- *
         *
         * Displaying the main menu of admin.
         *
         * -------------------------------------------------------------------------------------------------------------------------------------------- */

        default:

            /* User has not chosen a valid action
            ------------------------------------------------------------ */

            $title = "Admin";
            $navigation = "<a href='index.php'>Hovedmeny</a> &gt; Admin";

            $content = file_to_array("./layout/menu_admin.html");

            $content["db_error"] = database_health() . $content["db_error"];

            $content = implode($content);
            break;
    }

    return array($title, $navigation, $content);
}


/* ******************************************************************************************** *
 *  get_usage_count()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_usage_count($full_message = true)
{
    global $formdata;

    if (!strcmp($formdata["do"], "admin")) {
        $query = "SELECT COUNT(admin_access_id) AS counted FROM bs_admin_access WHERE admin_access_success > '0'";
    } else {
        $query = "SELECT COUNT(admin_access_id) AS counted FROM bs_admin_access WHERE admin_access_success = '0'";
    }

    $result = @run_query($query);

    if (@mysql_num_rows($result)) {
        $row = @mysql_fetch_array($result);

        if ($full_message) {
            return "brukt " . (int) $row["counted"] . " ganger";
        } else {
            return $row["counted"];
        }
    } else {
        return "<span class='failure'>databasen er ikke tilgjengelig!</span>";
    }
}


/* ******************************************************************************************** *
 *  delete_beboer_array($beboer_array)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function delete_beboer_array($beboer_array)
{
    $success = true;

    $failed = 0;
    $deleted = 0;

    foreach ($beboer_array as $beboer_id) {
        if (!delete_beboer($beboer_id) && $success) {
            $success = false;
            $failed++;
        } else {
            $deleted++;
        }
    }

    if ($success) {
        $feedback .= "<p class='success'>Slettet " . $deleted . " beboere fra dugnadsordningen.</p>";
    } else {
        $feedback .= "<p class=\"failure\">Av totalt " . ($deleted + $failed) . " beboer" . ($deleted + $failed == 1 ? "" : "e") . " var det " . $failed . " som ikke ble slettet.</p>";
    }

    return $feedback;
}


/* ******************************************************************************************** *
 *  delete_beboer($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function delete_beboer($beboer_id)
{
    if (verify_person_id($beboer_id)) {
        /* DELETE ALL FROM THIS PERSON
        ------------------------------------------------------------ */

        /* NOTAT, BOT, DELTAGER & BEBOER */

        $query = "SELECT deltager_id AS id
                    FROM bs_deltager
                    WHERE deltager_beboer = '" . $beboer_id . "'";

        $result = @run_query($query);

        if (@mysql_num_rows($result)) {
            $row = @mysql_fetch_array($result);

            $deltager_id = $row["id"];

            $query = "DELETE FROM bs_bot WHERE bot_deltager = '" . $deltager_id . "'";
            @run_query($query);

            $query = "DELETE FROM bs_deltager WHERE deltager_id = '" . $deltager_id . "'";
            @run_query($query);
        }

        $query = "DELETE FROM bs_notat WHERE notat_beboer = '" . $beboer_id . "'";
        @run_query($query);

        $query = "DELETE FROM bs_beboer WHERE beboer_id = '" . $beboer_id . "'";
        @run_query($query);

        return true;
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  make_unixtime($dugnad_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function make_unixtime($dugnad_id)
{
    $query = "SELECT dugnad_dato FROM bs_dugnad WHERE dugnad_id = '" . $dugnad_id . "' LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        $date_frags = explode("-", substr($row["dugnad_dato"], 0, 10));

        return mktime(0, 0, 0, $date_frags[1], $date_frags[2], $date_frags[0]);
    } else {
        print "Hva har du gjort? ;-)<br />Ta kontakt med en dugnadsleder og oppgi feilkode 42.";
        exit();
    }
}


/* ******************************************************************************************** *
 *  clean_txt($str)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function clean_txt($str)
{
    $str = preg_replace('/ +/', ' ', trim($str));
    //$str = ereg_replace (', +', ',', $str);
    $str = preg_replace("/[\r\n]+/", "\r\n", $str);
    $str = preg_replace("/\r\n/", "**", $str);
    return $str;
}


/* ******************************************************************************************** *
 *  store_data($txt_data, $split_char = "/", $split_name = ",")
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function store_data($txt_data, $split_char = "/", $split_name = ",")
{
    global $formdata;

    $c = 0;
    $success = true;
    $txt_lines = split("\*\*", clean_txt($txt_data));

    foreach ($txt_lines as $line) {
        $c++;
        $splits = split("/", $line);

        if (strcmp($split_char, $split_name)) {

            /* First name and last name is divided with a character different from each column: */

            list($last, $first) = split($split_name, $splits[0]);

            /* Removed rooms, as Lene has formatted wrong.
            // $room        = $splits[1];
            // $type        = $splits[2];
            */
        }

        $first = trim($first);
        $last  = trim($last);

        if (!find_person($last, $first)) {
            /* Person does not exist, inserting it into the db:
            ---------------------------------------------------------------------- */

            /* Generate random password: */
            $jumble = md5(((time() / 10000) + ($c * 45))  . getmypid());
            $password = substr($jumble, 0, 5);

            $phone_query = "SELECT rom_id FROM bs_rom WHERE CONCAT(rom_nr, rom_type) = '" . trim($splits[1]) . "' LIMIT 1";

            $phone_result = @run_query($phone_query);
            if (@mysql_num_rows($phone_result)) {
                list($rom_id) = @mysql_fetch_array($phone_result);
            } else {
                $rom_id = 0;
            }

            /* Inserting person: */
            $query = "INSERT INTO bs_beboer (beboer_for, beboer_etter, beboer_rom, beboer_passord)
                        VALUES ('" . $first . "', '" . $last . "', '" . $rom_id . "', '" . $password . "')";

            @run_query($query);

            if (@mysql_errno() > 0) {
                $success = false;
            }

            $id = @mysql_insert_id();
        } else {
            $id = get_person_id($last, $first);
        }
    }

    return $success;
}


/* ******************************************************************************************** *
 *  delete_note($note_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function delete_note($note_id)
{
    if (confirm_note_id($note_id)) {
        $query = "DELETE FROM bs_notat WHERE notat_id = '" . $note_id . "'";
        @run_query($query);
    }
}


/* ******************************************************************************************** *
 *  insert_note($id, $note, $mottaker = 0)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function insert_note($id, $note, $mottaker = 0)
{
    if (!empty($note)) {
        $noted = get_note_id($id, $note);

        if ($noted == -1) {
            $query = "INSERT INTO bs_notat (notat_beboer, notat_txt, notat_mottaker)
                            VALUES('" . $id . "', '" . $note . "', '" . $mottaker . "')";

            @run_query($query);
            return true;
        }
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_note_id($id, $note)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_note_id($id, $note)
{
    $query = "SELECT notat_id AS id
                FROM bs_notat
                WHERE notat_beboer = '" . $id . "' AND
                    notat_txt = '" . $note . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        return $row["id"];
    } else {
        return -1;
    }
}


/* ******************************************************************************************** *
 *  confirm_note_id($id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function confirm_note_id($id)
{
    $query = "SELECT notat_id AS id
                FROM bs_notat
                WHERE notat_id = '" . $id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        return true;
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  valid_dugnad_id($id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function valid_dugnad_id($id)
{
    global $dugnad_is_empty, $dugnad_is_full;

    $query = "SELECT dugnad_id AS id
                FROM bs_dugnad
                WHERE dugnad_id = '" . $id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        if (!empty($dugnad_is_full[$id])) {
            return false;
        } else {
            return true;
        }
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  insert_dugnad($id, $date, $type = 1, $notat = null)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function insert_dugnad($id, $date, $type = 1, $notat = null)
{
    if (!empty($date)) {
        $date_split = split("\.", $date);
        $year = date("Y", time());

        $date = get_valid_date_id($date_split[1], $date_split[0], $year);

        if ($date > -1 && !allready_added_dugnad($id, $date)) {
            $query = "INSERT INTO bs_deltager (deltager_beboer, deltager_dugnad, deltager_type, deltager_notat)
                            VALUES(" . intval($id) . ", '" . $date . "', " . intval($type) . ", '" . $notat . "')";

            @run_query($query);

            return true;
        }
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  insert_dugnad_using_id($beboer_id, $dugnad_id, $type = 1, $notat = null)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function insert_dugnad_using_id($beboer_id, $dugnad_id, $type = 1, $notat = null)
{
    if (!allready_added_dugnad($beboer_id, $dugnad_id)) {
        $query = "INSERT INTO bs_deltager (deltager_beboer, deltager_dugnad, deltager_type, deltager_notat)
                        VALUES(" . intval($beboer_id) . ", " . intval($dugnad_id) . ", " . intval($type) . ", '" . $notat . "')";

        @run_query($query);

        return (@mysql_affected_rows() == 1 ? true : false);
    }

    // Ending here is wrong
    return false;
}


/* ******************************************************************************************** *
 *  get_saturdays()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_saturdays()
{
    $count = 0;

    $year    = date("Y", time());
    $month    = date("m", time());

    if ($month > 7) {
        /* We now know we are in the autumn semester
        -------------------------------------------------------- */
        $months = array("8", "9", "10", "11", "12");
    } else {
        /* .. Spring
        -------------------------------------------------------- */
        $months = array("1", "2", "3", "4", "5", "6");
    }

    foreach ($months as $month) {
        for ($i = 1; $i <= 31; $i++) {
            if (checkdate($month, $i, $year)) {
                if (date("w", mktime(0, 0, 0, $month, $i, $year)) == '6') {
                    if (!date_exist($month, $i, $year)) {
                        $query = "INSERT INTO bs_dugnad (dugnad_dato)
                                    VALUES ('" . date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $i, $year)) . "')";

                        @run_query($query);

                        if (@mysql_affected_rows() == 0) {
                            $count++;
                        } else {
                            print @mysql_error();
                        }
                    }
                }
            }
        }
    }

    return $count;
}


/* ******************************************************************************************** *
 *  allready_added_dugnad($id, $date)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function allready_added_dugnad($id, $date)
{
    $query = "SELECT deltager_id
                FROM bs_deltager
                WHERE deltager_dugnad = '" . $date . "' AND
                    deltager_beboer = '" . $id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        return true;
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  date_exist($month, $day, $year)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function date_exist($month, $day, $year)
{
    $the_day = date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $day, $year));

    if (checkdate($month, $day, $year)) {
        if (date("w", mktime(0, 0, 0, $month, $day, $year)) == '6') {
            $query = "SELECT dugnad_id
                        FROM bs_dugnad
                        WHERE dugnad_dato = '" . $the_day . "'
                        LIMIT 1";

            $result = @run_query($query);

            if (@mysql_num_rows($result) == 1) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  date_is_disabled($month, $day, $year)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function date_is_disabled($month, $day, $year)
{
    $the_day = date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $day, $year));

    if (checkdate($month, $day, $year)) {
        if (date("w", mktime(0, 0, 0, $month, $day, $year)) == '6') {
            $query = "SELECT dugnad_id
                        FROM bs_dugnad
                        WHERE dugnad_dato = '" . $the_day . "'
                            AND dugnad_slettet = '1'
                        LIMIT 1";

            $result = @run_query($query);

            if (@mysql_num_rows($result) == 1) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_valid_date_id($month, $day, $year)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_valid_date_id($month, $day, $year)
{
    $the_day = date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $day, $year));

    if (checkdate((int) $month, (int) $day, (int) $year)) {
        if (date("w", mktime(0, 0, 0, $month, $day, $year)) == '6') {
            $query = "SELECT dugnad_id
                        FROM bs_dugnad
                        WHERE dugnad_dato = '" . $the_day . "' AND
                            dugnad_slettet = '0'
                        LIMIT 1";

            $result = @run_query($query);

            if (@mysql_num_rows($result) == 1) {
                $row = @mysql_fetch_array($result);
                return $row["dugnad_id"];
            } else {
                return -1;
            }
        } else {
            return -1;
        }
    } else {
        return -1;
    }
}


/* ******************************************************************************************** *
 *  get_date_id($month, $day, $year)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_date_id($month, $day, $year)
{
    $the_day = date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $day, $year));

    if (checkdate($month, $day, $year)) {
        if (date("w", mktime(0, 0, 0, $month, $day, $year)) == '6') {
            $query = "SELECT dugnad_id
                        FROM bs_dugnad
                        WHERE dugnad_dato = '" . $the_day . "'
                        LIMIT 1";

            $result = @run_query($query);

            if (@mysql_num_rows($result) == 1) {
                $row = @mysql_fetch_array($result);
                return $row["dugnad_id"];
            } else {
                return -1;
            }
        } else {
            return -1;
        }
    } else {
        return -1;
    }
}


/* ******************************************************************************************** *
 *  get_person_id($last, $first)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_person_id($last, $first)
{
    $query = "SELECT beboer_id AS id
                FROM bs_beboer
                WHERE beboer_for = '" . $first . "' AND beboer_etter = '" . $last . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        return $row["id"];
    } else {
        return -1;
    }
}


/* ******************************************************************************************** *
 *  find_person($last, $first)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function find_person($last, $first)
{
    $query = "SELECT beboer_id
                FROM bs_beboer
                WHERE beboer_for = '" . $first . "' AND beboer_etter = '" . $last . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        return true;
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_day_header($day)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_day_header($day)
{
    $query = "SELECT dugnad_dato, dugnad_type
                FROM bs_dugnad
                WHERE dugnad_id = '" . $day . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);

        $complex = explode("-", substr($row["dugnad_dato"], 0, 10));

        $day_string = strtolower(date("j. F", mktime(0, 0, 0, $complex[1], $complex[2], $complex[0])));
        if ($row['dugnad_type'] == 'lordag') {
            $day_string .= " &nbsp;&nbsp; 10:00-14:00";
        } elseif ($row['dugnad_type'] == 'anretning') {
            $day_string .= ' (anretningsdugnad)';
        }

        return $day_string;
    } else {
        return "Dette er ingen dugnadsdato";
    }
}


/* ******************************************************************************************** *
 *  count_dugnad_barn()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function count_dugnad_barn()
{
    global $formdata;

    $query = "SELECT
                        DISTINCT beboer_id AS id,
                        dugnad_id AS done_when

                FROM bs_deltager, bs_beboer, bs_dugnad

                WHERE dugnad_dato > CURDATE() AND (TO_DAYS(dugnad_dato) - TO_DAYS(NOW()) <= 7)
                    AND deltager_beboer = beboer_id
                    AND deltager_dugnad = dugnad_id
                    AND dugnad_slettet = '0'

                ORDER BY dugnad_dato";

    $result = @run_query($query);
    if (@mysql_num_rows($result)) {
        $row = @mysql_fetch_array($result);
        return array(@mysql_num_rows($result), get_day_header($row["done_when"]));
    } else {
        return array("ingen", "f&oslash;rstkommende helg");
    }
}


/* ******************************************************************************************** *
 * show_day ( int, boolean, boolean, boolean, boolean )
 * --------------------------------------------------------------------------------------------
 *
 * Returns: A html-formattes list of the dugnad with id $day
 *
 * Params : $day is dugnads_id
 *            ...
 *
 * Used by: output_full_list(), "Neste dugnadsliste" (case)
 *
 * ============================================================================================ */

function show_day($day, $show_expired_days = false, $editable = false, $dugnadsliste_full_name = false, $supress_header = false)
{
    global $formdata;

    if (!$show_expired_days) {
        /* To limit days shown - as regular users have no need to see old days...
        -------------------------------------------------------------------------------- */
        $show_expired_days_limit = "AND dugnad_dato >= NOW() ";
    }

    $query = "SELECT
                        beboer_id        AS id,
                        beboer_for        AS first,
                        beboer_etter    AS last,

                        rom_nr            AS rom,
                        rom_type        AS rtype,
                        rom_tlf            AS tlf,

                        dugnad_dato        AS done_when,

                        deltager_gjort    AS completed,
                        deltager_type    AS kind,
                        deltager_notat    AS note


                FROM (bs_deltager, bs_dugnad, bs_beboer)

                        LEFT JOIN bs_rom
                            ON rom_id = beboer_rom

                WHERE dugnad_id = '" . $day . "'
                    " . $show_expired_days_limit . "AND deltager_beboer = beboer_id

                    AND deltager_dugnad = dugnad_id
                    AND dugnad_slettet = '0'
                ORDER BY beboer_for, beboer_etter";

    $result = @run_query($query);

    if (@mysql_num_rows($result) >= 1) {
        $line_count = 0;

        if (!$supress_header) {
            $entries .= "<div class='row_header'><h1>" . get_day_header($day) . "</h1></div>\n\n";
            $entries .= "<div class='row_explained_day'><div class='name_narrow'>Beboer (" . @mysql_num_rows($result) . " deltagere)</div><div class='note'>Notater</div><div class='spacer'>&nbsp;</div></div>";
        }

        while (list($id, $first, $last, $rom, $type, $tlf, $when, $done, $kind, $note) = @mysql_fetch_row($result)) {
            if ($show_expired_days) {
                $check_box = get_beboer_selectbox($id, $day);
            }

            $full_name = get_public_lastname($last, $first, !strcmp($formdata["sorts"], "last"), $dugnadsliste_full_name);

            $entries .= "<div class='row" . ($line_count++ % 2 ? "_odd" : null) . "'>" . $check_box . "<div class='name_narrow'>" . $full_name . "</div>\n<div class='note'>" . get_notes($id) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";
        }

        $entries .= "<div class='day_spacer'>&nbsp;</div>";

        return $entries;
    } else {
        return null;
    }
}


/* ******************************************************************************************** *
 *  admin_show_day($day, $use_dayspacer = true)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function admin_show_day($day, $use_dayspacer = true)
{
    global $formdata;

    $query = "SELECT
                        beboer_id        AS id,
                        beboer_for        AS first,
                        beboer_etter    AS last,


                        dugnad_dato        AS done_when,

                        deltager_gjort    AS completed,
                        deltager_type    AS kind,
                        deltager_notat    AS note

                FROM bs_deltager, bs_beboer, bs_dugnad

                WHERE dugnad_id = '" . $day . "'
                    AND deltager_beboer = beboer_id
                    AND deltager_dugnad = dugnad_id
                    AND dugnad_slettet = '0'
                ORDER BY beboer_for, beboer_etter";

    $result = @run_query($query);

    if (@mysql_num_rows($result) >= 1) {
        $line_count = 0;

        $entries .= "<div class='row_header'><h1>" . get_day_header($day) . "</h1></div>\n\n";
        $entries .= "<div class='row_explained_day'><div class='" . (strcmp($formdata["admin"], "Dugnadsliste") ? "select_narrow" : "checkbox_narrow") . "'>" . (strcmp($formdata["admin"], "Dugnadsliste") ? "Frav&aelig;r" : "Slett") . "</div><div class='name_narrow'>Beboer</div><div class='when_narrow'>Tildelte dugnader</div><div class='note'>Admin</div><div class='spacer'>&nbsp;</div></div>";

        while (list($id, $first, $last, $when, $done, $kind, $note) = @mysql_fetch_row($result)) {
            if (!strcmp($formdata["sorts"], "last")) {
                $full_name = $last . ", " . $first;
            } else {
                $full_name = $first . " " . $last;
            }

            if (strcmp($formdata["admin"], "Dugnadsliste")) {
                /* Showing checkbox only when this is not admin mode..
                ---------------------------------------------------------------- */
                $select_box = get_beboer_selectbox($id, $day);
            } else {
                $select_box = "<div class='checkbox_narrow'><input type='checkbox' name='delete[]' value='" . $id . "_" . $day . "' /></div>";
            }

            $dugnads = admin_get_dugnads($id, $editable) . admin_addremove_dugnad($id, $line_count);

            $entries .= "<div class='row" . ($line_count++ % 2 ? "_odd" : null) . "'>" . $select_box . "<div class='name_narrow'>" . $line_count . ". " . $full_name . "</div>\n<div class='when_narrow'>" . $dugnads . "</div><div class='note'>" . get_notes($id, true) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";
        }

        if ($use_dayspacer) {
            $entries .= "<div class='day_spacer'>&nbsp;</div>";
        }

        return $entries;
    } else {
        return null;
    }
}


/* ******************************************************************************************** *
 *  get_beboer_selectbox($beboer_id, $dugnad_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_beboer_selectbox($beboer_id, $dugnad_id)
{
    $query = "SELECT deltager_gjort AS gjort
                FROM bs_deltager
                WHERE deltager_beboer = '" . $beboer_id . "'
                    AND deltager_dugnad = '" . $dugnad_id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        $selected[$row["gjort"]] = "selected='selected' ";
    }

    return "<div class='select_narrow'><select name='" . $beboer_id . "'><option value='0' " . $selected[0] . ">Ok</option><option value='1' " . $selected[1] . ">Bot og ny dugnad</option><option value='2' " . $selected[2] . ">Kun ny dugnad</option><option value='3' " . $selected[3] . ">Kun bot</option></select></div>";
}


/* ******************************************************************************************** *
 *  get_notes($id, $admin = false)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_notes($id, $admin = false)
{
    global $formdata;

    $admin_enda = "</a>";

    // SHOWING NOTES ONLY TO ADMIN OR SUPERUSER (not undergruppe or beboer)
    if ($admin == 1 || $admin == 2) {
        $show = (!empty($formdata["show"]) ? "&show=" . $formdata["show"] : null);

        if (isset($formdata["next"])) {
            $navigate = "&next=go";
        } elseif (isset($formdata["prev"])) {
            $navigate = "&prev=go";
        }
    }

    $query = "SELECT notat_txt AS the_note, notat_id, notat_mottaker, beboer_passord, rom_tlf, rom_nr, rom_type, beboer_for
                FROM bs_beboer

                    LEFT JOIN bs_notat
                        ON notat_beboer = beboer_id

                    LEFT JOIN bs_rom
                        ON rom_id = beboer_rom

                WHERE beboer_id = '" . $id . "'";

    $result = @run_query($query);

    if (!empty($formdata["sorts"])) {
        $sort_by = $formdata["sorts"];
    } else {
        $sort_by = "last";
    }


    while ($row = @mysql_fetch_array($result)) {
        $admin_starta = "<a href='index.php?do=admin" . $show . $navigate . "&admin=" . $formdata["admin"] . "&sorts=" . $sort_by . "&deln=" . $row["notat_id"] . "'>";
        $passord =  "\nPassord: " . $row["beboer_passord"];
        $room     =  "\nRom: " . $row["rom_nr"] . $row["romtype"];

        if (!empty($row["the_note"])) {
            $content .= $admin_starta . "<img src='./images/postit" . ((int) $row["notat_mottaker"] == 1 ? "_petter" : null) . ".gif' alt='[note]' title='" . $row["the_note"] . "' class='postit_note' />" . $admin_enda;
        }
    }

    if ($admin == 1 || $admin == 2) {
        $content .= "<a href='index.php?do=admin" . $show . $navigate . "&admin=" . $formdata["admin"] . "&sorts=" . $sort_by . "&newn=" . $id . "' ><img src='./images/postitadd.gif' alt='[note]' title='Legg inn nytt notat." . $passord . $room . "' class='postit_note' /></a>";
    } else {
        $content .= $room;
    }

    return $content;
}


/* ******************************************************************************************** *
 *  get_beboer_password($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_beboer_password($beboer_id)
{
    $query = "SELECT beboer_passord
                FROM bs_beboer
                WHERE beboer_id = '" . $beboer_id . "'";

    $result = @run_query($query);

    if (@mysql_num_rows($result)) {
        $row = @mysql_fetch_array($result);
        return $row["beboer_passord"];
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_dugnad_status($max_kids = MAX_KIDS)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_dugnad_status($max_kids = MAX_KIDS)
{
    $query = "SELECT COUNT(deltager_id) AS antall, deltager_dugnad AS id,
                    dugnad_min_kids, dugnad_max_kids
                FROM bs_deltager, bs_dugnad
                WHERE dugnad_id = deltager_dugnad
                    AND dugnad_slettet = '0'
                    AND dugnad_dato > NOW() + 7
                    AND dugnad_type = 'lordag'
                GROUP BY deltager_dugnad";

    $result = @run_query($query);

    while ($row = @mysql_fetch_array($result)) {
        if ((int) $row["antall"] <= $row['dugnad_min_kids']) {
            $empty_dugnads[$row["id"]] = "1";
        }

        if ((int) $row["antall"] >= $max_kids) {
            $full_dugnads[$row["id"]] = $row['dugnad_max_kids'];
        }
    }

    return array($empty_dugnads, $full_dugnads);
}


/* ******************************************************************************************** *
 *  unchanged_dugnad($deltager_id, $new_dugnad)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function unchanged_dugnad($deltager_id, $new_dugnad)
{
    $query = "SELECT deltager_dugnad
                FROM bs_deltager
                WHERE deltager_id = '" . $deltager_id . "'
                    AND deltager_dugnad = '" . $new_dugnad . "'
                LIMIT 1";
    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        return true;
    } else {
        return false;
    }
}

/* ******************************************************************************************** *
 *  init_dugnads($beboer_id, $dugnad_array, $beboer_count_per_dugnad, $force_count = 0)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function init_dugnads($beboer_id, $dugnad_array, $beboer_count_per_dugnad, $force_count = 0)
{
    $added_to = -1;
    $c = 0;

    foreach ($dugnad_array as $dugnad_id => $count) {

        $query = "SELECT dugnad_dato FROM bs_dugnad WHERE dugnad_id = '" . $dugnad_id . "' LIMIT 1";
        $result = @run_query($query);
        $row = @mysql_fetch_array($result);

        if ($added_to == -1 && ($count < $beboer_count_per_dugnad || $force_count == 2) && valid_dugnad_id($dugnad_id) && !allready_added_dugnad($beboer_id, $dugnad_id)) {
            /* Adding the first dugnad */
            $added_to = $c;
            $query = "INSERT INTO bs_deltager (deltager_beboer, deltager_dugnad, deltager_type, deltager_notat)
                        VALUES(" . intval($beboer_id) . ", " . intval($dugnad_id) . ", '1', '')";

            @run_query($query);

            $dugnad_array[$dugnad_id] = $dugnad_array[$dugnad_id] + 1;
        } elseif (($added_to + 2) <= $c && ($count < $beboer_count_per_dugnad || $force_count == 1) && valid_dugnad_id($dugnad_id) && !allready_added_dugnad($beboer_id, $dugnad_id)) {
            /* Adding the second dugnad */
            $query = "INSERT INTO bs_deltager (deltager_beboer, deltager_dugnad, deltager_type, deltager_notat)
                        VALUES(" . intval($beboer_id) . ", " . intval($dugnad_id) . ", 1, '')";

            @run_query($query);

            $dugnad_array[$dugnad_id] = $dugnad_array[$dugnad_id] + 1;

            return $dugnad_array;
        }

        $c++;
    }

    /* as we are here; the beboer has either received 0 or 1 dugnad, this has to be fixed: */
    if ($added_to == -1) {
        /* beboer has not received ANY dugnads, force add TWO dugnads: */
        return init_dugnads($beboer_id, $dugnad_array, $beboer_count_per_dugnad, 2);
    } else {
        /* beboer has received only ONE dugnad, force add one more: */
        return init_dugnads($beboer_id, $dugnad_array, $beboer_count_per_dugnad, 1);
    }
}


/* ******************************************************************************************** *
 *  smart_create_dugnad($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function smart_create_dugnad($beboer_id)
{
    /* Forceing a new dugnad for the user,
    on a day that is valid and preferably empty.
    ------------------------------------------------------------- */

    global $empty_dugnads, $full_dugnads;

    if (!empty($empty_dugnads)) {
        $empty_dugnad = each($empty_dugnads);
    }

    if (!empty($empty_dugnad["key"]) && valid_dugnad_id($empty_dugnad["key"]) && !allready_added_dugnad($beboer_id, $empty_dugnad["key"])) {
        $query = "INSERT INTO bs_deltager (deltager_beboer, deltager_dugnad, deltager_type, deltager_notat)
                    VALUES(" . intval($beboer_id) . ", " . $empty_dugnad['key'] . ", '1', 'Opprettet dugnad.')";

        @run_query($query);
        return true;
    } else {
        $offset = 0;

        $date = get_next_dugnad_id($offset);

        if (is_numeric($date) && allready_added_dugnad($beboer_id, $date)) {
            /* Getting the next weeks dugnad, as this
            was allready added to the user
            ---------------------------------------------------- */

            $offset = $offset + 7;
            $date = get_next_dugnad_id($offset);
        }

        if ($date && !allready_added_dugnad($beboer_id, $date)) {
            /* Found valid dugnad dato
            -------------------------------------------------- */

            $query = "INSERT INTO bs_deltager (deltager_beboer, deltager_dugnad, deltager_type, deltager_notat)
                            VALUES('" . $beboer_id . "', '" . $date . "', '1', 'Opprettet dugnad.')";

            @run_query($query);
            return true;
        } else {
            /* Found no valid date for a new dugnad,
            inserting a note about this.
            ------------------------------------------------------ */

            $year    = date("Y", time());
            $month    = date("m", time());

            if ((int) $month > 7) {
                $semester = "V";
                $year = (int) $year + 1;
            } else {
                $semester = "H";
            }

            insert_note($beboer_id, "Overf&oslash;re dugnad til " . $semester . $year . ".");
            return false;
        }
    }
}


/* --------------------------------------------------------------------------------------------------------

    Function: update_dugnads();
    Users   : Only admin
    Usage   : Sets new dugnads and does an integrity check, to avoid more than one dugnads at the same day.

   -------------------------------------------------------------------------------------------------------- */


function update_dugnads()
{
    global $formdata;

    foreach ($formdata as $beboer_combo => $new_dugnad) {
        $splits = explode("_", $beboer_combo);

        /* ----------------------------------------------------------------
         * ================================================================
         *
         * ADMIN WANTS TO ADD, DELETE, CHANGE DUGNAD
         *
         * ================================================================
         * ---------------------------------------------------------------- */

        if (!strcmp("admin", $splits[0])) {
            /* Admin has selected to add one or more dugnads to the user
            --------------------------------------------------------------- */

            if (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad > 0) {
                for ($c = 0; $c < (int) $new_dugnad; $c++) {
                    smart_create_dugnad($splits[1]);
                }
            } elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -1) {
                $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $splits[1] . "'";
                @run_query($query);
            }


            /* Elefant business ...
            ------------------------------------------------------------------------------------------------------------ */ elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -2) {
                /* Beboer is an elefant and should not have any dugnads - ever again! */

                $query = "UPDATE bs_beboer SET beboer_spesial = '2'
                            WHERE beboer_id = '" . $splits[1] . "'";

                @run_query($query);

                if (@mysql_errno() == 0) {
                    // Remove dugnads from this person
                    $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $splits[1] . "'";
                    @run_query($query);

                    $feedback .= "<div class='success'>En ny elefant vandrer iblant oss!</div>";
                }
            } elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -3) {
                /* Beboer is NOT an elefant any more - how can this be!? */

                $query = "UPDATE bs_beboer SET beboer_spesial = '0'
                            WHERE beboer_id = '" . $splits[1] . "'";

                @run_query($query);

                if (@mysql_errno() == 0) {
                    $feedback .= "<div class='success'>En elefantfeil har blitt rettet opp...</div>";
                }
            }

            /* Blivende Elefant business ...
            ------------------------------------------------------------------------------------------------------------ */ elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -8) {
                /* Beboer will become an elefant and should only have one dugnad this semester.. */

                $query = "UPDATE bs_beboer SET beboer_spesial = '8'
                            WHERE beboer_id = '" . $splits[1] . "'";

                @run_query($query);

                if (@mysql_errno() == 0) {
                    // Remove dugnads from this person
                    $query = "SELECT 1 FROM bs_deltager WHERE deltager_beboer = " . $splits[1] . "";
                    $result_bli = @run_query($query);

                    if (@mysql_num_rows($result_bli) > 1) {
                        $del_dugnads = @mysql_num_rows($result_bli) - (@mysql_num_rows($result_bli) - 1);
                        // Remove dugnads from this person
                        $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $splits[1] . "' LIMIT " . $del_dugnads;
                        @run_query($query);
                    }

                    $feedback .= "<div class='success'>En beboer blir elefant dette semesteret...</div>";
                }
            } elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -9) {
                /* Beboer is will not become an elefant this semester anyway.. */

                $query = "UPDATE bs_beboer SET beboer_spesial = '0'
                            WHERE beboer_id = '" . $splits[1] . "'";

                @run_query($query);

                if (@mysql_errno() == 0) {
                    $feedback .= "<div class='success'>En beboer blir ikke elefant dette semestere likevel...</div>";
                }
            }

            /* Festforening FF business ...
            ------------------------------------------------------------------------------------------------------------ */ elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -4) {
                /* Beboer is in the Festforening and should not have any dugnads - this semester! */

                $query = "UPDATE bs_beboer SET beboer_spesial = '4'
                            WHERE beboer_id = '" . $splits[1] . "'";

                @run_query($query);

                if (@mysql_errno() == 0) {
                    // Remove dugnads from this person
                    $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $splits[1] . "'";
                    @run_query($query);

                    $feedback .= "<div class='success'>Blindern&aring;nden lever - en beboer er i festforeningen!</div>";
                }
            } elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -5) {
                /* Beboer is NOT in the Festforening any more - how can this be!? */

                $query = "UPDATE bs_beboer SET beboer_spesial = '0'
                            WHERE beboer_id = '" . $splits[1] . "'";

                @run_query($query);

                if (@mysql_errno() == 0) {
                    $feedback .= "<div class='success'>En er ikke lenger med i Festforeningen...</div>";
                }
            }

            /* Dugnadsfri business ...
            ------------------------------------------------------------------------------------------------------------ */ elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -6) {
                /* Beboer is the Dugnadsfri and should not have any dugnads! */

                $query = "UPDATE bs_beboer SET beboer_spesial = '6'
                            WHERE beboer_id = '" . $splits[1] . "'";
                @run_query($query);

                if (@mysql_errno() == 0) {

                    // Remove dugnads from this person
                    $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $splits[1] . "'";
                    @run_query($query);


                    $feedback .= "<div class='success'>En ny beboer har n&aring; dugnadsfri...</div>";
                }
            } elseif (is_numeric($splits[1]) && get_beboer_name($splits[1]) && (int) $new_dugnad == -7) {
                /* Beboer is NOT the Dugnadsfri any more - how can this be!? */

                $query = "UPDATE bs_beboer SET beboer_spesial = '0'
                            WHERE beboer_id = '" . $splits[1] . "'";

                @run_query($query);

                if (@mysql_errno() == 0) {
                    $feedback .= "<div class='success'>En beboer har ikke lenger dugnadsfri...</div>";
                }
            }
        }

        /* ----------------------------------------------------------------
         * ================================================================
         *
         * OPPDATERE DUGNADSBARNA - DUGNAD HAS BEEN DONE, NOT DONE, CHANGED
         *
         * ================================================================
         * ---------------------------------------------------------------- */ elseif (is_numeric($splits[0]) && get_beboer_name($splits[0])) {
            if ((int) $new_dugnad < -1) {
                /* The beboer want to take or has completed a dugnad at Vedlikehold
                --------------------------------------------------------------------------- */
                $query = "UPDATE bs_deltager SET deltager_dugnad = '" . $new_dugnad . "'
                                WHERE deltager_beboer = '" . $splits[0] . "' AND deltager_id = '" . $splits[1] . "'";

                @run_query($query);

                if (@mysql_errno() != 0) {
                    $feedback .= "<div class='failure'>Problemer med &aring; lagre ny dugnadstype, kontakt dugnadsleder.</div>";
                }
            } elseif (!strcmp($new_dugnad, "-1")) {
                /* Admin has selected to REMOVE dugnad from the user, which is kind. :-)
                --------------------------------------------------------------------------- */

                $query = "DELETE FROM bs_deltager WHERE deltager_id = '" . $splits[1] . "'";
                @run_query($query);
            } elseif (valid_dugnad_id($new_dugnad)) {

                /* Admin wants to change a dugnad
                --------------------------------------------------------------------------- */

                /* $user_id ."_". $deltager_id ."_". $select_count */

                if (!double_booked_dugnad($new_dugnad, $splits[0], $splits[1])) {
                    $query = "UPDATE bs_deltager SET deltager_dugnad = '" . $new_dugnad . "'
                                WHERE deltager_beboer = '" . $splits[0] . "' AND deltager_id = '" . $splits[1] . "'";

                    @run_query($query);

                    if (@mysql_errno() == 0) {
                        if (@mysql_affected_rows()) {
                            $feedback .= "<div class='success'>Dugnadsdatoen ble lagret.</div>";
                        }
                    } else {
                        $feedback .= "<div class='failure'>Det oppstod en feil, ta kontakt med en dugnadsleder for &aring; l&oslash;se problemet.</div>";
                    }
                } else {
                    /*
                    print "<p>". get_beboerid_name($splits[0]). " (deltager ". $splits[1] ."): ";
                    print_r($splits);
                    print " New d: ". $new_dugnad ."</p>"; */

                    $feedback .= "<div class='failure'>Du har valgt flere dugnader p&aring; samme dag, en dugnad ble ikke oppdatert.</div>";
                }
            } else {
                if (!unchanged_dugnad($splits[1], $new_dugnad)) {
                    $feedback .= "<div class='failure'>Dugnadsdagen er ugyldig, ta kontakt med en dugnadsleder for &aring; l&oslash;se problemet.</div>";
                }
            }
        }
    }

    return $feedback;
}


/* ******************************************************************************************** *
 *  double_booked_dugnad($new_dugnad, $beboer_id, $deltager_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function double_booked_dugnad($new_dugnad, $beboer_id, $deltager_id)
{
    $query = "SELECT deltager_id, deltager_beboer, deltager_dugnad, deltager_gjort
                FROM bs_deltager
                WHERE deltager_beboer = '" . $beboer_id . "'
                    AND deltager_dugnad = '" . $new_dugnad . "'
                    AND deltager_id <> '" . $deltager_id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        return true;
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  admin_make_select($user_id, $select_count, $date_id, $deltager_id = false)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function admin_make_select($user_id, $select_count, $date_id, $deltager_id = false)
{
    if (!$deltager_id) {
        $deltager_id = get_deltager_id($user_id, $date_id);
    }

    $content .= "\n<select size='1' name='" . $user_id . "_" . $deltager_id . "'>\n";

    if ((int) $date_id == -2) {
        $petter_selected = "selected='selected' ";
    } elseif ((int) $date_id == -3) {
        $petter_done_selected = "selected='selected' ";
    } elseif ((int) $date_id == -10) {
        $hyttedugnad_selected = "selected='selected' ";
    } elseif ((int) $date_id == -11) {
        $ryddevakt_selected = "selected='selected' ";
    } elseif ((int) $date_id == -12) {
        $billavakt_selected = "selected='selected' ";
    }


    $content .=    "<option value='-10' " . $hyttedugnad_selected . ">Hyttedugnad</option>\n";
    $content .= "<option value='-11' " . $ryddevakt_selected . ">Ryddevakt</option>\n";
    $content .= "<option value='-12' " . $billavakt_selected . ">Billavakt</option>\n";
    $content .= "<option value='-3' " . $petter_done_selected . ">Utf&oslash;rt</option>\n<option value='-2' " . $petter_selected . ">Dagdugnad</option>\n<option value='-1'>Slett</option>\n"; {
        $query = "SELECT
                    dugnad_dato        AS da_date,
                    dugnad_id        AS id,
                    dugnad_type,
                    dugnad_min_kids,
                    dugnad_max_kids
                FROM bs_dugnad
                WHERE dugnad_slettet ='0' AND " . get_dugnad_range() . "
                ORDER BY dugnad_dato";

        $result = @run_query($query);

        while ($row = @mysql_fetch_array($result)) {
            $count_all = get_dugnad_count();
            $count = isset($count_all[$row['id']]) ? $count_all[$row['id']] : 0;

            $note = '';
            if ($row['dugnad_type'] == 'anretning') {
                $note .= ' (anretning)';
            }

            $note .= " ($count stk)";

            if ($count < $row['dugnad_min_kids']) {
                $note .= ' (-)';
            } else if ($count > $row['dugnad_max_kids']) {
                $note .= ' (+)';
            }

            $selected = $row['id'] == $date_id ? ' selected="selected"' : '';
            $content .= "<option value='" . $row["id"] . "'" . $selected . ">" . get_simple_date($row["da_date"], true) . $note . "</option>\n";
        }
    }

    $content .= "</select>\n";

    return $content;
}


/* ******************************************************************************************** *
 * make_last_beboere_select () - select box - option box - last import beboer list
 * --------------------------------------------------------------------------------------------
 *
 * Enables administrator to select which import to gather, to make a list of dugnadsinnkallinger.
 *
 * Returns: a select box that includes all the imports of beboere.
 *
 * ============================================================================================ */

function make_last_beboere_select()
{
    global $dugnad_is_empty, $dugnad_is_full;

    if (!$deltager_id) {
        $deltager_id = get_deltager_id($user_id, $date_id);
    }

    $query = "SELECT
                    DISTINCT deltager_notat AS notat

                FROM bs_deltager
                WHERE
                    deltager_notat LIKE 'IMP%'

                ORDER BY deltager_notat DESC";

    $result = @run_query($query);

    if (mysql_num_rows($result)) {
        $content .= "\n<select size='1' name='nyinnkalling'>\n";
        while ($row = @mysql_fetch_array($result)) {
            $content .= "<option value='" . $row["notat"] . "' >Import " . sprintf("%03d", substr($row["notat"], 3)) . "</option>\n";
        }
    } else {
        $content .= "\n<select size='1' name='nyinnkalling' disabled='disabled'>\n";
        $content .= "<option value='-1' >Ingen tilf&oslash;yninger</option>\n";
    }
    $content .= "</select>\n";

    return $content;
}



/* ******************************************************************************************** *
 * beboer_make_select () - select box - option box - last import beboer list
 * --------------------------------------------------------------------------------------------
 *
 * Enables administrator to select which import to gather, to make a list of dugnadsinnkallinger.
 *
 * Returns: a select box that includes all the imports of beboere.
 *
 * ============================================================================================ */

function beboer_make_select($user_id, $select_count, $date_id)
{
    /* Used ONLY to let the user be able to change dugnad
    ------------------------------------------------------------------------------------ */

    global $dugnad_is_empty, $dugnad_is_full;

    $deltager_id = get_deltager_id($user_id, $date_id);

    // get details about this dugnad
    $prefix = '';
    $result = @run_query("
        SELECT dugnad_id, dugnad_dato, dugnad_type
        FROM bs_dugnad
        WHERE dugnad_id = '" . $date_id . "'");
    if ($dugnad = mysql_fetch_assoc($result)) {
        $prefix = get_dugnad_type_prefix($row);
    }

    if (isset($dugnad_is_empty[$date_id])) {

        /* Too few beboere at this particular day - disabling drop-down menu...
        ------------------------------------------------------------------------------------------ */

        $content .= "\n<select size='1' name='" . $user_id . "_" . $deltager_id . "' disabled class='no_block' >\n";


        $query = "SELECT    dugnad_dato        AS da_date,
                            dugnad_id        AS id

                    FROM bs_dugnad
                    WHERE dugnad_id = '" . $date_id . "'
                    LIMIT 1";

        $result = @run_query($query);

        if (@mysql_num_rows($result) == 1) {
            $row = @mysql_fetch_array($result);
            $content .= "<option value='" . $row["id"] . "' selected='selected' >" . $prefix . get_simple_date($row["da_date"], true) . "</option>\n";
        } else {
            $content .= "<option value='-1' selected='selected' >:-)</option>\n";
        }

        $content .= "</select>\n";
    } else {
        /* Not too few...
        ------------------------------------------------------------------------------------------ */

        $content .= "\n<select size='1' name='" . $user_id . "_" . $deltager_id . "_" . $select_count . "' class='no_block' >\n";

        if ((int) $date_id < 0) {
            if ((int) $date_id == -2) {
                $content .=    "<option value='-2' selected='selected' >Dagdugnad</option>\n";
            } elseif ((int) $date_id == -3) {
                $content .=    "<option value='-3' selected='selected' >Utf&oslash;rt</option>\n";
            } elseif ((int) $date_id == -10) {
                $content .=    "<option value='-10' selected='selected' >Hyttedugnad</option>\n";
            } elseif ((int) $date_id == -11) {
                $content .=    "<option value='-11' selected='selected' >Ryddevakt</option>\n";
            } elseif ((int) $date_id == -12) {
                $content .=    "<option value='-12' selected='selected' >Billavakt</option>\n";
            }
        } elseif ($dugnad && $dugnad['dugnad_type'] == 'anretning') {
            $content .=    "<option value='" . $dugnad['dugnad_id'] . "' selected='selected'>Anretningsdugnad: " . get_simple_date($dugnad['dugnad_dato'], true) . "</option>\n";
        } else {
            $content .=    "<option value='-10' >Hyttedugnad</option>\n";

            $query = "SELECT

                        dugnad_dato        AS da_date,
                        dugnad_id        AS id

                    FROM bs_dugnad
                    WHERE dugnad_dato > DATE_ADD(CURDATE(),INTERVAL 6 DAY)
                        AND dugnad_slettet ='0'
                    ORDER BY dugnad_dato";

            $result = @run_query($query);

            while ($row = @mysql_fetch_array($result)) {
                if (!strcmp($row["id"], $date_id)) {
                    $content .= "<option value='" . $row["id"] . "' selected='selected' >" . $prefix . get_simple_date($row["da_date"], true) . "</option>\n";
                } else {
                    $checked = null;

                    if (empty($dugnad_is_full[$row["id"]])) {
                        $content .= "<option value='" . $row["id"] . "' " . $checked . ">" . $prefix . get_simple_date($row["da_date"], true) . "</option>\n";
                    }
                }
            }
        }
    }

    $content .= "</select>\n";

    return $content;
}


/* ******************************************************************************************** *
 *  get_dugnad_range()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_dugnad_range()
{
    $year    = date("Y", time());
    $month    = date("m", time());

    // ignorer begrensning, vis alt!
    return "TRUE";

    if ($month > 7) {
        /* We now know we are in the autumn semester, months: 8-12
        -------------------------------------------------------- */
        return "YEAR(dugnad_dato) = YEAR(NOW()) AND
                    (MONTH(dugnad_dato) >= 8 AND MONTH(dugnad_dato) <= 12) ";
    } else {
        /* .. Spring, months: 1-7
        -------------------------------------------------------- */
        return "YEAR(dugnad_dato) = YEAR(NOW()) AND
                    (MONTH(dugnad_dato) >= 1 AND MONTH(dugnad_dato) <= 7) ";
    }
}


/* ******************************************************************************************** *
 *  get_dugnads($id, $hide_outdated_dugnads = false)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_dugnads($id, $hide_outdated_dugnads = false)
{

    /* Creates static text showing all dugnads for a beboer ($id)
       If you need drop-down boxes containing ALL valid options, use admin_get_dugnads()
     -------------------------------------------------------------------------------------------- */

    global $formdata, $isSubFolder;

    $query = "SELECT    deltager_dugnad    AS id,

                        dugnad_dato        AS dugnad_dato,
                        dugnad_checked    AS checked,
                        dugnad_type,

                        deltager_gjort    AS status,
                        deltager_type    AS kind,
                        deltager_notat    AS note

                FROM bs_deltager

                    LEFT JOIN bs_dugnad
                        ON dugnad_id = deltager_dugnad AND dugnad_slettet = '0'

                WHERE deltager_beboer = '" . $id . "'
                    " . ($hide_outdated_dugnads ? 'AND (deltager_gjort = \'0\' AND (dugnad_dato > now() OR dugnad_dato IS NULL)) ' : '') . "

                    AND (dugnad_dato IS NULL OR (" . get_dugnad_range() . "))

                ORDER BY dugnad_dato";

    $result = @run_query($query);

    while ($row = @mysql_fetch_array($result)) {
        $type = get_dugnad_type_prefix($row);

        /* ADDING NOTES
        ------------------------------------------------------------ */

        if (!empty($row["note"])) {
            // Showing the note
            $more_info = " <img src='" . $isSubFolder . "./images/info.gif' alt='[i]' title='" . $row["note"] . "'>";
        } else {
            // No note to show
            $more_info = null;
        }

        /* ADDING DUGNADS
        ------------------------------------------------------------ */

        if ((int) $row["id"] > 0 && !strcmp($row["status"], "0")) {

            // Those done and not done (yet)

            if (!strcmp($row["checked"], "1")) {
                $use_class = "done_dugnad";
            } else {
                $use_class = "valid_dugnad";
            }
            $content .= "<span class='" . $use_class . "'>" . $type .  get_simple_date($row["dugnad_dato"], true) . $more_info . "</span>\n";
        } else {

            /* Dugnad special cases
            --------------------------- */

            if ((int) $row["id"] < 0) {

                // OK dugnads;

                if ((int) $row["id"] == -2) {
                    $content .=    "<span class='valid_dugnad'>Dagdugnad</span>\n";
                } elseif ((int) $row["id"] == -3) {
                    $content .=    "<span class='done_dugnad'>Utf&oslash;rt</span>\n";
                } elseif ((int) $row["id"] == -10) {
                    $content .=    "<span class='valid_dugnad'>Hyttedugnad</span>\n";
                } elseif ((int) $row["id"] == -11) {
                    $content .=    "<span class='valid_dugnad'>Ryddevakt</span>\n";
                } elseif ((int) $row["id"] == -12) {
                    $content .=    "<span class='valid_dugnad'>Billavakt</span>\n";
                }
            } else {
                // Damn dugnads; those they did not do..

                $content .= "<span class='damn_dugnad'>" . $type . get_simple_date($row["dugnad_dato"], true) . $more_info . "</span>\n";
            }
        }
    }

    return $content;
}


/* ******************************************************************************************** *
 *  admin_get_petter_select($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function admin_get_petter_select($beboer_id)
{

    /* Creates drop-downs showing all valid dugnads for a beboer, with the dugnad with Dagdugnad selected.
     -------------------------------------------------------------------------------------------------- */

    global $formdata;

    $query = "SELECT
                        deltager_id        AS id,
                        deltager_gjort    AS completed,
                        deltager_type    AS kind,
                        deltager_notat    AS note,
                        deltager_dugnad    AS dugnad

                FROM bs_deltager

                WHERE deltager_beboer = '" . $beboer_id . "'
                    AND deltager_dugnad < -1";

    $result = @run_query($query);
    $c = 0;

    while ($row = @mysql_fetch_array($result)) {
        /* admin_make_select($user_id, $select_count, $petter_code, $deltager_id = false) */
        $content .= admin_make_select($beboer_id, $c++, $row["dugnad"], $row["id"]);
    }

    return $content;
}


/* ******************************************************************************************** *
 *  admin_get_dugnads($id, $editable = true)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function admin_get_dugnads($id, $editable = true)
{

    /* Creates drop-downs showing all valid dugnads for a beboer, with the current selected ($id)
       If you need static text, use get_dugnads()
     -------------------------------------------------------------------------------------------- */

    global $formdata, $isSubFolder;

    $query = "SELECT    dugnad_id                    AS id,
                        dugnad_dato                    AS dugnad_dato,
                        UNIX_TIMESTAMP(dugnad_dato)    AS is_happening,
                        dugnad_checked                AS checked,

                        deltager_gjort    AS completed,
                        deltager_type    AS kind,
                        deltager_notat    AS note,
                        deltager_id        AS delt_id

                FROM bs_deltager, bs_dugnad

                WHERE deltager_beboer = '" . $id . "'
                    AND dugnad_id = deltager_dugnad

                    AND " . get_dugnad_range() . "

                    AND dugnad_slettet = '0'";

    $result = @run_query($query);
    $c = 0;

    while ($row = @mysql_fetch_array($result)) {

        if (!strcmp($row["checked"], "1")) {
            /* This day has been checked, if the beboer did not attend the dugnad, it should be red
            ------------------------------------------------------------------------------------------------ */

            if (!empty($row["note"])) {
                /* Displaying the note field, if there are any notes..
                --------------------------------------------------------------- */

                $more_info = " <img src='./images/info.gif' alt='[i]' title='" . $row["note"] . "'>";
            }

            if (strcmp($row["completed"], "0")) {
                /* Did not do dugnad, mark as damn_dugnad (red)
                -------------------------------------------------------- */

                // $content .= "<div class='damn_dugnad'>". get_simple_date($row["dugnad_dato"], true) . $more_info ."</div>\n";

                $content .= "<img class='dugnads_status' src='" . $isSubFolder . "./images/dugnad_damn.png' width='24px' height='24px' title='" . get_simple_date($row["dugnad_dato"], true) . " " . $row["note"] . "' alt='[BOT - " . get_simple_date($row["dugnad_dato"], true) . " " . $row["note"] . "]'>\n";
            } else {
                /* Dugnad is done, mark as done_dugnad (green)
                -------------------------------------------------------- */
                // $content .= "<div class='done_dugnad'>". get_simple_date($row["dugnad_dato"], true) . $more_info ."</div>\n";

                $content .= "<img class='dugnads_status' src='" . $isSubFolder . "./images/dugnad_ok.png' width='24px' height='24px' title='" . get_simple_date($row["dugnad_dato"], true) . " " . $row["note"] . "' alt='[OK - " . get_simple_date($row["dugnad_dato"], true) . " " . $row["note"] . "]'>\n";
            }
        } else {
            /* Paramters for admin_make_select: $user_id, $select_count, $date_id, $deltager_id = false) */
            $content .= admin_make_select($id, $c++, $row["id"], $row["delt_id"]);
        }
    }

    $content .= admin_get_petter_select($id);
    return $content;
}


/* ******************************************************************************************** *
 *  admin_addremove_dugnad($user_id, $date_id = null)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function admin_addremove_dugnad($user_id, $date_id = null)
{
    $select .= "\n<select size='1' name='admin_" . $user_id . "_" . $date_id . "'>

            <option value='-7'>Ikke dugnadsfri</option>
            <option value='-6'>Dugnadsfri</option>

            <option value='-5'>Ikke FF</option>
            <option value='-4'>Festforening</option>

            <option value='-9'>Ikke blivende</option>
            <option value='-8'>Blivende Elefant</option>

            <option value='-3'>Ikke elefant</option>
            <option value='-2'>Elefant</option>
            <option value='-1'>Slette alle</option>
            <option value='0' selected='selected' >Uendret</option>
            <option value='1'>En ny</option>
            <option value='2'>To nye</option>
            <option value='3'>Tre nye</option>
            <option value='4'>Fire nye!</option>
            </select>\n";

    return $select;
}


/* ******************************************************************************************** *
 *  change_status($user_id, $date_id = null)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function change_status($user_id, $date_id = null)
{

    $query =   "SELECT
                    1

                FROM
                    bs_beboer

                WHERE
                    beboer_id = " . $user_id . " AND
                    beboer_spesial <> 2 AND
                    beboer_spesial <> 8

                LIMIT 1";

    $result = run_query($query);

    if (mysql_num_rows($result)) {
        $select .= "\n<select size='1' name='admin_" . $user_id . "_" . $date_id . "'>

                <option value='-5'>Ikke FF</option>
                <option value='-4'>Festforening</option>

                <option value='-7'>Ikke dugnadsfri</option>
                <option value='-6'>Dugnadsfri</option>

                <option value='0' selected='selected' >Uendret</option>

                </select>\n";

        return $select;
    }
}


/* ******************************************************************************************** *
 *  beboer_get_dugnads($id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function beboer_get_dugnads($id)
{

    /* Used ONLY for letting the beboer change dugnads ...
    ---------------------------------------------------------------- */

    global $formdata;

    $query = "SELECT    deltager_dugnad                AS id,

                        dugnad_dato                    AS dugnad_dato,
                        UNIX_TIMESTAMP(dugnad_dato)    AS is_happening,
                        dugnad_checked                AS checked,

                        deltager_gjort    AS status,
                        deltager_type    AS kind,
                        deltager_notat    AS note

                FROM bs_deltager

                    LEFT JOIN bs_dugnad
                        ON dugnad_id = deltager_dugnad
                            AND dugnad_slettet = '0'

                WHERE deltager_beboer = '" . $id . "'
                        AND " . get_dugnad_range();

    $result = @run_query($query);
    $c = 0;

    while ($row = @mysql_fetch_array($result)) {
        if (!strcmp($row["status"], "0")) {

            if ((int) $row["id"] > 0 && (int) $row["is_happening"] < (time() + (60 * 60 * 24 * 6))) {
                /* If the dugnad is allready completed - add it as pure text
                ---------------------------------------------------------------------------------------- */

                if (!strcmp($row["checked"], "1")) {
                    $use_class = "done_dugnad";
                } else {
                    $use_class = "valid_dugnad";
                }
                $static_content .= "<span class='" . $use_class . "'>" . get_simple_date($row["dugnad_dato"], true) . "</span>\n";
            } else {
                /* Add the dugnad as a selection box
                ---------------------------------------------------------------------------------------- */

                $content .= beboer_make_select($id, $c++, $row["id"]);
            }
        } else {
            if (!empty($row["note"])) {
                $more_info = " <img src='./images/info.gif' alt='[i]' title='" . $row["note"] . "'>";
            }

            $static_content .= "<span class='damn_dugnad'>" . get_simple_date($row["dugnad_dato"], true) . $more_info . "</span>\n";
        }
    }

    return $static_content . $content;
}


/* ******************************************************************************************** *
 *  get_undone_dugnads($id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_undone_dugnads($id)
{
    global $formdata;

    $query = "SELECT    dugnad_id        AS id,
                        dugnad_dato        AS dugnad_dato,
                        dugnad_checked    AS checked,
                        dugnad_type,

                        deltager_gjort    AS completed,
                        deltager_type    AS kind,
                        deltager_notat    AS note

                FROM bs_deltager, bs_dugnad

                WHERE deltager_beboer = '" . $id . "'
                    AND dugnad_id = deltager_dugnad
                    AND dugnad_dato > CURDATE()
                    AND dugnad_slettet = '0'";

    $result = @run_query($query);
    $count = 0;
    $total_count = @mysql_num_rows($result);

    while ($row = @mysql_fetch_array($result)) {
        $count++;
        $content .= $comma . get_dugnad_type_prefix($row) . get_simple_date($row["dugnad_dato"], true);

        if (($count + 1) < $total_count) {
            $comma = ", ";
        } else {
            $comma = " og ";
        }
    }

    if ($count == 0) {
        return null;
    } else {
        return $content;
    }
}


/* ******************************************************************************************** *
 *  get_simple_date($complex, $very_simple = false)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_simple_date($complex, $very_simple = false)
{
    $complex = explode("-", substr($complex, 0, 10));

    $simple = $complex[2] . "." . $complex[1] . "." . ($very_simple ? substr($complex[0], -2) : $complex[0]);

    return $simple;
}

/* ******************************************************************************************** *
 * show_vedlikehold_person ( int, int )
 * --------------------------------------------------------------------------------------------
 *
 * Returns: A html-formattes list of all kids having dugnad
 *
 * Params : $id Beboer identity number
 *            $line_count to set the correct background color
 *
 * Used by:
 *
 * ============================================================================================ */

function show_vedlikehold_person($id, $line_count)
{
    global $formdata;

    $query = "SELECT    beboer_for        AS first,
                        beboer_etter    AS last,
                        rom_tlf,
                        rom_nr

                FROM bs_beboer

                    LEFT JOIN bs_rom
                        ON beboer_rom = rom_id

                WHERE beboer_id = '" . $id . "' AND
                        beboer_spesial = '0'

                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        list($first, $last, $tlf, $rom) = @mysql_fetch_row($result);

        if ($admin) {
            $check_box = "<input type='checkbox' name='delete_person[]' value='" . $id . "'> ";
        }

        if (empty($formdata["sorts"]) || !strcmp($formdata["sorts"], "last")) {
            $full_name = $last . ", " . $first;
        } else {
            $full_name = $first . " " . $last;
        }

        $full_name .= " (tlf " . $tlf . ")";

        /* Normal business ... */

        $dugnads = admin_get_dugnads($id, $admin);

        $entries .= "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $check_box . $full_name . (empty($rom) ? " (<b>rom ukjent</b>)" : null) . "</div>\n<div class='when'>" . $dugnads . "</div><div class='note'>" . get_notes($id, true) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";

        return $entries;
    } else {
        return "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'>Halvor Gimnes var her!</div>";
    }
}

/* ******************************************************************************************** *
 * show_person (int, int, boolean/int)
 * --------------------------------------------------------------------------------------------
 *
 * Returns: Shows the beboer with $id
 *
 * Used by: output_full_list ( boolean )
 *
 * Params : $admin = false for normal beboere
 *          $admin = 1 for SuperUsers (allowed to delete, change date and status)
 *            $admin = 2 for Administrasjon (allowed to change status and date)
 *            $admin = 3 for Undergrupper (only allowed to change status)
 * ============================================================================================ */

function show_person($id, $line_count, $admin = false)
{
    global $formdata;

    $query = "SELECT    beboer_for        AS first,
                        beboer_etter    AS last,
                        beboer_spesial    AS spesial,

                        rom_tlf,
                        rom_nr

                FROM bs_beboer

                    LEFT JOIN bs_rom
                        ON beboer_rom = rom_id

                WHERE beboer_id = '" . $id . "'

                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        list($first, $last, $spesial, $tlf, $rom) = @mysql_fetch_row($result);


        // ONLY SUPER USER ARE ALLOWED TO DELETE BEBOERE
        if ($admin == 1) {
            $check_box = "<input type='checkbox' name='delete_person[]' value='" . $id . "'> ";
        }

        if (empty($formdata["sorts"]) || !strcmp($formdata["sorts"], "last")) {
            $full_name = get_public_lastname($last, $first, true, $admin);
        } else {
            $full_name = get_public_lastname($last, $first, false, $admin);
        }


        /* Outputting a static list of dugnads and status
        --------------------------------------------------------------------- */

        if ($admin === false) {
            // BEBOERE ARE SHOWN ONLY STATIC ELEMENTS
            $show_ff_details = true;
            $dugnads = get_special_status_image($id, $line_count, $show_ff_details) . get_dugnads($id);
        } elseif ($admin == 3) {
            // SHOWING SPECIAL IMAGE AND STATIC DUGNADS
            $dugnads = get_special_status_image($id, $line_count) . get_dugnads($id) . change_status($id, $line_count);
        } elseif ($admin == 1 || $admin == 2) {
            // SHOWING SPECIAL IMAGE AND DYNAMIC DUGNADS
            $dugnads = get_special_status_image($id, $line_count) . admin_get_dugnads($id, $admin) . admin_addremove_dugnad($id, $line_count);
        }


        $entries .= "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $check_box . $full_name . (empty($rom) ? " (<b>rom ukjent</b>)" : null) . "</div>\n<div class='when'>" . $dugnads . "</div><div class='note'>" . get_notes($id, $admin) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";

        return $entries;
    } else {
        return "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'>Halvor Gimnes var her!</div>";
    }
}


/* ******************************************************************************************** *
 *  get_public_lastname($last, $first, $last_first = false, $admin = false)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_public_lastname($last, $first, $last_first = false, $admin = false)
{
    if ($last_first) {
        if (!$admin && strlen($last) > 4) {
            $full_name = utf8_substr($last, 0, 4) . "., " . $first;
        } else {
            $full_name = $last . ", " . $first;
        }
    } else {
        if (!$admin && strlen($last) > 4) {
            $full_name = $first . " " . utf8_substr($last, 0, 4) . "...";
        } else {
            $full_name = $first . " " . $last;
        }
    }

    return $full_name;
}


/* ******************************************************************************************** *
 *  show_beboer_ctrlpanel($id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function show_beboer_ctrlpanel($id)
{
    /* Makes a form for the beboer needed to change date for the dugnad
    ------------------------------------------------------------------------------------ */

    global $formdata;

    $query = "SELECT    beboer_for        AS first,
                        beboer_etter    AS last

                FROM bs_beboer

                WHERE beboer_id = '" . $id . "'
                LIMIT 1";

    $result = @run_query($query);

    $valid_login = valid_admin_login();

    if ($valid_login == 1 || $valid_login == 2) {
        $admin_show_password = "<b>Passord</b>: " . get_beboer_password($id) . "&nbsp;";
    }


    if (@mysql_num_rows($result) == 1) {
        list($first, $last) = @mysql_fetch_row($result);

        $full_name = get_public_lastname($last, $first, false, true);

        $entries .= "<div class='name_ctrl'>" . $full_name . "</div>\n
                    <div class='room_ctrl'>Rom: " . get_room_select($id) . "</div>
                    <div class='when_ctrl'>" . beboer_get_dugnads($id) . "</div>
                    <div class='note'>" . $admin_show_password . "<input type='submit' value='Lagre endringer'></div>
                    <div class='spacer_small'>&nbsp;</div>\n\n";

        return $entries;
    } else {
        return "<div class='success'>Ugyldig beboerkode, vennligst ta kontakt med en dugnadsleder.</div>";
    }
}


/* ******************************************************************************************** *
 *  show_all_saturdays()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function show_all_saturdays()
{
    $count = 0;

    $year    = date("Y", time());
    $month    = date("m", time());

    if ($month > 7) {
        /* We now know we are in the autumn semester
        -------------------------------------------------------- */
        $months = array("8", "9", "10", "11", "12");
    } else {
        /* .. Spring
        -------------------------------------------------------- */
        $months = array("1", "2", "3", "4", "5", "6");
    }

    $months = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

    $content = "<img src='./images/update.gif' class='hoyre' /><h1>Merk alle dugnadsfrie l&oslash;rdager:</h1>\n\n";

    foreach ($months as $month) {
        $content .= "<div class='saturday_container'>\n";

        for ($i = 1; $i <= 31; $i++) {
            if (checkdate($month, $i, $year)) {
                if (date("w", mktime(0, 0, 0, $month, $i, $year)) == '6') {
                    $count++;

                    if (date_exist($month, $i, $year)) {
                        if (!date_is_disabled($month, $i, $year)) {
                            $content .= "<div class='saturday'><input type='checkbox' name='sat[]' value='" . get_date_id($month, $i, $year) . "' /> " . $i . ". " . date("M", mktime(0, 0, 0, $month, $i, $year)) . " <span class='disabled'>(" . get_beboer_count(get_date_id($month, $i, $year)) . ")</span></div>\n";
                        } else {
                            $content .= "<div class='saturday_off'><input type='checkbox' name='sat[]' value='" . get_date_id($month, $i, $year) . "' checked='checked' /> " . $i . ". " . date("M", mktime(0, 0, 0, $month, $i, $year)) . "</div>\n";
                        }
                    }
                }
            }
        }

        $content .= "</div><br clear='left' />\n\n";
    }

    return $content;
}


/* ******************************************************************************************** *
 *  update_saturdays_status()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function update_saturdays_status()
{
    global $formdata;


    if (valid_admin_login()) {
        $count = 0;

        $year    = date("Y", time());
        $month    = date("m", time());

        foreach ($formdata["sat"] as $value) {
            /* making an array to make it easier to check further down */
            $disabled_sats[$value] = "1";
        }

        if ($month > 7) {
            /* We now know we are in the autumn semester
            -------------------------------------------------------- */
            $months = array("8", "9", "10", "11", "12");
        } else {
            /* .. Spring
            -------------------------------------------------------- */
            $months = array("1", "2", "3", "4", "5", "6");
        }

        foreach ($months as $month) {
            for ($i = 1; $i <= 31; $i++) {
                if (checkdate($month, $i, $year)) {
                    if (date("w", mktime(0, 0, 0, $month, $i, $year)) == '6') {
                        $count++;

                        if (date_exist($month, $i, $year)) {
                            $sat_id = get_date_id($month, $i, $year);

                            if (!empty($disabled_sats[$sat_id])) {
                                $query = "UPDATE bs_dugnad SET dugnad_slettet = '1'
                                            WHERE dugnad_id = '" . $sat_id . "'";
                            } else {
                                $query = "UPDATE bs_dugnad SET dugnad_slettet = '0'
                                            WHERE dugnad_id = '" . $sat_id . "'";
                            }

                            @run_query($query);
                        }
                    }
                }
            }
        }

        return null;
    } else {
        return "<p class='failure'>Du har ikke tastet inn korrekt passord.</p>";
    }
}



/* ******************************************************************************************** *
 * output_full_list ( boolean )
 * --------------------------------------------------------------------------------------------
 *
 * Returns   : the compelete list for all beboere, showing all dugnads
 *
 * Used by   : BOTH beboere and Admin.
 *
 * Parameters: $admin == 1 enables drop down boxes enabling user to change dates and states
 *               $admin == 2 allows change of date and status
 *               $admin == 3 only allowed to change status
 *               $admin == false disables ALL editing; shows a text based list
 *
 * See also  : output_vedlikehold_list (the list made for dagdugnad/Vedlikehold)
 *
 * ============================================================================================ */

function output_full_list($admin = false)
{
    global $formdata;

    $query = "SELECT beboer_for, beboer_etter, beboer_id AS id
                FROM bs_beboer ";

    if (empty($formdata["sorts"])) {
        /* Default is alphabetical by surname
        ------------------------------------------------------ */
        $sort_last = "checked='checked' ";
        $sort_query = "ORDER BY beboer_etter, beboer_for";
    } else {
        if (!strcmp($formdata["sorts"], "last")) {
            /* SORT BY LAST NAME */

            $sort_last = "checked='checked' ";
            $query .= "ORDER BY beboer_etter, beboer_for";
        } elseif (!strcmp($formdata["sorts"], "first")) {
            /* SORT BY FIRST NAME */

            $sort_first = "checked='checked' ";
            $query .= "ORDER BY beboer_for, beboer_etter";
        } else {
            /* SORT BY DATE */

            $sort_date = "checked='checked' ";

            $query = "SELECT DISTINCT dugnad_id AS id
                            FROM bs_dugnad
                            ORDER BY dugnad_dato";
        }
    }

    if ($admin) {

        /* ADDING HEADER FOR ADMIN OF DUGNADSLISTE
        ---------------------------------------------------- */

        $hidden = "<input type='hidden' name='do' value='admin' />
                            <input type='hidden' name='admin' value='Dugnadsliste' />";

        $list_title = "Administrering av dugnadsliste";

        $admin_buttons = "<div class='row_explained'><div class='name'><input type='reset' class='check_space' value='Nullstille endringer' /></div><div class='when_narrow'><input type='submit' class='check_space' value='Oppdater dugnadslisten' /></div></div>";
    } else {
        /* SHOWING LIST FOR STATIC DUGNADSLISTE
        ---------------------------------------------------- */

        $hidden = "<input type='hidden' name='do' value='Se dugnadslisten uten passord' />";
        $list_title = "Fullstendig dugnadsliste";
    }

    $content  = "<h1>" . $list_title . "</h1>";
    $content .= "<form method='post' action='index.php'>

    " . $hidden . "

    <p>
        <input type='radio' name='sorts' value='last'  " . $sort_last . "/> Sorter etter etternavn
        <input type='radio' class='check_space' name='sorts' value='first' " . $sort_first . "/> Sorter etter fornavn
        <input type='radio' class='check_space' name='sorts' value='date'  " . $sort_date . "/> Sorter listen etter dato
        <input type='submit' class='check_space' value='Oppdater visning' /></form>
    </p>

        <form method='post' action='index.php'>" . $hidden . "
        \n";


    /* CREATING THE ITEM LINES
    ---------------------------------------------------------------------- */

    if (empty($sort_date)) {
        /* Sort by name, do not group items:
        -------------------------------------------------------- */
        $c = 0;
        $result = @run_query($query . $sort_query);

        $content .= "<div class='row_explained'><div class='name'>" . ($admin == 1 ? "Slett " : null) . "Beboer</div><div class='when_narrow'>Tildelte dugnader</div><div class='note'>Notater</div><div class='spacer'>&nbsp;</div></div>";

        while ($row = @mysql_fetch_array($result)) {
            $content .= "\n\n" . show_person($row["id"], $c++, $admin) . "\n";
        }
    } else {
        /* GROUPING ITEMS by date
        -------------------------------------------------------- */
        $result = @run_query($query);

        while ($row = @mysql_fetch_array($result)) {
            if (!$admin) {
                $content .= show_day($row["id"], $admin);
            } else {
                $content .= admin_show_day($row["id"]);
            }
        }
    }

    return $content . $admin_buttons . "</form>";
}


/* ******************************************************************************************** *
 * output_vedlikehold_list()
 * --------------------------------------------------------------------------------------------
 *
 * Returns : a form with the list of all beboer that has one or more dugnads with Vedlikehold
 *
 * See also: output_full_list (a list with all beboere)
 *
 * Used by :
 *
 * ============================================================================================ */

function output_vedlikehold_list()
{
    global $formdata;

    $query = "SELECT DISTINCT beboer_id AS id, beboer_for, beboer_etter
                FROM bs_beboer, bs_deltager
                WHERE deltager_beboer = beboer_id
                    AND deltager_dugnad = '-2'";

    if (empty($formdata["sorts"]) || !strcmp($formdata["sorts"], "last")) {
        /* SORT BY LAST NAME */

        $sort_last = "checked='checked' ";
        $query .= "ORDER BY beboer_etter, beboer_for";
    } else {
        /* SORT BY FIRST NAME */

        $sort_first = "checked='checked' ";
        $query .= "ORDER BY beboer_for, beboer_etter";
    }

    $hidden = "<input type='hidden' name='do' value='admin' />
                        <input type='hidden' name='admin' value='Dagdugnad' />";

    $list_title = "Dagdugnad";

    $admin_buttons = "<div class='dagdugnad_beboerselect'>Velg ny beboer som skal ha dagdugnad: " . get_vedlikehold_beboer_select() . " <input type='submit' class='check_space' value='Oppdater Dagdugnadslisten' /></div>";

    $content  = "<h1>" . $list_title . "</h1>
                <p>
                    Du kan tilf&oslash;ye en dagdugnad ved &aring; velge beboeren fra listen under.
                    <b>Hvis du tildeler en dagdugnad</b>, er det viktig at du ber beboeren velge hvilken av de ordin&aelig;re dugnadene
                    som skal slettes. <b>N&aring;r en dagdugnad er utf&oslash;rt</b>, er det ogs&aring; viktig at du merker
                    dagdugnaden som utf&oslash;rt.
                </p>
                ";
    $content .= "<form method='post' action='index.php'>

    " . $hidden . "

    <p>
        <input type='radio' name='sorts' value='last'  " . $sort_last . "/> Sorter etter etternavn
        <input type='radio' class='check_space' name='sorts' value='first' " . $sort_first . "/> Sorter etter fornavn
        <input type='submit' class='check_space' name='update' value='Oppdater visning' />
        <input type='submit' class='check_space' name='act' value='Vis dugnadskalenderen' /></form>
    </p>

        <form method='post' action='index.php'>" . $hidden . "
        \n";


    /* CREATING THE ITEM LINES
    ---------------------------------------------------------------------- */

    $c = 0;
    $result = @run_query($query . $sort_query);

    $content .= "<div class='row_explained'><div class='name_narrow'>Beboerens navn</div><div class='when_narrow'>Dugnadstatus</div><div class='note'>Notater</div><div class='spacer'>&nbsp;</div></div>";

    if (@mysql_num_rows($result) > 0) {
        while ($row = @mysql_fetch_array($result)) {
            $content .= "\n\n" . show_vedlikehold_person($row["id"], $c++);
        }
    } else {
        $content .= "<p>Ingen beboere er satt opp med dagdugnad.</p>";
    }

    list($antall, $dato) = count_dugnad_barn();

    return $content . $admin_buttons . "</form>
    <h1>Ordin&aelig;r helgedugnad</h1>
    <p>F&oslash;rstkommende helg har " . $antall . " beboere ordin&aelig;r dugnad (" . $dato . ")" . (isset($formdata["showkids"]) ? ".<br /><a href='index.php?do=admin&admin=Dagdugnad'>Skjul dugnadsdeltagerne.</a></p>" . get_all_barn() : ".<br /><a href='index.php?do=admin&admin=Dagdugnad&showkids=true'>Vis alle dugnadsdeltagerne.</a></p>");
}

/* ******************************************************************************************** *
 * output_ryddevakt_list()
 * --------------------------------------------------------------------------------------------
 *
 * Returns : displays two lists; 1) all with ryddevakt, and 2) everyone else.
 *
 * FIXME: Denne funksjonen er ikke ferdig.
 *
 * ============================================================================================ */

function output_ryddevakt_list()
{
    global $formdata;

    $query = "SELECT DISTINCT beboer_id AS id, beboer_for, beboer_etter
                FROM bs_beboer, bs_deltager
                WHERE deltager_beboer = beboer_id
                    AND deltager_dugnad = '-2'";

    if (empty($formdata["sorts"]) || !strcmp($formdata["sorts"], "last")) {
        /* SORT BY LAST NAME */

        $sort_last = "checked='checked' ";
        $query .= "ORDER BY beboer_etter, beboer_for";
    } else {
        /* SORT BY FIRST NAME */

        $sort_first = "checked='checked' ";
        $query .= "ORDER BY beboer_for, beboer_etter";
    }

    $hidden = "<input type='hidden' name='do' value='admin' />
                        <input type='hidden' name='admin' value='Dagdugnad' />";

    $list_title = "Dagdugnad";

    $admin_buttons = "<div class='row_explained'>" . get_vedlikehold_beboer_select() . " <input type='submit' class='check_space' value='Oppdater Dagdugnad' /></div>";

    $content  = "<h1>" . $list_title . "</h1>
                <p>
                    Du kan tilf&oslash;ye en dagdugnad ved &aring; velge beboeren fra listen under.
                    <b>Hvis du tildeler en dagdugnad</b>, er det viktig at du ber beboeren velge hvilken av de ordin&aelig;re dugnadene
                    som skal slettes. <b>N&aring;r en dagdugnad er utf&oslash;rt</b>, er det ogs&aring; viktig at du merker
                    dagdugnaden som utf&oslash;rt.
                </p>
                ";
    $content .= "<form method='post' action='index.php'>

    " . $hidden . "

    <p>
        <input type='radio' name='sorts' value='last'  " . $sort_last . "/> Sorter etter etternavn
        <input type='radio' class='check_space' name='sorts' value='first' " . $sort_first . "/> Sorter etter fornavn
        <input type='submit' class='check_space' name='update' value='Oppdater visning' />
        <input type='submit' class='check_space' name='act' value='Vis dugnadskalenderen' /></form>
    </p>

        <form method='post' action='index.php'>" . $hidden . "
        \n";


    /* CREATING THE ITEM LINES
    ---------------------------------------------------------------------- */

    $c = 0;
    $result = @run_query($query . $sort_query);

    $content .= "<div class='row_explained'><div class='name_narrow'>Beboerens navn</div><div class='when_narrow'>Dugnadstatus</div><div class='note'>Notater</div><div class='spacer'>&nbsp;</div></div>";

    while ($row = @mysql_fetch_array($result)) {
        $content .= "\n\n" . show_vedlikehold_person($row["id"], $c++);
    }

    list($antall, $dato) = count_dugnad_barn();

    return $content . $admin_buttons . "</form>
    <h1>Vanlig dugnad</h1>
    <p>" . $antall . " beboere har ordin&aelig;r dugnad " . $dato . ".</p>";
}


/* ******************************************************************************************** *
 *  update_full_list()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function update_full_list()
{
    global $formdata;

    if (valid_admin_login()) {
        $content  = "<h1>Fullstendig dugnadsliste</h1>";
        $content .= "<p><input type='radiobox' name='sort' value='last' " . (!empty($formdata["sort"]) && !strcmp($formdata["sort"], "last") ? "checked='checked' " : null) . "/> Sorter etter etternavn<br />\n";
        $content .= "<input type='radiobox' name='sort' value='first' " . (!empty($formdata["sort"]) && !strcmp($formdata["sort"], "first") ? "checked='checked' " : null) . "/> Sorter etter fornavn<br />\n";
        $content .= "<input type='radiobox' name='sort' value='date' " . (!empty($formdata["sort"]) && !strcmp($formdata["sort"], "date") ? "checked='checked' " : null) . "/> Sorter listen etter dato</p>\n";

        return $content;
    } else {
        return "<p class='failure'>Du har ikke tastet inn korrekt passord.</p>";
    }
}


/* ******************************************************************************************** *
 *  get_beboer_select($new_dugnadsleder = false)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_beboer_select($new_dugnadsleder = false)
{
    global $formdata;

    $content .= "\n<select size='1' name='beboer'>\n";
    $content .= "<option value='-1' >" . ($new_dugnadsleder ? "Velg ny dugnadsleder..." : "Hvem er du?") . "</option>\n";

    $query = "SELECT beboer_for, beboer_etter, beboer_id AS id
                FROM bs_beboer
                ORDER BY beboer_for, beboer_etter";

    $result = @run_query($query);

    while ($row = @mysql_fetch_array($result)) {
        if (!strcmp($formdata["beboer"], $row["id"])) {
            $selected = "selected='selected' ";
        } else {
            $selected = null;
        }

        $content .= "<option value='" . $row["id"] . "' " . $selected . ">" . get_public_lastname($row["beboer_etter"], $row["beboer_for"], false) . "</option>\n";
    }

    $content .= "</select>\n";

    return $content;
}


/* ******************************************************************************************** *
 *  get_vedlikehold_beboer_select()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_vedlikehold_beboer_select()
{
    global $formdata;

    $content .= "\n<select size='1' name='beboer'>\n";
    $content .= "<option value='-1' >Velg beboer fra listen</option>\n";

    $query = "SELECT beboer_for, beboer_etter, beboer_id AS id
                FROM bs_beboer
                    WHERE beboer_spesial = '0' " .
        (isset($formdata["sorts"]) && !strcmp($formdata["sorts"], "first") ?
            "ORDER BY beboer_for, beboer_etter" :
            "ORDER BY beboer_etter, beboer_for");

    $result = @run_query($query);

    while ($row = @mysql_fetch_array($result)) {
        if (isset($formdata["sorts"]) && !strcmp($formdata["sorts"], "first")) {
            $beboer_name = $row["beboer_for"] . " " . $row["beboer_etter"];
        } else {
            $beboer_name = $row["beboer_etter"] . ", " . $row["beboer_for"];
        }

        $content .= "<option value='" . $row["id"] . "' >" . $beboer_name . "</option>\n";
    }

    $content .= "</select>\n";

    return $content;
}


/* ******************************************************************************************** *
 *  utf8_substr($str, $start)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function utf8_substr($str, $start)
{
    preg_match_all("/./su", $str, $ar);

    if (func_num_args() >= 3) {
        $end = func_get_arg(2);
        return join("", array_slice($ar[0], $start, $end));
    } else {
        return join("", array_slice($ar[0], $start));
    }
}


function file_to_array($filename)
{
    global $path_final, $using_layout;

    $using_layout = $filename;
    $buffer = file_get_contents($path_final . $filename);

    // Returns an array with content and the entire tag
    // in the order they were found in $filename.

    $out = preg_split('(\[([a-zA-Z_]+?)\])', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);

    $final = array();
    $final["head"] = $out[0];

    for ($c = 1; $c < sizeof($out); $c = $c + 2) {
        $final[strtolower($out[$c])] = $out[$c + 1];
    }

    return $final;
}


/* ******************************************************************************************** *
 *  valid_login()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function valid_login()
{
    global $formdata;

    $valid_login = valid_admin_login();

    if ($valid_login == 1 || $valid_login == 2) {
        // This is admin overrun
        // Added so that admin can login using Admin or Superuser password
        return 1;
    } elseif (!strcmp($formdata["beboer"], "-1")) {
        // User has not selected a valid beboer from the drop down list
        return -2;
    } elseif (empty($formdata["pw"])) {
        // Password is missing
        return -1;
    } else {
        // Password has been entered and a use selected from the drop down box
        $query = "SELECT beboer_id, beboer_passord
                    FROM bs_beboer
                    WHERE beboer_id = '" . $formdata["beboer"] . "'
                    LIMIT 1";
        $result = @run_query($query);

        $row = @mysql_fetch_array($result);

        if (isset($formdata["pw"]) && !strcmp($row["beboer_passord"], $formdata["pw"])) {
            // VALID LOGIN
            increase_normal_login();
            return 1;
        } else {
            // INVALID LOGIN
            return 0;
        }
    }
}


/* ******************************************************************************************** *
 *  valid_admin_login()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function valid_admin_login()
{
    return check_is_admin() ? 1 : false;

    /*
    global $formdata;

    $visitor_ip = getenv("REMOTE_ADDR");

    if(!strcmp($formdata["pw"], get_innstilling("pw_dugnadsleder")) )
    {
        /* SUPERUSER login - This is used by the dugnadsledere
         * Defined at the top of this page
        --------------------------------------------------------------------- */

    /* REMOVED: No need for this info any more
        $query = "INSERT INTO bs_admin_access  (admin_access_ip, admin_access_date, admin_access_success)
                    VALUES ('". $visitor_ip ."', NOW(), '1')";

        @run_query($query);
        *-/

        return 1;
    }

    /* THIS PASSWORD IS WHAT THE BS ADMINISTRATION IS USING ALL THE TIME TO LOGIN
     * TO CHANGE LOCATE THE DEFINE AT THE TOP OF THIS PAGE
     * ---------------------------------------------------------------------- *-/

    elseif(!strcmp($formdata["pw"], get_innstilling("pw_administrasjon")) )
    {
        /* Return true to prevent login / Useful for when I am editing code
         * Defined at the top of this page
        --------------------------------------------------------------------------------
        return false; *-/

        $query = "INSERT INTO bs_admin_access  (admin_access_ip, admin_access_date, admin_access_success)
                    VALUES ('". $visitor_ip ."', NOW(), '2')";

        @run_query($query);

        return 2;
    }

    /* THIS METHOD WILL GET THE POTENTIALLY AVAILABLE PASSWORD FROM THE DB
     * THIS IS USED BY THE Ryddevaktsjef, Vaktgruppa AND Festforeningen
     * ---------------------------------------------------------------------- *-/

    elseif(!strcmp($formdata["pw"], get_innstilling("pw_undergruppe")))
    {
        /* Return true to prevent login / Useful for when I am editing code
        --------------------------------------------------------------------------------
        return false; *-/

        $query = "INSERT INTO bs_admin_access  (admin_access_ip, admin_access_date, admin_access_success)
                    VALUES ('". $visitor_ip ."', NOW(), '3')";

        @run_query($query);

        if(get_innstilling("open_season", "1"))
        {
            return 3;
        }
        else
        {
            return 0;
        }
    }

    else
    {
        $query = "SELECT 1 FROM bs_innstillinger WHERE innstillinger_felt LIKE 'pw_dugnadsleder' LIMIT 1";
        $result = run_query($query);

        if(mysql_num_rows($result))
        {
            return false;
        }
        else
        {
            if(!strcmp($formdata["pw"], SUPERUSER) )
            {
                return 1;
            }
        }
    }

    // Reaching this means login was invalid
    return false;
     */
}


/* ******************************************************************************************** *
 *  increase_normal_login()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function increase_normal_login()
{
    $visitor_ip = getenv("REMOTE_ADDR");

    $query = "INSERT INTO bs_admin_access  (admin_access_ip, admin_access_date, admin_access_success)
                VALUES ('" . $visitor_ip . "', NOW(), '0')";

    @run_query($query);
}


/* ******************************************************************************************** *
 *  get_beboer_name($id, $show_full = false)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_beboer_name($id, $show_full = false)
{
    $query = "SELECT beboer_for, beboer_etter
                FROM bs_beboer
                WHERE beboer_id = '" . $id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result)) {
        $row = @mysql_fetch_array($result);
        return get_public_lastname($row["beboer_etter"], $row["beboer_for"], false, $show_full);
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  find_bot($beboer, $dugnad)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function find_bot($beboer, $dugnad)
{
    $query = "SELECT deltager_id, bot_id AS bot
                FROM bs_deltager

                LEFT JOIN bs_bot
                    ON bot_deltager = deltager_id

                WHERE deltager_beboer = '" . $beboer . "'
                    AND deltager_dugnad = '" . $dugnad . "'

                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);

        if (empty($row["bot"])) {
            return false;
        } else {
            return $row["bot"];
        }
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_deltager_id($beboer, $dugnad)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_deltager_id($beboer, $dugnad)
{
    $query = "SELECT deltager_id AS id
                FROM bs_deltager

                WHERE deltager_beboer = '" . $beboer . "'
                    AND deltager_dugnad = '" . $dugnad . "'

                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        return $row["id"];
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  make_bot($deltager_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function make_bot($deltager_id)
{
    $query = "INSERT INTO bs_bot (bot_deltager)
                VALUES ('" . $deltager_id . "')";

    @run_query($query);
}


/* ******************************************************************************************** *
 *  get_dugnad_date($dugnad_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_dugnad_date($dugnad_id)
{
    $query = "SELECT dugnad_dato AS dato
                FROM bs_dugnad
                WHERE dugnad_id = '" . $dugnad_id . "'
                LIMIT 1";
    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        return get_simple_date($row["dato"]);
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  new_notat($beboer_id, $notat, $type = 0)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function new_notat($beboer_id, $notat, $type = 0)
{
    if (!get_notat($beboer_id, $notat)) {
        $query = "INSERT INTO bs_notat (notat_beboer, notat_txt, notat_mottaker)
                    VALUES ('" . $beboer_id . "', '" . $notat . "', '" . $type . "')";

        @run_query($query);
    }
}


/* ******************************************************************************************** *
 *  delete_notat($beboer_id, $notat)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function delete_notat($beboer_id, $notat)
{
    $query = "DELETE FROM bs_notat WHERE notat_beboer = '" . $beboer_id . "'
                AND notat_txt = '" . $notat . "'";

    @run_query($query);
}


/* ******************************************************************************************** *
 *  get_notat($beboer_id, $notat)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_notat($beboer_id, $notat)
{
    $query = "SELECT notat_txt AS notat
                FROM bs_notat
                WHERE notat_beboer = '" . $beboer_id . "'
                    AND notat_txt = '" . $notat . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        return $row["notat"];
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_next_dugnad_id($offset = 0)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_next_dugnad_id($offset = 0)
{
    $query = "SELECT dugnad_id AS id
                FROM bs_dugnad
                WHERE dugnad_dato > DATE_ADD(CURDATE(),INTERVAL " . (12 + $offset) . " DAY)
                    AND dugnad_slettet = '0' AND dugnad_type = 'lordag'
                ORDER BY dugnad_dato
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        return $row["id"];
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  new_smart_dugnad($beboer_id, $dugnad_id, $deltager_gjort)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function new_smart_dugnad($beboer_id, $dugnad_id, $deltager_gjort)
{
    /* Used to make a new straffedugnad
    ------------------------------------------------------------------------------------------------ */

    global $dugnad_is_full;
    $go_to_next = true;

    $query = "SELECT deltager_id
                FROM bs_deltager
                WHERE deltager_notat = 'Straff fra uke " . date("W", make_unixtime($dugnad_id)) . ".'
                    AND deltager_beboer = '" . $beboer_id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 0) {

        $query = "SELECT DISTINCT dugnad_id AS id, dugnad_dato AS dato
                    FROM bs_dugnad, bs_deltager
                    WHERE dugnad_dato > DATE_ADD(CURDATE(),INTERVAL 12 DAY)
                        AND deltager_dugnad = dugnad_id
                        AND dugnad_slettet = '0'
                        AND dugnad_type = 'lordag'
                    ORDER BY dugnad_dato";

        $result = @run_query($query);
        $row_count = @mysql_num_rows($result);

        for ($c = 0; $c < $row_count && $go_to_next; $c++) {
            $row = @mysql_fetch_array($result);

            if (empty($dugnad_is_full[$row["id"]])) {
                /* Dugnad is not full
                ------------------------------------------------ */

                if (!get_deltager_id($beboer_id, $row["id"])) {
                    /* User does not have dugnad on this valid day, end loop
                    ------------------------------------------------------------------ */

                    $go_to_next = false;

                    $type = ($deltager_gjort ? "-1" : "1");
                    insert_dugnad($beboer_id, get_simple_date($row["dato"]), $type, "Straff fra uke " . date("W", make_unixtime($dugnad_id)) . ".");
                }
            }
        }

        if ($go_to_next) {
            /* No new and valid dugnad day was found, making note telling us to move it to the next semester
            ------------------------------------------------------------------------------------------------------------ */
            new_notat($beboer_id, "Dugnad " . get_dugnad_date($dugnad_id) . " utsatt til neste semester (fant ingen ledige dugnader).", "0");
        }
    }
}


/* ******************************************************************************************** *
 *  remove_dugnad($beboer_id, $dugnad_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function remove_dugnad($beboer_id, $dugnad_id)
{

    $query = "SELECT deltager_id AS id
                FROM bs_deltager
                WHERE deltager_notat = 'Straff fra uke " . date("W", make_unixtime($dugnad_id)) . ".'
                    AND deltager_beboer = '" . $beboer_id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);

        $query = "DELETE FROM bs_deltager WHERE deltager_id = '" . $row["id"] . "' AND deltager_beboer = '" . $beboer_id . "'";
        @run_query($query);
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  remove_bot($bot_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function remove_bot($bot_id)
{
    $query = "DELETE FROM bs_bot WHERE bot_id = '" . $bot_id . "'";
    @run_query($query);
}


/* ******************************************************************************************** *
 *  new_dugnad($beboer_id, $dugnad_id, $deltager_gjort)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function new_dugnad($beboer_id, $dugnad_id, $deltager_gjort)
{
    /* check if new dugnad is allready made ...
    --------------------------------------------- */

    $deltager_id = get_deltager_id($beboer_id, $dugnad_id);

    $query = "SELECT deltager_notat
                FROM bs_deltager
                WHERE deltager_notat = '" . $deltager_id . "-" . $deltager_gjort . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 0) {
        /* dugnad not found, making new ...
        ---------------------------------------- */

        new_smart_dugnad($beboer_id, $dugnad_id, $deltager_gjort);
    }
}


/* ******************************************************************************************** *
 *  update_status_on_all($day_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function update_status_on_all($day_id)
{
    global $formdata;

    foreach ($formdata as $key => $value) {
        if (is_numeric($key) && valid_person_id($key, $day_id)) {
            // confirm that user har dugnad at this day

            $barn = $barn + 1;

            $query = "UPDATE bs_deltager SET deltager_gjort = '" . $value . "' WHERE deltager_beboer = '" . $key . "' AND deltager_dugnad = '" . $day_id . "'";
            @run_query($query);

            /* Updating, adding or removing dugnad and bot
            ------------------------------------------------------------ */

            switch ($value) {
                case 1:
                    /* Bot og ny dugnad
                    ----------------------------------- */


                    if (!find_bot($key, $day_id)) {
                        /* bot not found, insert it..
                        ---------------------------------------- */

                        $deltager_id = get_deltager_id($key, $day_id);

                        make_bot($deltager_id);
                    }

                    new_dugnad($key, $day_id, $value);

                    break;

                case 2:
                    /* Kun ny dugnad
                    ----------------------------------- */

                    $bot_id = find_bot($key, $day_id);

                    if ($bot_id) {
                        /* bot found, removing it..
                        ---------------------------------------- */
                        remove_bot($bot_id);
                    }

                    new_dugnad($key, $day_id, $value);

                    break;

                case 3:
                    /* Kun bot
                    ----------------------------------- */

                    if (!find_bot($key, $day_id)) {
                        /* bot not found, insert it..
                        ---------------------------------------- */

                        $deltager_id = get_deltager_id($key, $day_id);
                        make_bot($deltager_id);
                    }

                    remove_dugnad($key, $day_id);

                    break;

                default:
                    /* No bot and no new dugnad
                    ----------------------------------- */

                    $bot_id = find_bot($key, $day_id);

                    if ($bot_id) {
                        /* bot found, removing it..
                        ---------------------------------------- */
                        remove_bot($bot_id);
                    }

                    remove_dugnad($key, $day_id);
                    break;
            }
        }
    }
}


/* ******************************************************************************************** *
 *  valid_person_id($beboer, $day)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function valid_person_id($beboer, $day)
{
    $query = "SELECT deltager_beboer
                FROM bs_deltager
                WHERE deltager_beboer = '" . $beboer . "'
                    AND deltager_dugnad = '" . $day . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        return true;
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  verify_person_id($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function verify_person_id($beboer_id)
{
    $query = "SELECT beboer_id
                FROM bs_beboer
                WHERE beboer_id = '" . $beboer_id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        return true;
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_straff_count($dugnad_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_straff_count($dugnad_id)
{
    $query = "SELECT COUNT(deltager_id) AS straffs
                FROM bs_deltager, bs_dugnad
                WHERE dugnad_id =  '" . $dugnad_id . "'
                    AND deltager_gjort > 0
                    AND deltager_dugnad = dugnad_id";

    $result = @run_query($query);

    if (@mysql_num_rows($result) > 0) {
        $row = @mysql_fetch_array($result);
        return $row["straffs"];
    } else {
        return 0;
    }
}


/* ******************************************************************************************** *
 *  get_bot_count()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_bot_count()
{
    $query = "SELECT 1
                FROM bs_bot
                WHERE bot_deltager = 0
                    AND bot_beboer <> 0
                    AND bot_registrert = 0";

    $result = @run_query($query);
    $annuleringer = @mysql_num_rows($result);

    $query = "SELECT 1
                FROM bs_bot, bs_deltager, bs_dugnad
                WHERE bot_registrert = '0'
                    AND bot_deltager = deltager_id
                    AND deltager_dugnad = dugnad_id
                    AND dugnad_checked = '1'";

    $result = @run_query($query);

    return (@mysql_num_rows($result) + $annuleringer);
}


/* ******************************************************************************************** *
 *  get_room_id($room_nr, $room_type)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_room_id($room_nr, $room_type)
{
    $query = "SELECT rom_id
                FROM bs_rom
                WHERE rom_nr = '" . $room_nr . "'
                    AND rom_type = '" . $room_type . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {

        $row = @mysql_fetch_array($result);
        return $row["rom_id"];
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_beboer_room_id($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_beboer_room_id($beboer_id)
{
    $query = "SELECT rom_id
                FROM bs_rom, bs_beboer
                WHERE beboer_id = '" . $beboer_id . "'
                    AND beboer_rom = rom_id
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        return $row["rom_id"];
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  confirm_room_id($room_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function confirm_room_id($room_id)
{
    $query = "SELECT rom_id
                FROM bs_rom
                WHERE rom_id = '" . $room_id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        return true;
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  insert_room($room_nr, $room_type)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function insert_room($room_nr, $room_type)
{
    $room_id = get_room_id($room_nr, $room_type);

    if (empty($room_id)) {
        $query = "INSERT INTO bs_rom (rom_nr, rom_type)
                    VALUES ('" . $room_nr . "', '" . $room_type . "')";

        @run_query($query);
        return @mysql_insert_id();
    } else {
        return $room_id;
    }
}


/* ******************************************************************************************** *
 *  attach_room($beboer_id, $room_nr, $room_type)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function attach_room($beboer_id, $room_nr, $room_type)
{
    if (!empty($room_nr)) {
        $room_id = insert_room($room_nr, $room_type);

        $query = "UPDATE bs_beboer SET beboer_rom = '" . $room_id . "' WHERE beboer_id = '" . $beboer_id . "'";

        @run_query($query);
    }
}


/* ******************************************************************************************** *
 *  update_beboer_room($beboer_id, $room_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function update_beboer_room($beboer_id, $room_id)
{

    if (confirm_room_id($room_id) && verify_person_id($beboer_id)) {
        $query = "UPDATE bs_beboer SET beboer_rom = '" . $room_id . "' WHERE beboer_id = '" . $beboer_id . "'";
        @run_query($query);
    }
}


/* ******************************************************************************************** *
 *  get_room_select($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_room_select($beboer_id)
{
    $query = "SELECT rom_id, rom_nr, rom_type
                FROM bs_rom
                ORDER BY rom_nr";

    $result = @run_query($query);
    $the_rom = get_beboer_room_id($beboer_id);

    $select = "<select name='room'><option value='-1'>Velg</option>\n";

    while ($row = @mysql_fetch_array($result)) {
        if (!strcmp($row["rom_id"], $the_rom)) {
            $selected = "selected='selected' ";
        } else {
            $selected = null;
        }

        $select .= "<option value='" . $row["rom_id"] . "' " . $selected . ">" . $row["rom_nr"] . $row["rom_type"] . "</option>\n";
    }

    $select .= "</select>\n\n";
    return $select;
}


/* ******************************************************************************************** *
 *  status_of_dugnad($dugnad_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function status_of_dugnad($dugnad_id)
{

    $query = "SELECT dugnad_checked AS done
                FROM bs_dugnad
                WHERE dugnad_id = '" . $dugnad_id . "'";

    $result = @run_query($query);

    if (@mysql_num_rows($result)) {
        $row = @mysql_fetch_array($result);
        return $row["done"];
    } else {
        return false;
    }
}


/* ******************************************************************************************** *
 *  get_innstilling($felt, $value = null)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_innstilling($felt, $value = null)
{
    /* Grabs and returns the $felt values returned by the query .. */

    $query = "SELECT innstillinger_verdi AS value
                FROM bs_innstillinger
                WHERE innstillinger_felt = '" . $felt . "'
                " . (isset($value) ? "AND innstillinger_verdi = '" . $value . "'" : null) . "
                ORDER BY innstillinger_verdi";

    $result = @run_query($query);

    if (isset($value)) {
        // Return true/false:
        if (@mysql_num_rows($result) >= 1) {
            // One or more innstillings were found, return true
            return true;
        } else {
            // No innstilling was found, return false
            return false;
        }
    } else {
        // Return value
        list($value) = @mysql_fetch_row($result);
        return $value;
    }
}


/* ******************************************************************************************** *
 *  set_innstilling($felt, $value)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function set_innstilling($felt, $value)
{
    /* Grabs and returns the $felt values returned by the query .. */

    $query = "UPDATE bs_innstillinger
                    SET innstillinger_verdi = '" . $value . "'
                    WHERE innstillinger_felt = '" . $felt . "'";

    @run_query($query);
    return @mysql_errno();
}


/* ******************************************************************************************** *
 *  get_result($felt, $value = null)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_result($felt, $value = null)
{
    /* Grabs and returns the $felt values returned by the query .. */

    $query = "SELECT innstillinger_verdi AS value
                FROM bs_innstillinger
                WHERE innstillinger_felt = '" . $felt . "'
                " . (isset($value) ? "AND innstillinger_verdi = '" . $value . "'" : null) . "
                ORDER BY innstillinger_verdi";

    $result = @run_query($query);
    return $result;
}


/* ******************************************************************************************** *
 *  set_password($new_pw)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function set_password($new_pw, $field)
{
    /* Inserts or updates the Admin password .. */

    $result = get_result("pw_" . $field);

    if (@mysql_num_rows($result) == 1) {
        /* Password is set - update value: */

        $query = "UPDATE bs_innstillinger
                    SET innstillinger_verdi = '" . $new_pw . "'
                    WHERE innstillinger_felt = 'pw_" . $field . "'";

        @run_query($query);
        return @mysql_errno();
    } else {
        /* Password is not set - insert value: */

        $query = "INSERT INTO bs_innstillinger (innstillinger_felt, innstillinger_verdi)
                    VALUES ('pw_" . $field . "', '" . $new_pw . "')";

        @run_query($query);
        return @mysql_errno();
    }
}


/* ******************************************************************************************** *
 *  delete_dugnadsleder($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function delete_dugnadsleder($beboer_id)
{
    $result = get_result("dugnadsleder", $beboer_id);

    if (@mysql_num_rows($result) >= 1) {
        $query = "DELETE FROM bs_innstillinger
                    WHERE innstillinger_felt = 'dugnadsleder'
                    AND innstillinger_verdi = '" . $beboer_id . "'";

        @run_query($query);
    }

    return @mysql_errno();
}


/* ******************************************************************************************** *
 *  set_dugnadsleder($beboer_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function set_dugnadsleder($beboer_id)
{
    /* Inserts or updates the Admin password .. */

    $result = get_result("dugnadsleder", $beboer_id);

    if (@mysql_num_rows($result) == 0) {
        /* Adding new dugnadsleder: */

        $query = "INSERT INTO bs_innstillinger (innstillinger_felt, innstillinger_verdi)
                    VALUES ('dugnadsleder', '" . $beboer_id . "')";

        @run_query($query);
        return @mysql_errno();
    }
}


/* ******************************************************************************************** *
 *  get_beboerid_name($id, $fullname = true, $lastname = false)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_beboerid_name($id, $fullname = true, $lastname = false)
{
    $query = "SELECT
                        beboer_for        AS first,
                        beboer_etter    AS last

                FROM bs_beboer

                WHERE beboer_id = '" . $id . "'
                ORDER BY beboer_for, beboer_etter";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);

        if ($lastname) {
            return $row["last"];
        } else {
            return $row["first"] . ($fullname ? " " . $row["last"] : null);
        }
    } else {
        $return = "Denne beboeren er er ikke registrert i databasen. ";

        $query = "DELETE FROM bs_innstillinger
                    WHERE innstillinger_felt = 'dugnadsleder'
                    AND innstillinger_verdi = '" . $id . "'";

        @run_query($query);

        if (@mysql_errno() == 0) {
            $return .= "Dugnadslederen slettes. ";
        } else {
            $return .= "Det m&aring; tas en db-opprydding asap. ";
        }

        return $return;
    }
}


/* ******************************************************************************************** *
 * get_special_status_image ( int, int, boolean)
 * --------------------------------------------------------------------------------------------
 *
 * Returns   : html code for the image used on the dugnadsliste, if the person has a special status
 *
 * Parameters: $id is beboer_id
 *               $line_count is to make sure the correct background color is used (as they vary)
                $show_ff_details shows all info needed to call beboer to buy things from FF
 *
 * Used by: show_person($id, $line_count, $admin = false)
 *
 * ============================================================================================ */

function get_special_status_image($id, $line_count, $show_ff_details = false)
{
    global $isSubFolder;

    $query = "SELECT
                        beboer_spesial AS spesial

                FROM bs_beboer

                WHERE beboer_id = '" . $id . "'
                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        $row = @mysql_fetch_array($result);
        $dugnads = "";

        /* Elefant business ... */

        if ((int) $row["spesial"] == 2) {
            $dugnads .= "<img src='" . $isSubFolder . "./images/elephant" . ($line_count % 2 ? "_odd" : null) . ".gif' title='Elefanter har dugnadsfri' border='0' align='top'>";
        }

        /* Festforening FF business ... */

        if ((int) $row["spesial"] == 4) {
            $dugnads .= "<img src='" . $isSubFolder . "./images/festforeningen" . ($line_count % 2 ? "_odd" : null) . ".gif' title='Festforeningen har dugnadsfri' border='0' align='top'>";

            if ($show_ff_details) {
                if (SHOW_BUATELEFON) {
                    $dugnads .= "<b>Buatelefon</b>: " . get_innstilling("buatelefon") . " ";
                }
            }
        }

        /* Dugnadsfri business ... */ elseif ((int) $row["spesial"] == 6) {
            $dugnads .= "<img src='" . $isSubFolder . "./images/dugnadsfri" . ($line_count % 2 ? "_odd" : null) . ".gif' width='24px' height='24px' title='Denne beboeren har dugnadsfri' border='0' align='top'>";
        }

        /* Dugnadsfri business ... */ elseif ((int) $row["spesial"] == 8) {
            $dugnads .= "<img class='dugnads_status' src='" . $isSubFolder . "./images/blivende" . ($line_count % 2 ? "_odd" : null) . ".gif' width='32px' height='20px' title='Denne beboeren skal kun ha en dugnad' border='0' align='top'>";
        }

        return $dugnads;
    } else {
        return null;
    }
}


/* ******************************************************************************************** *
 * update_blivende_elephants ( void )
 * --------------------------------------------------------------------------------------------
 *
 * Returns   : the number of beboere that just became elefant
 *
 *
 * Used by:
 *
 * ============================================================================================ */

function update_blivende_elephants()
{
    global $formdata;


    $blivende = 0;

    $month    = date("m", time());
    $day    = date("d", time());

    if ($month > 7) {
        /* We now know we are in the autumn semester
        -------------------------------------------------------- */

        if ($month == 10 && $day > 15 && $day < 22) {
            $query = "SELECT beboer_id FROM bs_beboer, bs_deltager WHERE beboer_spesial = '8' AND deltager_beboer = beboer_id";
            $result = @run_query($query);

            while (list($beboer_id) = @mysql_fetch_row($result)) {
                // Remove dugnads from this person
                $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $beboer_id . "'";
                @run_query($query);
            }

            $query = "UPDATE bs_beboer SET beboer_spesial = '2' WHERE beboer_spesial = '8'";
            @run_query($query);
            $blivende++;
        }
    } else {
        /* .. Spring
        -------------------------------------------------------- */

        if ($month == 3 && $day > 15 && $day < 22) {
            $query = "SELECT beboer_id FROM bs_beboer, bs_deltager WHERE beboer_spesial = '8' AND deltager_beboer = beboer_id";
            $result = @run_query($query);

            while (list($beboer_id) = @mysql_fetch_row($result)) {
                // Remove dugnads from this person
                $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $beboer_id . "'";
                @run_query($query);
            }

            $query = "UPDATE bs_beboer SET beboer_spesial = '2' WHERE beboer_spesial = '8'";
            @run_query($query);
            $blivende++;
        }
    }

    return $blivende;
}


/* ******************************************************************************************** *
 * get_dugnadsledere ( void )
 * --------------------------------------------------------------------------------------------
 *
 * Returns   : returns the name of the dugnadsledere
 *
 *
 * Used by:
 *
 * ============================================================================================ */

function get_dugnadsledere()
{
    $query = "SELECT beboer_for, beboer_etter, rom_nr, rom_type, rom_tlf
                FROM bs_beboer, bs_rom, bs_innstillinger
                WHERE innstillinger_felt = 'dugnadsleder' AND
                    beboer_id = innstillinger_verdi AND
                    beboer_rom = rom_id";

    $result = @run_query($query);

    if (@mysql_num_rows($result)) {
        while ($row = @mysql_fetch_array($result)) {
            $tlf = "";
            if ($row['beboer_for'] == "Karl-Martin" && $row['beboer_etter'] == "Svastuen") $tlf = " - 971 5 9 266";
            if ($row['beboer_for'] == "Theodor Tinius" && $row['beboer_etter'] == "Tronerud") $tlf = " - 400 41 458";
            $names .= "<i>" . $row["beboer_for"] . " " . $row["beboer_etter"] . "</i> (" . $row["rom_nr"] . $row["rom_type"] . " #" . $row["rom_tlf"] . $tlf . ")<br />";
        }
    } else {
        $names = "Dugnadslederne";
    }
    return $names;
}


/* ******************************************************************************************** *
 *  output_default_frontpage()
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function output_default_frontpage()
{
    $page_array = file_to_array("./layout/menu_main.html");

    $page_array["gutta"] = get_dugnadsledere() . $page_array["gutta"];
    $page_array["beboer"] = get_beboer_select() . $page_array["beboer"];

    $page_array["db_error"] = database_health() . $page_array["db_error"];

    return implode($page_array);
}


/* ******************************************************************************************** *
 *  redirect($to)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function redirect($to)
{
    global $path_web;

    if (headers_sent()) {
        return false;
    } else {
        header("HTTP/1.1 303 See Other");
        header("Location: $to");
        exit();
    }
}


/* ******************************************************************************************** *
 *  get_beboer_count($dugnad_id)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function get_beboer_count($dugnad_id)
{
    $query = "SELECT DISTINCT dugnad_id AS id, COUNT(deltager_id) AS antall
                FROM bs_dugnad

                    LEFT JOIN bs_deltager
                        ON dugnad_id = deltager_dugnad

                WHERE dugnad_slettet = '0'
                    AND dugnad_id = '" . $dugnad_id . "'
                GROUP BY dugnad_id";

    $result = @run_query($query);
    list($dugnad_id, $antall_deltagere) = @mysql_fetch_row($result);

    return $antall_deltagere;
}


/* ******************************************************************************************** *
 *  forceNewDugnads($beboer_id, $forceCount, $perDugnad, $note = null)
 * --------------------------------------------------------------------------------------------
 *
 * ============================================================================================ */

function forceNewDugnads($beboer_id, $forceCount, $perDugnad, $note = null)
{
    $query = "SELECT DISTINCT dugnad_id AS id, COUNT(deltager_id) AS antall
                FROM bs_dugnad

                    LEFT JOIN bs_deltager
                        ON dugnad_id = deltager_dugnad

                WHERE dugnad_slettet = '0'
                    AND dugnad_dato > NOW()
                GROUP BY dugnad_id";

    $result = @run_query($query);

    $skip_a_week = false;
    $added = 0;

    // Going through all dugnads; grouped by id with a count of the number
    // of beboers added..

    while (list($dugnad_id, $antall_deltagere) = @mysql_fetch_row($result)) {
        // We keep adding to this date as long as the added count of beboere
        // is less than the required amount..

        if ($antall_deltagere < $perDugnad) {
            if (!$skip_a_week) {
                if (insert_dugnad_using_id($beboer_id, $dugnad_id, 1, $note)) {
                    // As we do not want a beboer to have to
                    // adjacent week ends for dugnad, we skip a dugnad...

                    $skip_a_week = true;
                    $added += 1;
                }
            } else {
                // Now as we have skippe done, we set it to false to avoid
                // skipping again..

                $skip_a_week = false;
            }

            if (!($forceCount - $added)) {
                // We have now added all we need, so we return to count added..
                return $forceCount;
            }
        }
    }

    // Arriving here means the beboer did not have all the required dugnads added..
    // So we increase the max count per dugnad, and re-force the remaining dugnads.
    if ($forceCount - $added) {
        return forceNewDugnads($beboer_id, ($forceCount - $added), ($perDugnad + 1));
    }

    // Now as we have added all required dugnads for this beboer,
    // we return the number added..
    return $forceCount;
}



/* ******************************************************************************************** *
 * get_buatelefon ( void )
 * --------------------------------------------------------------------------------------------
 *
 * Returns   : Queries the bs_innstillinger for "buatelefon" and returns the phone number
 *
 * Used by: show_person($id, $line_count, $admin = false)
 *
 * ============================================================================================ */

function get_buatelefon()
{
    $query = "SELECT innstillinger_verdi FROM bs_innstillinger WHERE innstillinger_felt = 'buatelefon' LIMIT 1";
    $result = @run_query($query);

    list($buatelefon) = @mysql_fetch_row($result);
    return $buatelefon;
}

/* ******************************************************************************************** *
 * update_buatelefon ( String )
 * --------------------------------------------------------------------------------------------
 *
 * Returns: Updates the buatelefon with a new number
 *
 * Params : $new_buatelefon The new number formatted as a string
 *
 * Used by: show_person($id, $line_count, $admin = false)
 *
 * ============================================================================================ */

function update_buatelefon($new_buatelefon)
{
    $query = "UPDATE bs_innstillinger SET innstillinger_verdi = '" . $new_buatelefon . "' WHERE innstillinger_felt = 'buatelefon'";
    @run_query($query);

    print @mysql_error();
    return @mysql_affected_rows();
}


/* ******************************************************************************************** *
 * get_all_barn ( String )
 * --------------------------------------------------------------------------------------------
 *
 * Returns: A html-formattes list of all kids having dugnad
 *
 * Params : $new_buatelefon The new number formatted as a string
 *
 * Used by: output_vedlikehold_list()
 *
 * ============================================================================================ */

function get_all_barn()
{
    global $formdata;

    $query = "SELECT dugnad_id
                FROM bs_dugnad
                WHERE dugnad_dato > CURDATE() AND (TO_DAYS(dugnad_dato) - TO_DAYS(NOW()) <= 7)
                    AND dugnad_slettet = '0'
                ORDER BY dugnad_dato";

    $result = @run_query($query);
    if (@mysql_num_rows($result)) {
        list($dugnad_id) = @mysql_fetch_row($result);

        $show_expired_days = false;
        $editable = false;
        $dugnadsliste_full_name = true;
        $supress_header = true;

        return  show_day($dugnad_id, $show_expired_days, $editable, $dugnadsliste_full_name, $supress_header);
    } else {
        return "<p>Ingen dugnadsdeltagere er satt opp til helgen.</p>";
    }
}


/* ******************************************************************************************** *
 * rounded_feedback_box ( color_string, text_string)
 * --------------------------------------------------------------------------------------------
 *
 * Simple feedback to the user.
 *
 * Returns: A html-formatted message, with a rounded colored box and white text.
 *
 * Used by: div. cases
 *
 * color: green, red
 * text: html formated text
 *
 * ============================================================================================ */

function rounded_feedback_box($color, $html_txt)
{
    return "\n\t\t\t\t\t<div class='bl_" . $color  . "'>
                <div class='br_" . $color  . "'>
                    <div class='tl_" . $color  . "'>
                        <div class='tr_" . $color  . "'>
                            <p class=\"white_message\">" . $html_txt . "</p>
                        </div>
                    </div>
                </div>
            </div>\n
            <p>&nbsp;</p>";
}


/* ******************************************************************************************** *
 * database_health ()
 * --------------------------------------------------------------------------------------------
 *
 * Simple feedback iff the database is offline.
 *
 * Returns: A html-formatted message, with a rounded red box and white text explaining that the
 *          database is offline.
 *
 * Used by: output_vedlikehold_list()
 *
 * ============================================================================================ */

function database_health()
{
    $query = "SELECT 1 FROM bs_rom LIMIT 1";
    $result = @run_query($query);

    if (@mysql_num_rows($result) == 0) {

        return "<p>&nbsp;</p>\n\t\t\t\t\t<div class='bl_red'>
                        <div class='br_red'>
                            <div class='tl_red'>
                                <div class='tr_red'>
                                    Beklager, databasen er for &oslash;yeblikket ikke tilgjengelig, vennligst kom tilbake senere.
                                </div>
                            </div>
                        </div>
                    </div>\n";
    }
}


/* ******************************************************************************************** *
 * truncateAllowed ()
 * --------------------------------------------------------------------------------------------
 *
 * Used to check that the dugnadsperiode has not started, and if so will not allow any truncate
 * of any data tables unless in developer mode.
 *
 * Returns: true if DEVELOPER_MODE == true
 *            false if DEVELOPER_MODE == false && dugnadsperiode has started
 *
 * Params : $future_check == false - will check if any dugnads has been carried out already
            $future_check = true - will check if any dugnads remains this semester
 *
 * Used by: all functions that TRUNCATEs any data tables
 *
 * ============================================================================================ */

function truncateAllowed($future_check = false)
{
    if (DEVELOPER_MODE) {
        return true;
    } else {
        // Do we have any registered and valid dugnads at all?
        // This is to prevent being refused to change the dugnadsliste
        // after deleting all beboere, but before adding any valid dugnads

        $query =   "SELECT
                        deltager_id

                    FROM
                        bs_deltager,
                        bs_dugnad,
                        bs_beboer

                    WHERE
                        dugnad_slettet = 0 AND
                        deltager_dugnad = dugnad_id

                    LIMIT 1";

        $result = run_query($query);

        if (mysql_num_rows($result) == 0) {
            // If no valid dugnads because we have only
            // added beboere, but no dugnads yet

            return true;
        }

        // If a dugnad has been arranged, that is not deleted and had some
        // beboere attached - return false!

        if ($future_check == false) {
            $query =   "SELECT
                            deltager_id

                        FROM
                            bs_deltager,
                            bs_dugnad,
                            bs_beboer

                        WHERE
                            dugnad_dato < NOW() AND
                            dugnad_slettet = 0 AND
                            deltager_dugnad = dugnad_id AND
                            deltager_beboer = beboer_id

                        LIMIT 1";
        } else {
            $query =   "SELECT
                            deltager_id

                        FROM
                            bs_deltager,
                            bs_dugnad,
                            bs_beboer

                        WHERE
                            dugnad_dato > NOW() AND
                            dugnad_slettet = 0 AND
                            deltager_dugnad = dugnad_id AND
                            deltager_beboer = beboer_id


                        LIMIT 1";
        }

        $result = @run_query($query);

        if (mysql_num_rows($result)) {
            return false;
        } else {
            return true;
        }
    }
}




function run_query($query)
{
    $result = mysql_query($query);
    if (!$result && DEVELOPER_MODE) {
        die("MySQL spørring feilet: (#" . mysql_errno() . ") " . mysql_error() . " -- Spørringen var: " . $query);
    }

    $GLOBALS['queries'][] = $query;
    return $result;
}


function get_dugnad_count()
{
    static $list = null;

    if (!$list) {
        $list = [];
        $query = "SELECT COUNT(deltager_id) AS antall, deltager_dugnad AS id
                    FROM bs_deltager, bs_dugnad
                    WHERE dugnad_id = deltager_dugnad
                        AND dugnad_slettet = '0'
                    GROUP BY deltager_dugnad";

        $result = @run_query($query);
        while ($row = @mysql_fetch_array($result)) {
            $list[$row['id']] = $row['antall'];
        }
    }

    return $list;
}

function get_dugnad_type_prefix($dugnad)
{
    switch ($dugnad['dugnad_type']) {
        case 'anretning':
            return 'Anretning: ';
    }

    return '';
}
