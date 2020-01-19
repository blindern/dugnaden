<?php

namespace Blindern\Dugnaden\Pages\Admin;

class ChangeDugnadsleder extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml($this->formdata["admin"]);
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; $title");

        $page = get_layout_parts("admin_dugnadsledere");

        $content = "";

        if ((int) $this->formdata["beboer"] != -1) {
            if (set_dugnadsleder($this->formdata["beboer"]) != 0) {
                $content .= "<div class='failure'>Det oppstod en feil under tilf&oslash;yelse av ny dugnadsleder...</div>";
            }

            $this->formdata["beboer"] = null;
        }

        if (isset($this->formdata["del_dl"])) {
            /* Deleting dugnadsledere .. */

            foreach ($this->formdata["del_dl"] as $value) {
                if (delete_dugnadsleder($value) != 0) {
                    $content .= "<div class='failure'>Det oppstod en feil under sletting av dugnadsleder...</div>";
                }
            }
        }

        $page["hidden"] = " <input type='hidden' name='admin'    value='Dugnadslederstyring'>
                            <input type='hidden' name='do'        value='admin'>" . $page["hidden"];

        $result = get_result("dugnadsleder");

        /* Adding all dugnadsledere */

        while ($row = @mysql_fetch_array($result)) {
            $all_dl .= "<input type='checkbox' name='del_dl[]' value='" . $row['value'] . "'>" . get_beboerid_name($row['value']) . "<br />";
        }

        $page["dugnadslederne"] = $all_dl . $page["dugnadslederne"];

        /* Showing all beboere in a drop-down box */

        $dugnadsleder = true;
        $page["andre_beboere"] = get_beboer_select($dugnadsleder) . $page["andre_beboere"];

        $content .= implode($page);
        $this->page->addContentHtml($content);
    }
}
