<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Model\Dugnad;

class UpdateLastDugnad extends BaseAdmin
{
    function show()
    {
        if ($this->formdata["view"] === "Straffedugnadslapper") {
            $this->showStraffedugnad();
        } else {

            /* SHOWING THE PAPER LAYOUT - OF ALL STRAFFEDUGNADS
                -------------------------------------------------------------------------------- */

            $this->template->setPrintView();

            $item_count = 0;

            $flyer_template = get_layout_parts("flyer_bot");

            $query = "SELECT dugnad_id AS id, dugnad_dato AS dato FROM bs_dugnad WHERE dugnad_dato >= (SELECT dugnad_dato FROM bs_dugnad WHERE dugnad_id = ${formdata['show']}) AND dugnad_slettet ='0' ORDER BY dugnad_dato LIMIT 1";

            $result    = @run_query($query);
            $denne_dugnaden    = @mysql_fetch_array($result);

            $dugnad = $this->dugnaden->dugnad->getById($denne_dugnaden["id"]);
            $week = $dugnad->getWeekNumber();

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

                $this->template->addContentHtml(implode($flyer));
            }
        }
    }

    private function showStraffedugnad()
    {
        $this->template->addNavigation("Oppdatere siste dugnadsliste");

        $newnBeboer = !empty($this->formdata["newn"])
            ? $this->dugnaden->beboer->getById($this->formdata["newn"])
            : null;

        if ($newnBeboer && empty($this->formdata["notat"])) {
            $this->showStraffedugnadNewNote($newnBeboer);
        } else {
            global $dugnad_is_empty, $dugnad_is_full;
            list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

            $feedback .= update_dugnads($this->formdata);

            /* Updating reference list
                ------------------------------------------------ */
            list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

            $beboer = !empty($this->formdata["beboer"])
                ? $this->dugnaden->beboer->getById($this->formdata["beboer"])
                : null;

            if (!empty($this->formdata["deln"])) {
                $this->dugnaden->note->deleteById($this->formdata["deln"]);
            } elseif (!empty($this->formdata["notat"]) && $beboer) {
                $this->dugnaden->note->create(
                    $beboer,
                    $this->formdata["notat"]
                );
            }

            $dugnadList = $this->dugnaden->dugnad->getAll();
            $dugnadListById = [];
            foreach ($dugnadList as $dugnad) {
                $dugnadListById[$dugnad->id] = $dugnad;
            }

            $dugnad = null;
            if (empty($this->formdata["show"]) || !isset($dugnadListById[$this->formdata['show']])) {
                foreach ($dugnadList as $item) {
                    if ($dugnad === null || !$item->isFuture()) {
                        $dugnad = $item;
                    }
                }
            } else {
                $dugnad = $dugnadListById[$this->formdata['show']];

                if (isset($this->formdata["prev"])) {
                    $prev = null;
                    foreach ($dugnadList as $item) {
                        if ($item->id == $dugnad->id) {
                            if ($prev) {
                                $dugnad = $prev;
                            }
                            break;
                        }
                        $prev = $item;
                    }
                } elseif (isset($this->formdata["next"])) {
                    $next = null;
                    foreach ($dugnadList as $item) {
                        if ($next) {
                            $dugnad = $item;
                            break;
                        }
                        if ($item->id == $dugnad->id) {
                            $next = true;
                        }
                    }
                }
            }

            if ($dugnad) {
                if (isset($this->formdata["done"]) && $this->formdata["done"] === "Merke dagen som ferdigbehandlet") {
                    $this->dugnaden->dugnad->markCompleted($dugnad);
                } elseif (isset($this->formdata["done"]) && $this->formdata["done"] === "Angre ferdigbehandling") {
                    $this->dugnaden->dugnad->markCompletedUndo($dugnad);
                }

                $show_status = $this->updateStatusOnAll($dugnad->id);


                if ($this->getStraffCount($dugnad->id) > 0) {
                    $straff = "<input type='submit' name='view' value='Straffedugnadslapper'> ";
                }

                /* Top navigational buttons
                    ----------------------------------------------------------------- */
                $content  = "<form action='index.php' method='post'>
                                    <input type='hidden' name='do' value='admin'>
                                    <input type='hidden' name='admin' value='Oppdatere siste'>
                                    <input type='hidden' name='show' value='" . $dugnad->id . "'>
                                    <input type='submit' name='prev' value='&larr;'><input type='submit' name='next' value='&rarr;'> " . $straff . $nav_status . "</form>";


                /* The form to the actual list of beboere
                    ----------------------------------------------------------------- */

                $content  .= "<form action='index.php' method='post'>
                                    <input type='hidden' name='do' value='admin'>
                                    <input type='hidden' name='admin' value='Oppdatere siste'>
                                    <input type='hidden' name='show' value='" . $dugnad->id . "'>";



                $content .= $this->adminShowDay($this->formdata, $dugnad->id, false);

                $dugnad = $this->dugnaden->dugnad->getById($dugnad->id);
                if (!$dugnad) {
                }

                if (!$dugnad->isDone()) {
                    $done_caption = "Merke dagen som ferdigbehandlet";
                } else {
                    $done_caption = "Angre ferdigbehandling";
                }

                $content .= "<div class='row_explained'><input type='reset' class='check_space' value='Nullstille endringer' />&nbsp;&nbsp;&nbsp;<input type='submit' value='Oppdatere dugnadsbarna'>&nbsp;&nbsp;&nbsp;<input type='submit' name='done' value='" . $done_caption . "'></div></form>";
                $this->template->addContentHtml($content);
            } else {
                $this->template->addContentHtml("<p class='failure'>Det oppstod en feil, viser derfor hele dugnadslisten.</p>" . output_full_list(1));
            }
        }
    }

    private function showStraffedugnadNewNote(Beboer $beboer)
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
            "<input type='hidden' name='beboer' value='" . $beboer->id . "'>\n" . $show .
            $admin_login["hidden"];

        $admin_login["beboer"] = $beboer->getName() . $admin_login["beboer"];

        $this->template->addContentHtml(implode($admin_login));
    }

    function adminShowDay($formdata, $day, $use_dayspacer = true)
    {
        $dugnad = $this->dugnaden->dugnad->getById($day);

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

            $entries .= "<div class='row_header'><h1>" . $dugnad->getDayHeader() . "</h1></div>\n\n";
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

                $dugnads = admin_get_dugnads($id) . admin_addremove_dugnad($id, $line_count);

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

    function getStraffCount($dugnad_id)
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

    function updateStatusOnAll($day_id)
    {
        global $formdata;

        $dugnad = $this->dugnaden->dugnad->getById($day_id);
        if (!$dugnad) return;

        $barn = 0;

        foreach ($formdata as $key => $value) {
            $deltager = null;
            $beboer = null;
            if (is_numeric($key)) {
                $beboer = $this->dugnaden->beboer->getById($key);
                if ($beboer) {
                    $deltager = $this->dugnaden->deltager->getByBeboerAndDugnad($beboer, $dugnad);
                }
            }

            if ($deltager) {
                $barn = $barn + 1;

                $this->dugnaden->deltager->updateDone($deltager, $value);

                // Updating, adding or removing dugnad and bot.

                $fee = $this->dugnaden->fee->getByBeboerAndDugnad($beboer, $dugnad);

                switch ($value) {
                    case 1: // Bot og ny dugnad
                        if (!$fee) {
                            $this->dugnaden->fee->create($deltager);
                        }

                        $this->newDugnad($beboer, $dugnad, $value);
                        break;

                    case 2: // Kun ny dugnad
                        if ($fee) {
                            $this->dugnaden->fee->delete($fee);
                        }

                        $this->newDugnad($beboer, $dugnad, $value);
                        break;

                    case 3: // Kun bot
                        if (!$fee) {
                            $this->dugnaden->fee->create($deltager);
                        }

                        $this->removeDugnad($beboer, $dugnad);
                        break;

                    default: // No bot and no new dugnad
                        if ($fee) {
                            $this->dugnaden->fee->delete($fee);
                        }

                        $this->removeDugnad($beboer, $dugnad);
                        break;
                }
            }
        }
    }

    function newDugnad(Beboer $beboer, Dugnad $dugnad, $deltager_gjort)
    {
        $deltager = $this->dugnaden->deltager->getByBeboerAndDugnad($beboer, $dugnad);
        if (!$this->dugnaden->deltager->hasCreatedNew($deltager, $deltager_gjort)) {
            $this->addStraffDugnad($beboer, $dugnad, $deltager_gjort);
        }
    }

    function removeDugnad(Beboer $beboer, Dugnad $dugnad)
    {
        $deltager = $this->dugnaden->deltager->getDeltagerStraffFor($beboer, $dugnad);
        if ($deltager) {
            $this->dugnaden->deltager->delete($deltager);
        }
    }

    function addStraffDugnad(Beboer $beboer, Dugnad $dugnad, $deltager_gjort)
    {
        global $dugnad_is_full;

        $deltagerStraff = $this->dugnaden->deltager->getDeltagerStraffFor($beboer, $dugnad);
        if ($deltagerStraff) {
            return;
        }

        $dugnadList = $this->dugnaden->dugnad->getFutureLoerdagDugnadList();
        foreach ($dugnadList as $nextDugnad) {
            if ($nextDugnad->offsetDaysFromToday() < 10) continue;

            // Skip if full.
            if (!empty($dugnad_is_full[$nextDugnad->id])) continue;

            // Skip if already having dugnad.
            if ($this->dugnaden->deltager->getByBeboerAndDugnad($beboer, $nextDugnad)) continue;

            $this->dugnaden->deltager->createDugnadCustom(
                $beboer,
                $nextDugnad,
                ($deltager_gjort ? -1 : 1),
                "Straff fra uke " . $dugnad->getWeekNumber() . "."
            );

            return;
        }

        $this->dugnaden->note->create(
            $beboer,
            "Dugnad " . $dugnad->formatDate() . " utsatt til neste semester (fant ingen ledige dugnader)."
        );
    }
}
