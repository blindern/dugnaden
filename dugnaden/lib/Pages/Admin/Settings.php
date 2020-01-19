<?php

namespace Blindern\Dugnaden\Pages\Admin;

class Settings extends BaseAdmin
{
    function show()
    {
        $this->template->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->template->addContentHtml(implode(get_layout_parts("admin_mainmenu")));
    }
}
