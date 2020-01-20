<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Pages\Page;

class Semesterstart extends Page
{
    function show()
    {
        $this->template->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->template->addNavigation("Semesterstart", "index.php?do=admin&admin=Semesterstart");

        $page = $this->template->getLayoutParts("menu_semesterstart");

        if (truncateAllowed() == false) {
            // $page["disable_kalender"] = "disabled='disabled'" . $page["disable_kalender"];
            $page["disable_import"] = "disabled='disabled'" . $page["disable_import"];
            $page["disable_tildele"] = "disabled='disabled'" . $page["disable_tildele"];
        }

        $this->template->addContentHtml(implode($page));
    }
}
