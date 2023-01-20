<?php

    /**
     * @param string $command Shell command
     * @param array $output Output of command
     * @return integer Status of command
     */
    function bash($command, &$output = null) {
        $command = 'sudo -u metacortex ' . $command;
        $command = escapeshellcmd($command);
        exec($command, $output, $status);
        return $status;
    }

    function stripAccents($stripAccents) {
        if (strpos($stripAccents, 'œ'))
            $stripAccents = str_replace('œ', 'oe', $stripAccents);
        $transliterator = Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', Transliterator::FORWARD);
        return $transliterator->transliterate($stripAccents);
    }

    function ClearLyrics($lyrics) {
        // Remove '&quot;' and '&amp;'
        $lyrics = str_replace('&quot;', '"', $lyrics);
        $lyrics = str_replace('&amp;', '&', $lyrics);

        // Remove balises
        $balises = [ '<br>', '</br>', '<div>', '</div>', '<i>', '</i>' ];
        foreach ($balises as $balise)
            $lyrics = str_replace($balise, '', $lyrics);

        // Remove white spaces at start and at end
        $lyrics = trim($lyrics, " \t\n\r\0\x0B");

        // Remove multiple \n & Remove blank lines
        $lyrics = str_replace("\r", "", $lyrics);
        for ($i = 20; $i > 1; $i--) {
            $lyrics = str_replace(str_repeat("\n", $i), "\n", $lyrics);
        }

        // Remove blank lines
        //foreach ($lyrics as $line)
        //    if ($line == "" || $line == "\n" || $line == "\r" || $line == "\r\n" || $line == " \n" || $line == " ")
        //        unset($lyrics[$line]);

        // Split lines which are over 10 words
        /*$c = 0;
        for ($i = 0; $i < strlen($lyrics); $i++) {
            if ($lyrics[$i] == "\n") {
                $c = 0;
                continue;
            }
            if ($lyrics[$i] == ' ') {
                $c++;
            }
            if ($c >= 10) {
                $lyrics[$i] = "\n";
                $c = 0;
                continue;
            }
        }*/

        return $lyrics;
    }

?>