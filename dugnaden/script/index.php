<?php

// Placed here to reroute visitor to company front page.

function redirect($to)
{
    if (headers_sent()) {
        print '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<HTML><HEAD>
<TITLE>Request Halted</TITLE>
</HEAD><BODY>
<H1>Action Halted</H1>
Sorry, but your request can not be handled.<P>
<HR>
<ADDRESS>Pedit/2.0.1</ADDRESS>
</BODY></HTML>';

        exit();
    } else {
        header("HTTP/1.1 303 See Other");
        header("Location: $to");
        exit();
    }
}


redirect("http://www.dittverk.no/dugnad/");
