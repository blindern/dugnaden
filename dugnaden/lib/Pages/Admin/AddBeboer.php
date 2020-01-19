<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Fragments\HandoutFragment;

class AddBeboer extends BaseAdmin
{
    function show()
    {
        $this->template->addNavigation("Innkallingslapper");

        if (!empty($this->formdata["nyinnkalling"])) {
            $beboere = $this->dugnaden->beboer->getImportBeboerList($this->formdata["nyinnkalling"]);
            $f = new HandoutFragment($this);
            $f->show($beboere);
            return;
        }

        $page = get_layout_parts("form_innkallingnyeste");
        $page["importeringsliste"] = $this->makeLastBeboereSelect() . $page["importeringsliste"];
        $this->template->addContentHtml(implode($page));
    }

    function makeLastBeboereSelect()
    {
        $imports = $this->dugnaden->beboer->getImportsList();

        $options = '';
        if (sizeof($imports) == 0) {
            $options .= '
                <option value="-1">Ingen tilf&oslash;yninger</option>
            ';
        } else {
            foreach ($imports as $import) {
                $import_title = "Import " . sprintf("%03d", substr($import, 3));
                $options .= '
                    <option value="' . htmlspecialchars($import) . '">' . htmlspecialchars($import_title) . '</option>
                ';
            }
        }

        return '
            <select size="1" name="nyinnkalling">
                ' . $options . '
            </select>
        ';
    }
}
