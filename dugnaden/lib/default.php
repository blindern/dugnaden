<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set("Europe/Oslo");

require_once("auth.php");
require_once("config.php");

require_once __DIR__ . "/../../vendor/autoload.php";

use \Blindern\Dugnaden\Dugnaden;
use \Blindern\Dugnaden\Page;

$link     = mysql_connect($config_database["host"], $config_database["username"], $config_database["password"]);
mysql_set_charset("utf8", $link);
$database = mysql_select_db($config_database["dbname"], $link);


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


function clean_txt($str)
{
    $str = preg_replace('/ +/', ' ', trim($str));
    //$str = ereg_replace (', +', ',', $str);
    $str = preg_replace("/[\r\n]+/", "\r\n", $str);
    $str = preg_replace("/\r\n/", "**", $str);
    return $str;
}


function store_data($txt_data, $split_char = "/", $split_name = ",")
{
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


function delete_note($note_id)
{
    if (confirm_note_id($note_id)) {
        $query = "DELETE FROM bs_notat WHERE notat_id = '" . $note_id . "'";
        @run_query($query);
    }
}


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

function count_dugnad_barn()
{
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


function show_day($formdata, $day, $show_expired_days = false, $editable = false, $dugnadsliste_full_name = false, $supress_header = false)
{
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

        while (list($id, $first, $last, $rom, $type, $when, $done, $kind, $note) = @mysql_fetch_row($result)) {
            if ($show_expired_days) {
                $check_box = get_beboer_selectbox($id, $day);
            }

            $full_name = get_public_lastname($last, $first, true, $dugnadsliste_full_name);

            $entries .= "<div class='row" . ($line_count++ % 2 ? "_odd" : null) . "'>" . $check_box . "<div class='name_narrow'>" . $full_name . "</div>\n<div class='note'>" . get_notes($formdata, $id) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";
        }

        $entries .= "<div class='day_spacer'>&nbsp;</div>";

        return $entries;
    } else {
        return null;
    }
}


function admin_show_day($formdata, $day, $use_dayspacer = true)
{
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
            $full_name = $last . ", " . $first;

            if (strcmp($formdata["admin"], "Dugnadsliste")) {
                /* Showing checkbox only when this is not admin mode..
                ---------------------------------------------------------------- */
                $select_box = get_beboer_selectbox($id, $day);
            } else {
                $select_box = "<div class='checkbox_narrow'><input type='checkbox' name='delete[]' value='" . $id . "_" . $day . "' /></div>";
            }

            $dugnads = admin_get_dugnads($id, $editable) . admin_addremove_dugnad($id, $line_count);

            $entries .= "<div class='row" . ($line_count++ % 2 ? "_odd" : null) . "'>" . $select_box . "<div class='name_narrow'>" . $line_count . ". " . $full_name . "</div>\n<div class='when_narrow'>" . $dugnads . "</div><div class='note'>" . get_notes($formdata, $id, true) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";
        }

        if ($use_dayspacer) {
            $entries .= "<div class='day_spacer'>&nbsp;</div>";
        }

        return $entries;
    } else {
        return null;
    }
}


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


function get_notes($formdata, $id, $admin = false)
{
    $admin_enda = "</a>";

    if ($admin) {
        $show = (!empty($formdata["show"]) ? "&show=" . $formdata["show"] : null);

        if (isset($formdata["next"])) {
            $navigate = "&next=go";
        } elseif (isset($formdata["prev"])) {
            $navigate = "&prev=go";
        }
    }

    $query = "SELECT notat_txt AS the_note, notat_id, notat_mottaker, beboer_passord, rom_nr, rom_type, beboer_for
                FROM bs_beboer

                    LEFT JOIN bs_notat
                        ON notat_beboer = beboer_id

                    LEFT JOIN bs_rom
                        ON rom_id = beboer_rom

                WHERE beboer_id = '" . $id . "'";

    $result = @run_query($query);

    while ($row = @mysql_fetch_array($result)) {
        $admin_starta = "<a href='index.php?do=admin" . $show . $navigate . "&admin=" . $formdata["admin"] . "&deln=" . $row["notat_id"] . "'>";
        $passord =  "\nPassord: " . $row["beboer_passord"];
        $room     =  "\nRom: " . $row["rom_nr"] . $row["romtype"];

        if (!empty($row["the_note"])) {
            $content .= $admin_starta . "<img src='./images/postit" . ((int) $row["notat_mottaker"] == 1 ? "_petter" : null) . ".gif' alt='[note]' title='" . $row["the_note"] . "' class='postit_note' />" . $admin_enda;
        }
    }

    if ($admin == 1 || $admin == 2) {
        $content .= "<a href='index.php?do=admin" . $show . $navigate . "&admin=" . $formdata["admin"] . "&&newn=" . $id . "' ><img src='./images/postitadd.gif' alt='[note]' title='Legg inn nytt notat." . $passord . $room . "' class='postit_note' /></a>";
    } else {
        $content .= $room;
    }

    return $content;
}


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


