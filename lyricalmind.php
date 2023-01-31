<?php

    //namespace LyricalMind;

    require_once __DIR__ . '/src/utils.php';
    require_once __DIR__ . '/src/scrapper/AZ.php';
    require_once __DIR__ . '/src/scrapper/P2C.php';

    class LyricalMind
    {
        public function __construct()
        {
        }

        /**
         * Return lyrics of a song
         * @param string $artists
         * @param string $title
         * @return string|false
         */
        static function GetLyrics($artists, $title) {
            $lyrics = false;
            $scrapFuncs = array(
                'GetLyricsFromAZ',
                'GetLyricsFromP2C'
            );

            foreach ($scrapFuncs as $source) {
                $lyrics = $source($artists, $title);
                if ($lyrics !== false) break;
            }

            return $lyrics;
        }
    }

?>