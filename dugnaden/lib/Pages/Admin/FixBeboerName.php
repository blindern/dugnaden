<?php

namespace Blindern\Dugnaden\Pages\Admin;

class FixBeboerName extends BaseAdmin
{
    function show()
    {
        $this->page->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->page->addNavigation("Rette beboernavn");

        $page = get_layout_parts("menu_name");

        $page["hidden"] = "<input type='hidden' name='admin' value='" . $this->formdata["admin"] . "'><input type='hidden' name='do' value='admin'>" . $page["hidden"];

        $beboer = null;
        if (isset($this->formdata["beboer"])) {
            $beboer = $this->dugnaden->beboer->getById($this->formdata["beboer"]);
        }

        if ($beboer && isset($this->formdata["first"]) && isset($this->formdata["last"])) {
            $this->dugnaden->beboer->updateName($beboer, $this->formdata["first"], $this->formdata["last"]);

            $feedback .= "<div class='success'>Vellykket oppdatering, n&aring; heter beboeren " . $beboer->getName() . ".</div>";
            $beboer = null;
        }

        if (!$beboer) {
            $dugnadsleder = false;
            $page["beboer_bytte"] = "1. " . get_beboer_select($dugnadsleder) . "&nbsp;&nbsp;&nbsp;&nbsp; <input type='submit' value='OK'><br />" .
                "<div class='hint'>2. Fyll inn nytt etternavn og fornavn...</div>" . $page["beboer_bytte"];
        } else {
            $page["hidden"] = "<input type='hidden' name='beboer' value='" . $beboer->id . "'>" . $page["hidden"];

            $page["beboer_bytte"] = "<div class='hint'>1. Fyll inn nytt fornavn og etternavn</div><br />
                                        2. <input type='input' name='last' value='" . htmlspecialchars($beboer->lastName) . "'>,
                                        <input type='input' name='first' value='" . htmlspecialchars($beboer->firstName) . "'>
                                        <input type='submit' name='admin' value='Rette beboernavn'>
                                        <a href='index.php?do=admin&admin=Rette%20beboernavn'>Tilbake</a>" . $page["beboer_bytte"];
        }

        $this->page->addContentHtml($feedback . implode($page));
    }
}
