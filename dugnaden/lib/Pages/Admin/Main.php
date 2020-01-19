<?php

namespace Blindern\Dugnaden\Pages\Admin;

class Main extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml("Admin");
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; Admin");

        $content = get_layout_parts("menu_admin");
        $content["db_error"] = database_health() . $content["db_error"];

        $this->page->addContentHtml(implode($content));
    }
}
