<?php

    //namespace LyricalMind;

    require_once __DIR__ . '/src/utils.php';
    require_once __DIR__ . '/src/scrapper/AZ.php';
    require_once __DIR__ . '/src/scrapper/P2C.php';

    class LyricalMind
    {
        private $assemblyai = false;

        public function __construct($sync = false) {
            if ($sync) {
                $this->assemblyai = new AssemblyAI('25a1a28d61794482a92b99f6f26f1dff');
            }
        }

        /**
         * Return lyrics of a song
         * @param string $artists
         * @param string $title
         * @param bool $syncLyrics  If true, will try to sync lyrics with AssemblyAI,
         *                          and return lyrics from speech recognition if lyrics are not found
         * @return string|false
         */
        static function GetLyrics($artists, $title, $syncLyrics = false) {
            $output = array(
                'status' => 'success',
                'artists' => $artists,
                'title' => $title,
                'lyrics' => false
            );

            // Check database
            // TODO

            // Scrapper
            $output['lyrics'] = scrapper($artists, $title);
            if ($output['lyrics'] === false) {
                $output['status'] = 'error';
                $output['error'] = 'Lyrics not found';
                return $output;
            }

            if ($syncLyrics) {
                // Download audio
                // TODO

                // Spleet audio
                // TODO

                // Sort instrumental & vocals
                // TODO

                // Speech recognition on vocals with AssemblyAI
                // TODO

                // Sync lyrics with speech recognition
                // TODO

                // Save lyrics in database
                // TODO

                // Return lyrics
                // TODO
            }

            return $output;
        }
    }

?>