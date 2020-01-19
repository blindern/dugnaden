<?php

namespace Blindern\Dugnaden\Pages\Admin;

class Settings extends BaseAdmin
{
    function show()
    {
        $this->page->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->page->addContentHtml(implode(get_layout_parts("admin_mainmenu")));
    }
}
