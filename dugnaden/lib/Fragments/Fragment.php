<?php

namespace Blindern\Dugnaden\Fragments;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\PageContext;
use Blindern\Dugnaden\Template;

class Fragment
{
    /** @var PageContext */
    public $context;

    /** @var Template */
    public $template;

    /** @var Dugnaden */
    public $dugnaden;

    function __construct(PageContext $context)
    {
        $this->context = $context;
        $this->template = $context->template;
        $this->dugnaden = $context->dugnaden;
    }
}
