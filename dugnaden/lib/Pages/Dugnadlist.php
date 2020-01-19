<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Fragments\DugnadlistFragment;

class Dugnadlist extends Page
{
    function show()
    {
        global $dugnad_is_empty, $dugnad_is_full;
        list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

        $this->template->addNavigation("Komplett dugnadsliste");

        $this->template->addContentHtml((new DugnadlistFragment($this->context))->build());
    }
}
