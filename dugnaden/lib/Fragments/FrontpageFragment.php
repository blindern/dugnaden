<?php

namespace Blindern\Dugnaden\Fragments;

class FrontpageFragment extends Fragment
{
    public function build()
    {
        $page_array = get_layout_parts("menu_main");

        $page_array["gutta"] = get_dugnadsledere() . $page_array["gutta"];

        $f = new BeboerSelectFragment($this->context);
        $f->selectText = "Hvem er du?";
        $f->currentBeboer = !empty($this->formdata["beboer"])
            ? $this->dugnaden->beboer->getById($this->formdata["beboer"])
            : null;

        $page_array["beboer"] = $f->build() . $page_array["beboer"];

        return implode($page_array);
    }
}
