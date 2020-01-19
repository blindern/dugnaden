<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Fragments\BeboerSelectFragment;
use Blindern\Dugnaden\Pages\Page;

class RevokeFee extends Page
{
    function show()
    {
        $this->template->addNavigation("Annulere bot");

        $content = "";

        $page = get_layout_parts("admin_annulerebot");

        $page["hidden"] = " <input type='hidden' name='admin'    value='" . $this->formdata["admin"] . "' />
                            <input type='hidden' name='do'        value='admin' />" . $page["hidden"];

        $beboer = !empty($this->formdata["beboer"])
            ? $this->dugnaden->beboer->getById($this->formdata["beboer"])
            : null;

        if ($beboer) {
            $this->dugnaden->fee->createRevoke($beboer);
            $content .= "<div class='success'>Ny annulering ble tilf&oslash;yd for " . $beboer->getName() . "...</div>";
        }

        if (isset($this->formdata["del_dl"])) {
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

            foreach ($this->formdata["del_dl"] as $value) {
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

        /* Generate first month of the semester, so bots from the previous semester is not shown...
        ---------------------------------------------------------------------------------------------------------------- */

        $year    = date("Y", time());
        $month    = date("m", time());
        $day    = date("d", time());

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

                $beboer = $this->dugnaden->beboer->getById($row['beboer_id']);

                $all_dl .= "<div class='row" . $row_Text_and_Background . "'><div class='check_left'><input type='checkbox' name='del_dl[]' " . ((int) $row["bot_annulert"] == 1 ? "checked='checked' disabled " : null) . "value='" . $row['bot_id'] . "'></div><div class='name_wide" . (!(int) $row["bot_registrert"] ? "_success" : null) . "'>" . $line_count . ". " . htmlspecialchars($beboer->getName()) . "</div><div class='when'>" . get_simple_date($row["dugnad_dato"], true) . " " . $desc . "</div></div>\n";

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
            $page["semester_total"] = $simple_stats . " dette semesteret alene." . $page["semester_total"];
        }


        $page["botliste"] = $all_dl . $page["botliste"];

        $f = new BeboerSelectFragment($this->context);
        $f->truncateName = false;

        $admin_buttons = '
            <div class="box-green">
                Tilf&oslash;y ny annulering manuelt fordi boten ikke finnes i Botlisten over: ' . $f->build() . '
            </div>';

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

        $this->template->addContentHtml($content);
    }
}
