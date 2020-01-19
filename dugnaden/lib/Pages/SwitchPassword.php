<?php

namespace Blindern\Dugnaden\Pages;

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

                $feedback = rounded_feedback_box("green", "Ditt nye passord er lagret.");

                $this->page_array = get_layout_parts("menu_main");
                $this->template->addContentHtml($feedback . output_default_frontpage());
                return;
            }

            $feedback = rounded_feedback_box("red", "Passordene du valgte stemmer ikke overens, de m&aring; v&aelig;re like...");
        } else {
            if ((empty($this->formdata["pw_2"]) && !empty($this->formdata["pw_b"])) || (!empty($this->formdata["pw_2"]) && empty($this->formdata["pw_b"]))) {
                $feedback = rounded_feedback_box("red", "Du har ikke fylt inn begge feltene..");
            }
        }

        $this->page_array = get_layout_parts("form_pw");

        $this->template->addContentHtml($feedback . $this->page_array["head"] . "<input type='hidden' name='beboer' value='" . $this->formdata["beboer"] . "' /><input type='hidden' name='pw' value='" . $this->formdata["pw"] . "' />" .  $this->page_array["hidden"] . $this->beboer->getName() . $this->page_array["beboer_navn"]);
    }

    public function showLogin()
    {
        $this->template->addNavigation("Bytte passord");
        $this->showLoginFailure();
    }
}
