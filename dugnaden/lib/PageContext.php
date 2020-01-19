<?php

namespace Blindern\Dugnaden;

class PageContext
{
    /** @var Template */
    public $template;

    /** @var Dugnaden */
    public $dugnaden;

    public $formdata;

    function __construct(Template $template)
    {
        $this->template = $template;
        $this->dugnaden = Dugnaden::get();
        $this->formdata = $this->getFormData();
    }

    private function getFormData()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            foreach ($_POST as $key => $value) {
                $formdata[$key] = $value;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
            foreach ($_GET as $key => $value) {
                $formdata[$key] = $value;
            }
        } else {
            return [];
        }

        return $formdata;
    }
}
