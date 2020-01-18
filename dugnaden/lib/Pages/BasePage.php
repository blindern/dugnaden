<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Page;

class BasePage
{
    /** @var Page */
    public $page;

    /** @var Dugnaden */
    public $dugnaden;

    function __construct(Page $page)
    {
        $this->page = $page;
        $this->dugnaden = Dugnaden::get();
        $this->formdata = get_formdata();
    }
}
