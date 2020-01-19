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
        $this->page->setDugnadlisteView();

        $this->formdata['view'] = "Dugnadsliste";

        $query = "SELECT dugnad_id
                FROM bs_dugnad
                WHERE dugnad_dato > CURDATE()
                AND dugnad_slettet ='0' AND dugnad_type = 'lordag' ORDER BY dugnad_dato  LIMIT 1 ";

        $result = @run_query($query);
        $row = @mysql_fetch_array($result);

        $fullname = false;
        $this->page->addContentHtml("<h1 class='big'>Dugnad" . (!empty($this->formdata["dugnadsleder"]) && (int) $this->formdata["dugnadsleder"] != -1 ? " med " . ($name = get_beboerid_name($this->formdata["dugnadsleder"], $fullname)) . ($name == "Karl-Martin" ? " - 971 59 266" : ($name == "Theodor Tinius" ? " - 400 41 458" : "")) : "sinnkalling") . "</h1>

    <p>
        M&oslash;t i peisestuen if&oslash;rt antrekk som passer til b&aring;de innend&oslash;rs-
        og utend&oslash;rsarbeid. Møt tidsnok for å unngå bot.
    </p>\n\n");

        $show_expired_days = false;
        $editable = false;
        $dugnadsliste_full_name = true;

        $this->page->addContentHtml(show_day($this->formdata, $row["dugnad_id"], $show_expired_days, $editable, $dugnadsliste_full_name) . '
    <p>Ta kontakt med dugnadsleder ved spørsmål.</p>');
    }

    private function showSelect()
    {
        $this->page->setTitleHtml("Neste dugnadsliste");
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Botliste");

        $admin_login = get_layout_parts("admin_login_dugnadlist");


        /* Making the select drop-down box with all dugnadsledere .. */

        $select = "<select name='dugnadsleder'><option value='-1'>Velg dugnadsleder</option>";

        $result = get_result("dugnadsleder");

        while ($row = @mysql_fetch_array($result)) {
            $fullname = false;
            $select .= "<option value='" . $row["value"] . "'>" . get_beboerid_name($row["value"], $fullname) . "</option>";
        }

        $select .= "</select>";

        $admin_login["dugnadledere"] = $select . $admin_login["dugnadledere"];
        $admin_login["hidden"] = "<input type='hidden' name='admin' value='Neste dugnadsliste'>" . $admin_login["hidden"];

        $this->page->addContentHtml(implode($admin_login));
    }
}