function update_dugnads($formdata)
{
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

function get_simple_date($complex, $very_simple = false)
{
    $complex = explode("-", substr($complex, 0, 10));

    $simple = $complex[2] . "." . $complex[1] . "." . ($very_simple ? substr($complex[0], -2) : $complex[0]);

    return $simple;
}

function show_vedlikehold_person($formdata, $id, $line_count)
{
    global $formdata;

    $query = "SELECT    beboer_for        AS first,
                        beboer_etter    AS last,
                        rom_nr

                FROM bs_beboer

                    LEFT JOIN bs_rom
                        ON beboer_rom = rom_id

                WHERE beboer_id = '" . $id . "' AND
                        beboer_spesial = '0'

                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        list($first, $last, $rom) = @mysql_fetch_row($result);

        if ($admin) {
            $check_box = "<input type='checkbox' name='delete_person[]' value='" . $id . "'> ";
        }

        $full_name = $last . ", " . $first;

        /* Normal business ... */

        $dugnads = admin_get_dugnads($id, $admin);

        $entries .= "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $check_box . $full_name . (empty($rom) ? " (<b>rom ukjent</b>)" : null) . "</div>\n<div class='when'>" . $dugnads . "</div><div class='note'>" . get_notes($formdata, $id, true) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";

        return $entries;
    } else {
        return "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'>Halvor Gimnes var her!</div>";
    }
}

function show_person($id, $line_count, $admin = false)
{
    global $formdata;

    $query = "SELECT    beboer_for        AS first,
                        beboer_etter    AS last,
                        beboer_spesial    AS spesial,

                        rom_nr

                FROM bs_beboer

                    LEFT JOIN bs_rom
                        ON beboer_rom = rom_id

                WHERE beboer_id = '" . $id . "'

                LIMIT 1";

    $result = @run_query($query);

    if (@mysql_num_rows($result) == 1) {
        list($first, $last, $spesial, $rom) = @mysql_fetch_row($result);

        if ($admin) {
            $check_box = "<input type='checkbox' name='delete_person[]' value='" . $id . "'> ";
        }

        $full_name = get_public_lastname($last, $first, true, $admin);

        /* Outputting a static list of dugnads and status
        --------------------------------------------------------------------- */

        if (!$admin) {
            $dugnads = get_special_status_image($id, $line_count) . get_dugnads($id);
        } else {
            $dugnads = get_special_status_image($id, $line_count) . admin_get_dugnads($id, $admin) . admin_addremove_dugnad($id, $line_count);
        }

        $entries .= "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $check_box . $full_name . (empty($rom) ? " (<b>rom ukjent</b>)" : null) . "</div>\n<div class='when'>" . $dugnads . "</div><div class='note'>" . get_notes($formdata, $id, $admin) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";

        return $entries;
    } else {
        return "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'>Halvor Gimnes var her!</div>";
    }
}

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



/*
 * Returns   : the compelete list for all beboere, showing all dugnads
 *
 * Used by   : BOTH beboere and Admin.
 *
 * Parameters: $admin == 1 enables drop down boxes enabling user to change dates and states
 *               $admin == 2 allows change of date and status
 *               $admin == 3 only allowed to change status
 *               $admin == false disables ALL editing; shows a text based list
 */

