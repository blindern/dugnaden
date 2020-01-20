<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Fragments\FrontpageFragment;

class SwitchPassword extends UserPage
{
    public function show()
    {
        if (!$this->beboer) {
            $this->showLogin();
            return;
        }

        $this->template->addNavigation("Bytte passord til " . $this->beboer->getName());

        if (!empty($this->formdata["pw_2"]) && !empty($this->formdata["pw_b"])) {
            if ($this->formdata["pw_2"] === $this->formdata["pw_b"]) {
                $this->dugnaden->beboer->updatePassword(
                    $this->formdata["beboer"],
                    $this->formdata["pw_2"]
                );

                $feedback = '<div class="box-green">Ditt nye passord er lagret.</div>';

                $this->page_array = $this->template->getLayoutParts("menu_main");
                $this->template->addContentHtml($feedback);
                $this->template->addContentHtml(
                    (new FrontpageFragment($this->context))->build()
                );
                return;
            }

            $feedback = '<div class="box-red">Passordene du valgte stemmer ikke overens, de m&aring; v&aelig;re like...</div>';
        } else {
            if ((empty($this->formdata["pw_2"]) && !empty($this->formdata["pw_b"])) || (!empty($this->formdata["pw_2"]) && empty($this->formdata["pw_b"]))) {
                $feedback = '<div class="box-red">Du har ikke fylt inn begge feltene..</div>';
            }
        }

        $this->page_array = $this->template->getLayoutParts("form_pw");

        $this->template->addContentHtml($feedback . $this->page_array["head"] . "<input type='hidden' name='beboer' value='" . $this->formdata["beboer"] . "' /><input type='hidden' name='pw' value='" . $this->formdata["pw"] . "' />" .  $this->page_array["hidden"] . $this->beboer->getName() . $this->page_array["beboer_navn"]);
    }

    public function showLogin()
    {
        $this->template->addNavigation("Bytte passord");
        $this->showLoginFailure();
    }
}
