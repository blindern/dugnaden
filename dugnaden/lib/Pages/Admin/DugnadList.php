<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Fragments\DugnadlistFragment;
use Blindern\Dugnaden\Pages\Page;

class DugnadList extends Page
{
    function show()
    {
        $this->template->addNavigation("Administrer dugnadsliste");

        $newnBeboer = isset($this->formdata["newn"])
            ? $this->dugnaden->beboer->getById($this->formdata["newn"])
            : null;

        if ($newnBeboer && empty($this->formdata["notat"])) {
            /* Valid beboer - adding note..
            ------------------------------------------------ */
            $admin_login = $this->template->getLayoutParts("admin_notat");

            $show = (!empty($this->formdata["show"]) ? "<input type='hidden' name='show' value='" . $this->formdata["show"] . "'>\n" : null);

            if (isset($this->formdata["next"])) {
                $show .= "<input type='hidden' name='next' value='go'>\n";
            } elseif (isset($this->formdata["prev"])) {
                $show .= "<input type='hidden' name='prev' value='go'>\n";
            }

            $admin_login["hidden"] = "<input type='hidden' name='do' value='admin'>\n" .
                "<input type='hidden' name='admin' value='" . $this->formdata["admin"] . "'>\n" .
                "<input type='hidden' name='beboer' value='" . $newnBeboer->id . "'>\n" . $show .
                $admin_login["hidden"];

            $admin_login["beboer"] = $newnBeboer->getName() . $admin_login["beboer"];

            $this->template->addContentHtml(implode($admin_login));
        } else {
            $feedback .= update_dugnads($this->formdata);

            $beboer = isset($this->formdata["beboer"])
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

            if (!empty($this->formdata["delete_person"])) {
                /* Some user is to be deleted .. */
                $feedback .= $this->deleteBeboerArray($this->formdata["delete_person"]);
            }

            global $dugnad_is_empty, $dugnad_is_full;
            list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

            $this->template->addContentHtml($feedback . (new DugnadlistFragment($this->context))->build(true));
        }
    }

    private function deleteBeboerArray($beboer_array)
    {
        $success = true;

        $failed = 0;
        $deleted = 0;

        foreach ($beboer_array as $beboer_id) {
            if (!$this->deleteBeboer($beboer_id) && $success) {
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


    private function deleteBeboer($beboer_id)
    {
        $beboer = $this->dugnaden->beboer->getById($beboer_id);
        if (!$beboer) return false;

        $this->dugnaden->beboer->delete($beboer);
        return true;
    }
}
