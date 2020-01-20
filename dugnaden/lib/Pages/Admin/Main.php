<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Pages\Page;

class Main extends Page
{
    function show()
    {
        $this->template->addContentHtml(
            implode($this->template->getLayoutParts("menu_admin"))
        );
    }
}
