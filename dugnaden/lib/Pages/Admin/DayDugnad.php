<?php

namespace Blindern\Dugnaden\Pages\Admin;

class DayDugnad extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml($this->formdata["admin"]);
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; " . $this->formdata["admin"]);

        if (isset($this->formdata["act"]) && $this->formdata["act"] === "Vis dugnadskalenderen") {
            /* user wants to see the dugnadskalender. So be it: forwarding. */
            redirect(DUGNADURL . "?do=admin&admin=Dugnadskalender");
            exit();
        } elseif (!empty($this->formdata["newn"]) && get_beboer_name($this->formdata["newn"]) && empty($this->formdata["notat"])) {
            /* Valid beboer - adding note..
                ------------------------------------------------ */
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
        } else {

            /* VALID LOGIN  - SHOWING NORMAL DAGDUGNAD PAGE
                ------------------------------------------------------------- */


            $feedback .= update_dugnads($this->formdata);

            if (!empty($this->formdata["deln"])) {
                delete_note($this->formdata["deln"]);
            } elseif (!empty($this->formdata["notat"]) && !empty($this->formdata["beboer"]) && get_beboer_name($this->formdata["beboer"])) {
                if (insert_note($this->formdata["beboer"], $this->formdata["notat"], $this->formdata["mottaker"])) {
                    $feedback .= "<p class='success'>Nytt notat ble lagret.</p>";
                } else {
                    $feedback .= "<p class='failure'>Det oppstod en feil, nytt notat ble ikke lagret.</p>";
                }
            } elseif (!empty($this->formdata["beboer"]) && get_beboer_name($this->formdata["beboer"])) {
                /* valid beboer; adding a Vedlikeholdsdugnad */

                $query = "INSERT INTO bs_deltager (deltager_beboer, deltager_dugnad, deltager_gjort, deltager_type, deltager_notat)
                                VALUES ('" . $this->formdata["beboer"] . "', '-2', '0', '0', 'Opprettet av Vedlikehold')";
                @run_query($query);
            }

            global $dugnad_is_empty, $dugnad_is_full;
            list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

            /*
                $box = get_layout_parts("box_green");
                $box["content"] = "<h2>37 beboere har dugnad 28. januar.</h2>". $box["content"];
                */

            $this->page->addContentHtml($feedback . output_vedlikehold_list());
        }
    }
}
