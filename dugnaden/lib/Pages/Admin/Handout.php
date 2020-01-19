<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Fragments\HandoutFragment;
use Blindern\Dugnaden\Pages\Page;

class Handout extends Page
{
    function show()
    {
        $beboere = $this->dugnaden->beboer->getAllSortByRoom();

        $f = new HandoutFragment($this->context);
        $f->show($beboere);
    }
}
