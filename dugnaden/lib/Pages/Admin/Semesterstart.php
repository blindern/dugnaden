<?php

namespace Blindern\Dugnaden\Pages\Admin;

class Semesterstart extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml($this->formdata["admin"]);
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; $title");

        $page = get_layout_parts("menu_semesterstart");

        if (truncateAllowed() == false) {
            // $page["disable_kalender"] = "disabled='disabled'" . $page["disable_kalender"];
            $page["disable_import"] = "disabled='disabled'" . $page["disable_import"];
            $page["disable_tildele"] = "disabled='disabled'" . $page["disable_tildele"];
        }

        $this->page->addContentHtml(implode($page));
    }
}
