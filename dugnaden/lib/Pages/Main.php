<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Fragments\FrontpageFragment;

class Main extends Page
{
    function show()
    {
        $this->template->addContentHtml(
            (new FrontpageFragment($this->context))->build()
        );
    }
}
