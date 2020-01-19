<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Page;
use Blindern\Dugnaden\Pages\BasePage;

class BaseAdmin
{
    /** @var Page */
    public $page;

    /** @var Dugnaden */
    public $dugnaden;

    public $formdata;

    function __construct(BasePage $basePage)
    {
        $this->page = $basePage->page;
        $this->dugnaden = $basePage->dugnaden;
        $this->formdata = $basePage->formdata;
    }
}
