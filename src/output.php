<?php

// TODO: Remplacer le json de LyricalMind par un objet PHP (et dont le toString encode en json pour avoir un texte exploitable)

class LyricalMindOutput {
    /**
     * Status of the request (success or error, see $error)
     * @var 'success'|'error'
     */
    public $status = 'success';

    /**
     * Error message if status is error
     * @var string|false
     */
    public $error = false;

    /**
     * Spotify ID of the song
     * @var string|false
     */
    public $id = false;

    /**
     * Artists of the song
     * @var string[]
     */
    public $artists = array();

    /**
     * Title of the song
     * @var string
     */
    public $title = '';

    /**
     * Lyrics of the song (array of verses)
     * @var string|false
     */
    public $lyrics = false;

    /**
     * Timecodes of the song (array of timecodes)
     * @var Timecode[]|false
     */
    public $timecodes = false;

    /**
     * Total time of the request
     * @var float
     */
    public $total_time = 0.0;

    /**
     * Source of the lyrics
     * @var string|false
     */
    public $lyrics_source = false;

    /**
     * Source of the spleeted voice (audio file)
     * @var string|false
     */
    public $voice_source = false;

    public function __toString() {
        return json_encode($this);
    }
}

?>