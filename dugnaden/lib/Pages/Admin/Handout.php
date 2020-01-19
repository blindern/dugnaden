<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Fragments\HandoutFragment;

class Handout extends BaseAdmin
{
    function show()
    {
        $beboere = $this->dugnaden->beboer->getAllSortByRoom();

        $f = new HandoutFragment($this);
        $f->show($beboere);
    }
}
