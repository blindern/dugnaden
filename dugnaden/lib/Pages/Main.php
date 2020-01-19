<?php

namespace Blindern\Dugnaden\Pages;

class Main extends BasePage
{
    function show()
    {
        $this->template->addContentHtml(output_default_frontpage());
    }
}
