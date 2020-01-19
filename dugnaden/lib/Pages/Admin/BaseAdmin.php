<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Pages\BasePage;
use Blindern\Dugnaden\Template;

class BaseAdmin
{
    /** @var Template */
    public $template;

    /** @var Dugnaden */
    public $dugnaden;

    public $formdata;

    function __construct(BasePage $basePage)
    {
        $this->template = $basePage->template;
        $this->dugnaden = $basePage->dugnaden;
        $this->formdata = $basePage->formdata;
    }
}
