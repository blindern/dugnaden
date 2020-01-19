<?php

namespace Blindern\Dugnaden\Pages;

class Main extends BasePage
{
    function show()
    {
        $this->page->addContentHtml(output_default_frontpage());
    }
}
