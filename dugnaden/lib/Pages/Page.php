<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\PageContext;
use Blindern\Dugnaden\Template;

class Page
{
    /** @var PageContext */
    public $context;

    /** @var Template */
    public $template;

    /** @var Dugnaden */
    public $dugnaden;

    public $formdata;

    function __construct(PageContext $context)
    {
        $this->context = $context;

        // Shortcuts.
        $this->template = $context->template;
        $this->dugnaden = $context->dugnaden;
        $this->formdata = $context->formdata;
    }
}
