<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Pages\Page;

class Main extends Page
{
    function show()
    {
        $content = get_layout_parts("menu_admin");
        $content["db_error"] = database_health() . $content["db_error"];

        $this->template->addContentHtml(implode($content));
    }
}
