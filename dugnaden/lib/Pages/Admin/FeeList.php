<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Pages\Page;

class FeeList extends Page
{
    function show()
    {
        if (!empty($this->formdata["prepare"]) || !empty($this->formdata["printok"])) {
            $this->showPrintView();
        } else {
            $this->showMenu();
        }
    }

    private function showPrintView()
    {
        $this->template->setPrintView();

        $line_count = 0;

        $flyer = $this->template->getLayoutParts("flyer_botlist");

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
                            AND deltager_gjort <> '0'
                            AND dugnad_checked = '1'

                        ORDER BY beboer_etter, beboer_for";

        $result = @run_query($query);

        $min_date = null;
        $max_date = null;

        while ($row = @mysql_fetch_array($result)) {

            if ($row["dato"] < $min_date) {
                $min_date = $row["dato"];
            }

            if ($row["dato"] > $max_date) {
                $max_date = $row["dato"];
            }

            $entries .= "<div class='row" . (++$line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $line_count . ". " . $row["beboer_etter"] . ", " . $row["beboer_for"]  . "</div>\n<div class='when'>" . get_simple_date($row["dato"], true) . "</div><div class='note'>" . $row["notat"] . "&nbsp;</div><div class='note'>" . (!strcmp($row["bot_annulert"], "-1") ? "-" : null) . ONE_BOT . " kroner&nbsp;" . (!strcmp($row["bot_annulert"], "-1") ? "(ettergitt)" : null) . "</div><div class='spacer'>&nbsp;</div></div>\n\n";
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
                        ORDER BY beboer_etter, beboer_for";

        $result = @run_query($query);

        while ($row = @mysql_fetch_array($result)) {

            $dug_time = "Etterbehandlet";
            $entries .= "<div class='row" . (++$line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $line_count . ". " . $row["beboer_etter"] . ", " . $row["beboer_for"]  . "</div>\n<div class='when'>" . $dug_time . "</div><div class='note'></div><div class='note'>" . (!strcmp($row["bot_annulert"], "-1") ? "-" : null) . ONE_BOT . " kroner&nbsp;" . (!strcmp($row["bot_annulert"], "-1") ? "(ettergitt)" : null) . "</div><div class='spacer'>&nbsp;</div></div>\n\n";
        }



        /* Creating the page
            ------------------------------------------------------------- */

        $flyer["time_space"] = get_simple_date($min_date, true) . " til " . get_simple_date($max_date, true) . $flyer["time_space"];
        $flyer["dugnad_dugnad"] = $entries . $flyer["dugnad_dugnad"];

        $this->template->addContentHtml(implode($flyer));
    }

    private function showMenu()
    {
        $this->template->addNavigation("Botliste");

        $admin_login = $this->template->getLayoutParts("admin_login_botlist");

        $bot_count = $this->getFeeCount();

        if ($bot_count == 0) {
            $admin_login["bot_count"] = "<div class='failure'>Det er for tiden ingen nye b&oslash;ter &aring; skrive ut.</div>" . $admin_login["bot_count"];
        } else {
            $admin_login["bot_count"] = "<div class='success'>Det er registrert " . $bot_count . ($bot_count > 1 ? " nye b&oslash;ter som er klare " : " ny bot som er klar") . " for fakturering.</div>" . $admin_login["bot_count"];
        }

        $admin_login["hidden"] = "<input type='hidden' name='admin' value='Botliste'>" . $admin_login["hidden"];

        $this->template->addContentHtml(implode($admin_login));
    }

    function getFeeCount()
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
}
