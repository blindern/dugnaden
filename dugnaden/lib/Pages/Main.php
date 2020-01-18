<?php

namespace Blindern\Dugnaden\Pages;

class Main extends BasePage
{
    function show()
    {
        $this->page->setTitleHtml("Dugnadsordningen p&aring; nett");
        $this->page->setNavigationHtml("Hovedmeny");
        $this->page->addContentHtml(output_default_frontpage());
    }
}
