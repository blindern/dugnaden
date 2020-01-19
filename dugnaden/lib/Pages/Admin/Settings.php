<?php

namespace Blindern\Dugnaden\Pages\Admin;

class Settings extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml("Innstillinger");
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; Innstillinger");
        $this->page->addContentHtml(implode(get_layout_parts("admin_mainmenu")));
    }
}
