<?php

namespace Blindern\Dugnaden\Fragments;

use Blindern\Dugnaden\Pages\Admin\BaseAdmin;

class Fragment
{
    /** @var Page */
    public $page;

    /** @var Dugnaden */
    public $dugnaden;

    // TODO: Should not be BaseAdmin
    function __construct(BaseAdmin $basePage)
    {
        $this->page = $basePage->page;
        $this->dugnaden = $basePage->dugnaden;
    }
}
