<?php


class VerseTimecode {
    /** @var 'ok'|'error' $status Verse status */
    public $status;

    /** @var float $start Time in seconds */
    public $start;

    /** @var float $end Time in seconds */
    public $end;

    /**
     * VerseTimecode constructor
     * @param 'ok'|'error' $status Verse status
     * @param int $start Time in seconds
     * @param int $end Time in seconds
     */
    public function __construct($status, $start, $end) {
        $this->status = $status;
        $this->start = $start;
        $this->end = $end;
    }
}

class LyricalMindOutput {
    /**
     * Status of the request (success or error, see $error)
     * @var 'success'|'error'
     */
    public $status = 'success';

    /**
     * Status code of the request
     * - 0 => Success
     * - 1 => Lyrics not found
     * - 2 => Spotidy song not foud
     * - 3 => Song not downloaded
     * - 4 => Song not spleeted
     * - 5 => Speech to text failed
     * - 6 => Lyrics not synced
     * @var int
     */
    public $status_code = 0;

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
     * Key of the song
     * @var float
     */
    public $bpm = 0.0;

    /**
     * Key of the song
     * @var int
     */
    public $key = 0;

    /**
     * Mode of the song
     * @var int
     */
    public $mode = 0;

    /**
     * Lyrics of the song (array of verses)
     * @var string|false
     */
    public $lyrics = false;

    /**
     * Timecodes of the song (array of timecodes)
     * @var VerseTimecode[]|false
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