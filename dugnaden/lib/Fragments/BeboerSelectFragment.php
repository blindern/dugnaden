<?php

namespace Blindern\Dugnaden\Fragments;

use Blindern\Dugnaden\Model\Beboer;

class BeboerSelectFragment extends Fragment
{
    public $selectText = "Velg beboer fra listen";
    public $truncateName = true;

    /** @var Beboer */
    public $currentBeboer = null;

    function build()
    {
        $beboere = $this->dugnaden->beboer->getAll();

        $options = '';
        foreach ($beboere as $beboer) {
            $selected = $this->currentBeboer && $this->currentBeboer->id === $beboer->id
                ? ' selected="selected"'
                : '';

            $name = $this->truncateName
                ? $beboer->getNameTruncated()
                : $beboer->getName();

            $options .= '
                <option value="' . $beboer->id . '"' . $selected . '>
                    ' . htmlspecialchars($name) . '
                </option>
            ';
        }

        return '
            <select name="beboer">
                <option value="-1">' . htmlspecialchars($this->selectText) . '</option>
                ' . $options . '
            </select>
        ';
    }
}
