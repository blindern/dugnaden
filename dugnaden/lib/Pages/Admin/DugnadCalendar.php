<?php

namespace Blindern\Dugnaden\Pages\Admin;

class DugnadCalendar extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml($this->formdata["admin"]);
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; <a href='index.php?do=admin&admin=Semesterstart'>Semesterstart</a> &gt; $title");

        $content = "";

        switch ($this->formdata["saturdays"]) {
            case "add":

                if (truncateAllowed()) {
                    $query = "TRUNCATE TABLE bs_dugnad";
                    @run_query($query);

                    $content .= "<p class='success'>Tilf&oslash;yde " . get_saturdays() . " l&oslash;rdager for dette semesteret.</p>";
                } else {
                    $content .= "<p class='failure'>Denne operasjonenen er ikke tillatt etter at dugnadsperioden har startet.</p>";
                }
                break;

            case "remove":

                $query = "TRUNCATE TABLE bs_deltager";
                @run_query($query);

                if (@mysql_errno() == 0) {
                    $query = "TRUNCATE TABLE bs_dugnad";
                    @run_query($query);

                    $content .= "<p class='success'>Alle l&oslash;rdager er slettet.</p>";
                } else {
                    $content .= "<p class='failure'>Beklager, det oppstod en feil under sletting av l&oslash;rdagene.</p>";
                }

                break;

            case "idle":
                $content .= update_saturdays_status();
                break;

            default:
                /* Not deleting, adding og updating...
                    --------------------------------------------------------------- */

                break;
        }

        $content .= "<form action='index.php' method='post'>" . show_all_saturdays();
        $content .= get_layout_content("form_update") . "</form>" . $msg;

        if (truncateAllowed($future_check) == false) {

            $warning = "<p>&nbsp;</p>\n\t\t\t\t\t\t\t<div class='bl_red'>
                            <div class='br_red'>
                                <div class='tl_red'>
                                    <div class='tr_red'>
                                        <b>MERK</b>: Sletter du alle l&oslash;rdager, slettes ogs&aring; alle tilknyttede dugnader. Nye dugnader m&aring; derfor alltid tildeles etter sletting.
                                    </div>
                                </div>
                            </div>
                        </div>\n";

            $content .= $warning;
        }

        $this->page->addContentHtml($content);
    }
}