function output_full_list($admin = false)
{
    $query = "SELECT beboer_for, beboer_etter, beboer_id AS id
                FROM bs_beboer
                ORDER BY beboer_etter, beboer_for";

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
    $content .= "<form method='post' action='index.php'>" . $hidden . "
        \n";

    /* CREATING THE ITEM LINES
    ---------------------------------------------------------------------- */

    $c = 0;
    $result = @run_query($query);

    $content .= "<div class='row_explained'><div class='name'>" . ($admin == 1 ? "Slett " : null) . "Beboer</div><div class='when_narrow'>Tildelte dugnader</div><div class='note'>Notater</div><div class='spacer'>&nbsp;</div></div>";

    while ($row = @mysql_fetch_array($result)) {
        $content .= "\n\n" . show_person($row["id"], $c++, $admin) . "\n";
    }

    return $content . $admin_buttons . "</form>";
}


function output_vedlikehold_list()
{
    global $formdata;

    $query = "SELECT DISTINCT beboer_id AS id, beboer_for, beboer_etter
                FROM bs_beboer, bs_deltager
                WHERE deltager_beboer = beboer_id
                    AND deltager_dugnad = '-2'
                ORDER BY beboer_etter, beboer_for";

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
    $content .= "<p>
        <a href='index.php?do=admin&admin=Dugnadskalender'>Vis dugnadskalenderen</a>
    </p>

        <form method='post' action='index.php'>" . $hidden . "
        \n";


    /* CREATING THE ITEM LINES
    ---------------------------------------------------------------------- */

    $c = 0;
    $result = @run_query($query);

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

function output_ryddevakt_list()
{
    global $formdata;

    $query = "SELECT DISTINCT beboer_id AS id, beboer_for, beboer_etter
                FROM bs_beboer, bs_deltager
                WHERE deltager_beboer = beboer_id
                    AND deltager_dugnad = '-2'
                ORDER BY beboer_etter, beboer_for";

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
    $content .= "<p>
        <a href='index.php?do=admin&admin=Dugnadskalender'>Vis dugnadskalenderen</a>
    </p>

        <form method='post' action='index.php'>" . $hidden . "
        \n";


    /* CREATING THE ITEM LINES
    ---------------------------------------------------------------------- */

    $c = 0;
    $result = @run_query($query);

    $content .= "<div class='row_explained'><div class='name_narrow'>Beboerens navn</div><div class='when_narrow'>Dugnadstatus</div><div class='note'>Notater</div><div class='spacer'>&nbsp;</div></div>";

    while ($row = @mysql_fetch_array($result)) {
        $content .= "\n\n" . show_vedlikehold_person($row["id"], $c++);
    }

    list($antall, $dato) = count_dugnad_barn();

    return $content . $admin_buttons . "</form>
    <h1>Vanlig dugnad</h1>
    <p>" . $antall . " beboere har ordin&aelig;r dugnad " . $dato . ".</p>";
}


function update_full_list()
{
    global $formdata;

    if (valid_admin_login()) {
        return "<h1>Fullstendig dugnadsliste</h1>";
    } else {
        return "<p class='failure'>Du har ikke tastet inn korrekt passord.</p>";
    }
}


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


function get_vedlikehold_beboer_select()
{
    global $formdata;

    $content .= "\n<select size='1' name='beboer'>\n";
    $content .= "<option value='-1' >Velg beboer fra listen</option>\n";

    $query = "SELECT beboer_for, beboer_etter, beboer_id AS id
                FROM bs_beboer
                WHERE beboer_spesial = '0'
                ORDER BY beboer_etter, beboer_for";

    $result = @run_query($query);

    while ($row = @mysql_fetch_array($result)) {
        $beboer_name = $row["beboer_etter"] . ", " . $row["beboer_for"];
        $content .= "<option value='" . $row["id"] . "' >" . $beboer_name . "</option>\n";
    }

    $content .= "</select>\n";

    return $content;
}


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


function get_layout_content($name)
{
    return file_get_contents(__DIR__ . "/layout/$name.html");
}


function get_layout_parts($name)
{
    $buffer = get_layout_content($name);

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


function valid_admin_login()
{
    // Previously this method returned:
    //   1 for dugnadsleder
    //   2 for administrasjon
    //   3 for ryddevaktsjef, vaktgruppa, festforeningen
    // But only dugnadsleder have been using this for the last years, so
    // support for the others are removed when the SAML auth was set up.
    return check_is_admin() ? 1 : false;
}

function increase_normal_login()
{
    $visitor_ip = getenv("REMOTE_ADDR");

    $query = "INSERT INTO bs_admin_access  (admin_access_ip, admin_access_date, admin_access_success)
                VALUES ('" . $visitor_ip . "', NOW(), '0')";

    @run_query($query);
}

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


function make_bot($deltager_id)
{
    $query = "INSERT INTO bs_bot (bot_deltager)
                VALUES ('" . $deltager_id . "')";

    @run_query($query);
}


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


function new_notat($beboer_id, $notat, $type = 0)
{
    if (!get_notat($beboer_id, $notat)) {
        $query = "INSERT INTO bs_notat (notat_beboer, notat_txt, notat_mottaker)
                    VALUES ('" . $beboer_id . "', '" . $notat . "', '" . $type . "')";

        @run_query($query);
    }
}


function delete_notat($beboer_id, $notat)
{
    $query = "DELETE FROM bs_notat WHERE notat_beboer = '" . $beboer_id . "'
                AND notat_txt = '" . $notat . "'";

    @run_query($query);
}


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

function remove_bot($bot_id)
{
    $query = "DELETE FROM bs_bot WHERE bot_id = '" . $bot_id . "'";
    @run_query($query);
}


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

function attach_room($beboer_id, $room_nr, $room_type)
{
    if (!empty($room_nr)) {
        $room_id = insert_room($room_nr, $room_type);

        $query = "UPDATE bs_beboer SET beboer_rom = '" . $room_id . "' WHERE beboer_id = '" . $beboer_id . "'";

        @run_query($query);
    }
}


function update_beboer_room($beboer_id, $room_id)
{

    if (confirm_room_id($room_id) && verify_person_id($beboer_id)) {
        $query = "UPDATE bs_beboer SET beboer_rom = '" . $room_id . "' WHERE beboer_id = '" . $beboer_id . "'";
        @run_query($query);
    }
}

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


function get_special_status_image($id, $line_count)
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


function get_dugnadsledere()
{
    $query = "SELECT beboer_for, beboer_etter, rom_nr, rom_type
                FROM bs_beboer, bs_rom, bs_innstillinger
                WHERE innstillinger_felt = 'dugnadsleder' AND
                    beboer_id = innstillinger_verdi AND
                    beboer_rom = rom_id";

    $result = @run_query($query);

    if (@mysql_num_rows($result)) {
        while ($row = @mysql_fetch_array($result)) {
            $tlf = "";
            if ($row['beboer_for'] == "Karl-Martin" && $row['beboer_etter'] == "Svastuen") $tlf = " - 971 5 9 266";
            if ($row['beboer_for'] == "Mathias Løland" && $row['beboer_etter'] == "Velle") $tlf = " - 412 14 541";
            $names .= "<i>" . $row["beboer_for"] . " " . $row["beboer_etter"] . "</i> (" . $row["rom_nr"] . $row["rom_type"] . $tlf . ")<br />";
        }
    } else {
        $names = "Dugnadslederne";
    }
    return $names;
}


function output_default_frontpage()
{
    $page_array = get_layout_parts("menu_main");

    $page_array["gutta"] = get_dugnadsledere() . $page_array["gutta"];
    $page_array["beboer"] = get_beboer_select() . $page_array["beboer"];

    $page_array["db_error"] = database_health() . $page_array["db_error"];

    return implode($page_array);
}


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


function get_all_barn($formdata)
{
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

        return  show_day($formdata, $dugnad_id, $show_expired_days, $editable, $dugnadsliste_full_name, $supress_header);
    } else {
        return "<p>Ingen dugnadsdeltagere er satt opp til helgen.</p>";
    }
}


/*
 * Simple feedback to the user.
 *
 * Returns: A html-formatted message, with a rounded colored box and white text.
 *
 * Used by: div. cases
 *
 * color: green, red
 * text: html formated text
 *
 */
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


/*
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
 */

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
