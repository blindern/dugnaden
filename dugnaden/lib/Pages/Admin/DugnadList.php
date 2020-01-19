<?php

namespace Blindern\Dugnaden\Pages\Admin;

class DugnadList extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml("Administrere dugnadsliste");
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Dugnadsliste");

        if (!empty($this->formdata["newn"]) && get_beboer_name($this->formdata["newn"]) && empty($this->formdata["notat"])) {
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
            $feedback .= update_dugnads($this->formdata);

            if (!empty($this->formdata["deln"])) {
                delete_note($this->formdata["deln"]);
            } elseif (!empty($this->formdata["notat"]) && !empty($this->formdata["beboer"]) && get_beboer_name($this->formdata["beboer"])) {
                if (insert_note($this->formdata["beboer"], $this->formdata["notat"], $this->formdata["mottaker"])) {
                    $feedback .= "<p class='success'>Nytt notat ble lagret.</p>";
                } else {
                    $feedback .= "<p class='failure'>Det oppstod en feil, nytt notat ble ikke lagret.</p>";
                }
            }

            if (!empty($this->formdata["delete_person"])) {
                /* Some user is to be deleted .. */
                $feedback .= delete_beboer_array($this->formdata["delete_person"]);
            }

            if (!empty($this->formdata["delete"])) {
                foreach ($this->formdata["delete"] as $beboer_dugnad) {
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
            list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

            $this->page->addContentHtml($feedback . output_full_list(1));
        }
    }
}
