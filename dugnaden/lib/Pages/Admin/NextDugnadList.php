<?php

namespace Blindern\Dugnaden\Pages\Admin;

class NextDugnadList extends BaseAdmin
{
    function show()
    {
        if (isset($_POST['dugnadsleder'])) {
            $this->showForDugnadsleder();
        } else {
            $this->showSelect();
        }
    }

    private function showForDugnadsleder()
    {
        $this->template->setDugnadlisteView();

        $query = "SELECT dugnad_id
                FROM bs_dugnad
                WHERE dugnad_dato > CURDATE()
                AND dugnad_slettet ='0' AND dugnad_type = 'lordag' ORDER BY dugnad_dato  LIMIT 1 ";

        $result = @run_query($query);
        $row = @mysql_fetch_array($result);

        $text = "sinnkalling";

        $dugnadsleder = isset($this->formdata["dugnadsleder"])
            ? $this->dugnaden->beboer->getById($this->formdata["dugnadsleder"])
            : null;

        if ($dugnadsleder) {
            $text = " med " . $dugnadsleder->firstName;
            $phone = $dugnadsleder->getDugnadslederPhone();
            if ($phone) {
                $text .= " - " . $phone;
            }
        }

        $this->template->addContentHtml("<h1 class='big'>Dugnad" . $text . "</h1>

    <p>
        M&oslash;t i peisestuen if&oslash;rt antrekk som passer til b&aring;de innend&oslash;rs-
        og utend&oslash;rsarbeid. Møt tidsnok for å unngå bot.
    </p>\n\n");

        $show_expired_days = false;
        $editable = false;
        $dugnadsliste_full_name = true;

        $this->template->addContentHtml(show_day($this->formdata, $row["dugnad_id"], $show_expired_days, $editable, $dugnadsliste_full_name) . '
    <p>Ta kontakt med dugnadsleder ved spørsmål.</p>');
    }

    private function showSelect()
    {
        $this->template->addNavigation("Neste dugnadsliste");

        $admin_login = get_layout_parts("admin_login_dugnadlist");


        /* Making the select drop-down box with all dugnadsledere .. */

        $select = "<select name='dugnadsleder'><option value='-1'>Velg dugnadsleder</option>";

        $dugnadslederList = $this->dugnaden->dugnadsleder->getList();

        foreach ($dugnadslederList as $beboer) {
            $select .= "<option value='" . $beboer->id . "'>" . $beboer->getName() . "</option>";
        }

        $select .= "</select>";

        $admin_login["dugnadledere"] = $select . $admin_login["dugnadledere"];
        $admin_login["hidden"] = "<input type='hidden' name='admin' value='Neste dugnadsliste'>" . $admin_login["hidden"];

        $this->template->addContentHtml(implode($admin_login));
    }
}
