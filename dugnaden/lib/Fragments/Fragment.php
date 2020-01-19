<?php

namespace Blindern\Dugnaden\Fragments;

use Blindern\Dugnaden\Pages\Admin\BaseAdmin;
use Blindern\Dugnaden\Template;

class Fragment
{
    /** @var Template */
    public $template;

    /** @var Dugnaden */
    public $dugnaden;

    // TODO: Should not be BaseAdmin
    function __construct(BaseAdmin $basePage)
    {
        $this->template = $basePage->template;
        $this->dugnaden = $basePage->dugnaden;
    }
}
