<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set("Europe/Oslo");

require_once("auth.php");
require_once("config.php");

require_once __DIR__ . "/../../vendor/autoload.php";

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Fragments\BeboerSelectFragment;
use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Model\Dugnad;
use Blindern\Dugnaden\Util\Semester;

$link     = mysql_connect($config_database["host"], $config_database["username"], $config_database["password"]);
mysql_set_charset("utf8", $link);
$database = mysql_select_db($config_database["dbname"], $link);


function show_day($formdata, $day, $show_expired_days = false, $editable = false, $dugnadsliste_full_name = false, $supress_header = false)
{
    if (!$show_expired_days) {
        /* To limit days shown - as regular users have no need to see old days...
        -------------------------------------------------------------------------------- */
        $show_expired_days_limit = "AND dugnad_dato >= NOW() ";
    }

    $dugnad = Dugnaden::get()->dugnad->getById($day);

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
            $entries .= "<div class='row_header'><h1>" . $dugnad->getDayHeader() . "</h1></div>\n\n";
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


function smart_create_dugnad(Beboer $beboer)
{
    global $empty_dugnads;

    if (!empty($empty_dugnads)) {
        $empty_dugnad = each($empty_dugnads);
    }

    $dugnad = !empty($empty_dugnad["key"])
        ? Dugnaden::get()->dugnad->getById($empty_dugnad["key"])
        : null;

    if ($dugnad && !Dugnaden::get()->deltager->getByBeboerAndDugnad($beboer, $dugnad)) {
        Dugnaden::get()->deltager->createDugnad($beboer, $dugnad);
        return true;
    }

    $dugnadList = Dugnaden::get()->dugnad->getFutureLoerdagDugnadList();
    foreach ($dugnadList as $dugnad) {
        if ($dugnad->offsetDaysFromToday() < 10) continue;
        if (Dugnaden::get()->deltager->getByBeboerAndDugnad($beboer, $dugnad)) continue;

        Dugnaden::get()->deltager->createDugnad($beboer, $dugnad);
        return true;
    }

    Dugnaden::get()->note->create(
        $beboer,
        "Overf&oslash;re dugnad til " . Semester::getNextSemester()->str() . "."
    );
    return false;
}


function update_dugnads($formdata)
{
    foreach ($formdata as $beboer_combo => $new_dugnad) {
        $splits = explode("_", $beboer_combo);

        // ADMIN WANTS TO ADD, DELETE, CHANGE DUGNAD

        if ($splits[0] === "admin") {
            $beboer = is_numeric($splits[1])
                ? Dugnaden::get()->beboer->getById($splits[1])
                : null;

            if ($beboer && (int) $new_dugnad > 0) {
                for ($c = 0; $c < (int) $new_dugnad; $c++) {
                    smart_create_dugnad($beboer);
                }
            } elseif ($beboer && (int) $new_dugnad == -1) {
                Dugnaden::get()->deltager->deleteAllForBeboer($beboer);
            } elseif ($beboer && (int) $new_dugnad == -2) {
                if (Dugnaden::get()->beboer->setAsElephant($beboer)) {
                    $feedback .= "<div class='success'>En ny elefant vandrer iblant oss!</div>";
                }
            } elseif ($beboer && (int) $new_dugnad == -3) {
                if (Dugnaden::get()->beboer->setAsNormal($beboer)) {
                    $feedback .= "<div class='success'>En elefantfeil har blitt rettet opp...</div>";
                }
            } elseif ($beboer && (int) $new_dugnad == -8) {
                if (Dugnaden::get()->beboer->setAsBlivendeElephant($beboer)) {
                    $feedback .= "<div class='success'>En beboer blir elefant dette semesteret...</div>";
                }
            } elseif ($beboer && (int) $new_dugnad == -9) {
                if (Dugnaden::get()->beboer->setAsNormal($beboer)) {
                    $feedback .= "<div class='success'>En beboer blir ikke elefant dette semestere likevel...</div>";
                }
            } elseif ($beboer && (int) $new_dugnad == -4) {
                if (Dugnaden::get()->beboer->setAsFestforening($beboer)) {
                    $feedback .= "<div class='success'>Blindern&aring;nden lever - en beboer er i festforeningen!</div>";
                }
            } elseif ($beboer && (int) $new_dugnad == -5) {
                if (Dugnaden::get()->beboer->setAsNormal($beboer)) {
                    $feedback .= "<div class='success'>En er ikke lenger med i Festforeningen...</div>";
                }
            } elseif ($beboer && (int) $new_dugnad == -6) {
                if (Dugnaden::get()->beboer->setAsDugnadsfri($beboer)) {
                    $feedback .= "<div class='success'>En ny beboer har n&aring; dugnadsfri...</div>";
                }
            } elseif ($beboer && (int) $new_dugnad == -7) {
                if (Dugnaden::get()->beboer->setAsNormal($beboer)) {
                    $feedback .= "<div class='success'>En beboer har ikke lenger dugnadsfri...</div>";
                }
            }
        }

        // OPPDATERE DUGNADSBARNA - DUGNAD HAS BEEN DONE, NOT DONE, CHANGED

        $beboer = is_numeric($splits[0])
            ? Dugnaden::get()->beboer->getById($splits[0])
            : null;

        $deltager = $beboer ? Dugnaden::get()->deltager->getById($splits[1]) : null;

        if ($beboer && $deltager) {
            $dugnad = $new_dugnad > 0 ? Dugnaden::get()->dugnad->getById($new_dugnad) : null;

            if ((int) $new_dugnad < -1) {
                // The beboer want to take or has completed a dugnad at Vedlikehold
                Dugnaden::get()->deltager->updateSpecialDugnad($deltager, $new_dugnad);
            } elseif ((int) $new_dugnad == -1) {
                Dugnaden::get()->deltager->delete($deltager);
            } elseif ($dugnad) {
                if (Dugnaden::get()->deltager->updateDugnad($deltager, $dugnad)) {
                    $feedback .= "<div class='success'>Dugnadsdatoen ble lagret.</div>";
                } else {
                    $feedback .= "<div class='failure'>Du har valgt flere dugnader p&aring; samme dag, en dugnad ble ikke oppdatert.</div>";
                }
            } else {
                $feedback .= "<div class='failure'>Dugnadsdagen er ugyldig, ta kontakt med en dugnadsleder for &aring; l&oslash;se problemet.</div>";
            }
        }
    }

    return $feedback;
}


function admin_make_select($user_id, $select_count, $date_id, $deltager_id = false)
{
    if (!$deltager_id) {
        $deltager = Dugnaden::get()->deltager->getByBeboerAndDugnad(
            Dugnaden::get()->beboer->getById($user_id),
            Dugnaden::get()->dugnad->getById($date_id)
        );
        $deltager_id = $deltager->id;
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

        $count_all = Dugnaden::get()->dugnad->getDugnadDeltagerCountAll();

        $query = "SELECT
                    dugnad_dato        AS da_date,
                    dugnad_id        AS id,
                    dugnad_type,
                    dugnad_min_kids,
                    dugnad_max_kids
                FROM bs_dugnad
                WHERE dugnad_slettet ='0'
                ORDER BY dugnad_dato";

        $result = @run_query($query);

        while ($row = @mysql_fetch_array($result)) {
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


function admin_get_dugnads($id)
{

    /* Creates drop-downs showing all valid dugnads for a beboer, with the current selected ($id)
       If you need static text, use get_dugnads()
     -------------------------------------------------------------------------------------------- */

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

                $content .= "<img class='dugnads_status' src='./images/dugnad_damn.png' width='24px' height='24px' title='" . get_simple_date($row["dugnad_dato"], true) . " " . $row["note"] . "' alt='[BOT - " . get_simple_date($row["dugnad_dato"], true) . " " . $row["note"] . "]'>\n";
            } else {
                /* Dugnad is done, mark as done_dugnad (green)
                -------------------------------------------------------- */
                // $content .= "<div class='done_dugnad'>". get_simple_date($row["dugnad_dato"], true) . $more_info ."</div>\n";

                $content .= "<img class='dugnads_status' src='./images/dugnad_ok.png' width='24px' height='24px' title='" . get_simple_date($row["dugnad_dato"], true) . " " . $row["note"] . "' alt='[OK - " . get_simple_date($row["dugnad_dato"], true) . " " . $row["note"] . "]'>\n";
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


function get_undone_dugnads($id)
{
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
        $content .= $comma . Dugnad::getTypePrefix($row["dugnad_type"]) . get_simple_date($row["dugnad_dato"], true);

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



function get_public_lastname($last, $first, $last_first = false, $admin = false)
{
    if ($last_first) {
        if (!$admin && strlen($last) > 4) {
            $full_name = mb_substr($last, 0, 4) . "., " . $first;
        } else {
            $full_name = $last . ", " . $first;
        }
    } else {
        if (!$admin && strlen($last) > 4) {
            $full_name = $first . " " . mb_substr($last, 0, 4) . "...";
        } else {
            $full_name = $first . " " . $last;
        }
    }

    return $full_name;
}


function get_dugnadsledere()
{
    $dugnadsledere = Dugnaden::get()->dugnadsleder->getList();
    if (sizeof($dugnadsledere) == 0) {
        return "Dugnadslederne";
    }

    $names = "";
    foreach ($dugnadsledere as $beboer) {
        $phone = $beboer->getDugnadslederPhone();
        $tlf = $phone ? " - " . $phone : "";

        $names .= "<i>" . $beboer->firstName . " " . $beboer->lastName . "</i> (" . $beboer->getRoom()->getPretty() . $tlf . ")<br />";
    }

    return $names;
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
                $beboer = Dugnaden::get()->beboer->getById($beboer_id);
                $dugnad = Dugnaden::get()->dugnad->getById($dugnad_id);

                if (!Dugnaden::get()->deltager->getByBeboerAndDugnad($beboer, $dugnad)) {
                    Dugnaden::get()->deltager->createDugnadCustom(
                        $beboer,
                        $dugnad,
                        1,
                        $note
                    );

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
