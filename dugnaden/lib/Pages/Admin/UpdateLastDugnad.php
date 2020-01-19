<?php

namespace Blindern\Dugnaden\Pages\Admin;

class UpdateLastDugnad extends BaseAdmin
{
    function show()
    {
        if ($this->formdata["view"] === "Straffedugnadslapper") {
            $this->showStraffedugnad();
        } else {

            /* SHOWING THE PAPER LAYOUT - OF ALL STRAFFEDUGNADS
                -------------------------------------------------------------------------------- */

            $this->page->setPrintView();

            $item_count = 0;

            $flyer_template = get_layout_parts("flyer_bot");

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

                $this->page->addContentHtml(implode($flyer));
            }
        }
    }

    private function showStraffedugnad()
    {
        $this->page->setTitleHtml("Oppdatere siste dugnadliste");
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Ajourf&oslash;re");

        if (!empty($this->formdata["newn"]) && get_beboer_name($this->formdata["newn"]) && empty($this->formdata["notat"])) {
            $this->showStraffedugnadNewNote();
        } else {
            global $dugnad_is_empty, $dugnad_is_full;
            list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

            $feedback .= update_dugnads($this->formdata);

            /* Updating reference list
                ------------------------------------------------ */
            list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();


            if (!empty($this->formdata["deln"])) {
                delete_note($this->formdata["deln"]);
            } elseif (!empty($this->formdata["notat"]) && !empty($this->formdata["beboer"]) && get_beboer_name($this->formdata["beboer"])) {
                /* Valid beboer - adding notat ..
                    ------------------------------------------------ */

                if (insert_note($this->formdata["beboer"], $this->formdata["notat"], $this->formdata["mottaker"])) {
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
            if (empty($this->formdata["show"]) || !isset($all_dugnads[$this->formdata['show']])) {
                foreach ($all_dugnads as $dugnad) {
                    if ($dugnad_id === null || strtotime($dugnad['dugnad_dato']) < time()) {
                        $dugnad_id = $dugnad['dugnad_id'];
                    }
                }
            } else {
                $dugnad_id = $this->formdata['show'];

                if (isset($this->formdata["prev"])) {
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
                } elseif (isset($this->formdata["next"])) {
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
                if (isset($this->formdata["done"]) && $this->formdata["done"] === "Merke dagen som ferdigbehandlet") {
                    $query = "UPDATE bs_dugnad SET dugnad_checked = '1' WHERE dugnad_id = '" . $dugnad_id . "'";
                    @run_query($query);
                } elseif (isset($this->formdata["done"]) && $this->formdata["done"] === "Angre ferdigbehandling") {
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



                $content .= admin_show_day($this->formdata, $dugnad_id, false);

                if ((int) status_of_dugnad($dugnad_id) == 0) {
                    $done_caption = "Merke dagen som ferdigbehandlet";
                } else {
                    $done_caption = "Angre ferdigbehandling";
                }

                $content .= "<div class='row_explained'><input type='reset' class='check_space' value='Nullstille endringer' />&nbsp;&nbsp;&nbsp;<input type='submit' value='Oppdatere dugnadsbarna'>&nbsp;&nbsp;&nbsp;<input type='submit' name='done' value='" . $done_caption . "'></div></form>";
                $this->page->addContentHtml($content);
            } else {
                $this->page->addContentHtml("<p class='failure'>Det oppstod en feil, viser derfor hele dugnadslisten.</p>" . output_full_list(1));
            }
        }
    }

    private function showStraffedugnadNewNote()
    {
        $admin_login = get_layout_parts("admin_notat");

        $show = (!empty($this->formdata["show"]) ? "<input type='hidden' name='show' value='" . $this->formdata["show"] . "'>\n" : null);

        if (isset($this->formdata["next"])) {
            $show .= "<input type='hidden' name='next' value='go'>\n";
        } elseif (isset($this->formdata["prev"])) {
            $show .= "<input type='hidden' name='prev' value='go'>\n";
        }

        $admin_login["hidden"] = "<input type='hidden' name='do' value='admin'>\n" .
            "<input type='hidden' name='admin' value='" . $this->formdata["admin"] . "'>\n" .
            "<input type='hidden' name='beboer' value='" . $this->formdata["newn"] . "'>\n" . $show .
            $admin_login["hidden"];

        $admin_login["beboer"] = get_beboer_name($this->formdata["newn"]) . $admin_login["beboer"];

        $this->page->addContentHtml(implode($admin_login));
    }
}
