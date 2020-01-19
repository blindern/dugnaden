<?php

namespace Blindern\Dugnaden\Fragments;

use Blindern\Dugnaden\Model\Beboer;

class HandoutFragment extends Fragment
{
    /** @param Beboer[] */
    public function show($beboere)
    {
        $this->page->setPrintView();

        $element_count = 0;
        $flyer = get_layout_parts("flyer_passord");

        $dugnadsledere = get_dugnadsledere();

        foreach ($beboere as $beboer) {
            $undone_dugnads = get_undone_dugnads($beboer->id);

            if (!empty($undone_dugnads)) {
                $new_flyer = $flyer;

                $room = $beboer->getRoom();
                $room_details = $room ? $room->getPretty() : "";

                $new_flyer["rom_info"] = $beboer->getName() . "<br />" . $room_details . $new_flyer["rom_info"];

                if ($element_count++ % 2) {
                    $new_flyer["format_print"] = "_break" . $new_flyer["format_print"];
                }

                $new_flyer["gutta"] = $dugnadsledere . $new_flyer["gutta"];
                $new_flyer["dugnad_url"] = DUGNADURL . $new_flyer["dugnad_url"];
                $new_flyer["dugnad_dugnad"] = $undone_dugnads . $new_flyer["dugnad_dugnad"];
                $new_flyer["passord"] = $beboer->password . $new_flyer["passord"];

                $this->page->addContentHtml(implode($new_flyer));
            }
        }
    }
}
