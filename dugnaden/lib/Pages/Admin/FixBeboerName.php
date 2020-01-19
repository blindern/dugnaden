<?php

namespace Blindern\Dugnaden\Pages\Admin;

class FixBeboerName extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml($this->formdata["admin"]);
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; $title");

        $page = get_layout_parts("menu_name");

        $page["hidden"] = "<input type='hidden' name='admin' value='" . $this->formdata["admin"] . "'><input type='hidden' name='do' value='admin'>" . $page["hidden"];

        if (verify_person_id($this->formdata["beboer"]) && isset($this->formdata["first"]) && isset($this->formdata["last"]) && strcmp($this->formdata["go"], "Tilbake")) {
            /* Admin has logged in and entered new values for a beboer, time to save them: */

            $query = "UPDATE bs_beboer SET beboer_for = '" . $this->formdata["first"] . "', beboer_etter = '" . $this->formdata["last"]
                . "'    WHERE beboer_id = '" . $this->formdata["beboer"] . "'";

            @run_query($query);

            if (@mysql_errno() == 0) {
                $feedback .= "<div class='success'>Vellykket oppdatering, n&aring; heter beboeren " . get_beboerid_name($this->formdata["beboer"]) . ".</div>";

                $this->formdata["beboer"] = "-1";
                $this->formdata["beboer"] = null;
            } else {
                $feedback .= "<div class='failure'>Det oppstod en feil, navnet ble ikke oppdatert...</div>";
            }
        }

        if ((!isset($this->formdata["beboer"]) || (int) $this->formdata["beboer"] == -1) || (isset($this->formdata["go"]) && $this->formdata["go"] === "Tilbake")) {
            /* Either wrong password, no password, no beboer selected og the button "Tilbake" has been clicked: */

            if ($this->formdata["beboer"] === "-1") {
                $feedback .= "<div class='failure'>Du m&aring; velge en beboer med feil i navnet fra nedtrekksmenyen...</div>";
            } elseif (isset($this->formdata["go"]) && $this->formdata["go"] === "Tilbake") {
                $this->formdata["beboer"] = "-1";
            }

            $dugnadsleder = false;
            $page["beboer_bytte"] = "1. " . get_beboer_select($dugnadsleder) . "&nbsp;&nbsp;&nbsp;&nbsp; <input type='submit' value='OK'><br />" .
                "<div class='hint'>2. Fyll inn nytt etternavn og fornavn...</div>" . $page["beboer_bytte"] . $page["beboer_bytte"];
        } else {
            $page["hidden"] = "<input type='hidden' name='beboer' value='" . $this->formdata["beboer"] . "'>" . $page["hidden"];

            $page["beboer_bytte"] = "<div class='hint'>1. Fyll inn nytt fornavn og etternavn</div><br />
                                        2. <input type='input' name='last' value='" . get_beboerid_name($this->formdata["beboer"], false, true) . "'>,
                                        <input type='input' name='first' value='" . get_beboerid_name($this->formdata["beboer"], false) . "'>
                                        <input type='submit' name='admin' value='Rette beboernavn'>
                                        <input type='submit' name='go' value='Tilbake'>" . $page["beboer_bytte"];
        }

        $this->page->addContentHtml($feedback . implode($page));
    }
}
