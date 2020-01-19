<?php

namespace Blindern\Dugnaden\Pages;

class Main extends Page
{
    function show()
    {
        $this->template->addContentHtml(output_default_frontpage());
    }
}
