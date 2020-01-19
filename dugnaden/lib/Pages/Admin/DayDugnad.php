<?php

namespace Blindern\Dugnaden\Pages\Admin;

class DayDugnad extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml($this->formdata["admin"]);
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; " . $this->formdata["admin"]);

        $newnBeboer = isset($this->formdata["newn"])
            ? $this->dugnaden->beboer->getById($this->formdata["newn"])
            : null;

        if ($newnBeboer && empty($this->formdata["notat"])) {
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

            $admin_login["beboer"] = htmlspecialchars($newnBeboer->getName()) . $admin_login["beboer"];

            $this->page->addContentHtml(implode($admin_login));
        } else {

            /* VALID LOGIN  - SHOWING NORMAL DAGDUGNAD PAGE
                ------------------------------------------------------------- */

            $beboer = isset($this->formdata["beboer"])
                ? $this->dugnaden->beboer->getById($this->formdata["beboer"])
                : null;

            $feedback .= update_dugnads($this->formdata);

            if (!empty($this->formdata["deln"])) {
                $this->dugnaden->note->deleteById($this->formdata["deln"]);
            } elseif (!empty($this->formdata["notat"]) && $beboer) {
                $this->dugnaden->note->create(
                    $beboer,
                    $this->formdata["notat"]
                );
            } elseif ($beboer) {
                $this->dugnaden->deltager->createVedlikeholdDugnad($beboer);
            }

            global $dugnad_is_empty, $dugnad_is_full;
            list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

            $this->page->addContentHtml($feedback . $this->outputVedlikeholdList());
        }
    }

    function outputVedlikeholdList()
    {
        $query = "SELECT DISTINCT beboer_id AS id, beboer_for, beboer_etter
                FROM bs_beboer, bs_deltager
                WHERE deltager_beboer = beboer_id
                    AND deltager_dugnad = '-2'
                ORDER BY beboer_etter, beboer_for";

        $hidden = "<input type='hidden' name='do' value='admin' />
                        <input type='hidden' name='admin' value='Dagdugnad' />";

        $list_title = "Dagdugnad";

        $admin_buttons = "<div class='dagdugnad_beboerselect'>Velg ny beboer som skal ha dagdugnad: " . get_vedlikehold_beboer_select() . " <input type='submit' class='check_space' value='Oppdater Dagdugnadslisten' /></div>";

        $content  = "<h1>" . $list_title . "</h1>
                <p>
                    Du kan tilf&oslash;ye en dagdugnad ved &aring; velge beboeren fra listen under.
                    <b>Hvis du tildeler en dagdugnad</b>, er det viktig at du ber beboeren velge hvilken av de ordin&aelig;re dugnadene
                    som skal slettes. <b>N&aring;r en dagdugnad er utf&oslash;rt</b>, er det ogs&aring; viktig at du merker
                    dagdugnaden som utf&oslash;rt.
                </p>
                ";
        $content .= "<p>
        <a href='index.php?do=admin&admin=Dugnadskalender'>Vis dugnadskalenderen</a>
    </p>

        <form method='post' action='index.php'>" . $hidden . "
        \n";

        $c = 0;
        $result = @run_query($query);

        $content .= "<div class='row_explained'><div class='name_narrow'>Beboerens navn</div><div class='when_narrow'>Dugnadstatus</div><div class='note'>Notater</div><div class='spacer'>&nbsp;</div></div>";

        if (@mysql_num_rows($result) > 0) {
            while ($row = @mysql_fetch_array($result)) {
                $content .= "\n\n" . $this->showVedlikeholdPerson($this->formdata, $row["id"], $c++);
            }
        } else {
            $content .= "<p>Ingen beboere er satt opp med dagdugnad.</p>";
        }

        return $content . $admin_buttons . "</form>";
    }

    function showVedlikeholdPerson($formdata, $id, $line_count)
    {
        global $formdata;

        $beboer = $this->dugnaden->beboer->getById($id);

        $check_box = "<input type='checkbox' name='delete_person[]' value='" . $beboer->id . "'> ";

        return '
            <div class="row' . ($line_count % 2 ? '_odd' : '') . '">
                <div class="name">' . $check_box . $beboer->getName() . (empty($rom) ? ' (<b>rom ukjent</b>)' : null) . '</div>
                <div class="when">' . admin_get_dugnads($id) . '</div>
                <div class="note">' . get_notes($formdata, $id, true) . '&nbsp;</div>
                <div class="spacer">&nbsp;</div>
            </div>
        ';
    }
}
