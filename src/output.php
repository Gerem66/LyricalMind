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
     * - 1 => Spotify song not found
     * - 2 => Lyrics not found
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
     * @var string|false
     */
    public $title = false;

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
     * Duration of the song in milliseconds
     * @var int
     */
    public $duration = 0;

    /**
     * Lyrics of the song (array of verses)
     * @var array|false
     */
    public $lyrics = false;

    /**
     * Timecodes of the song (array of timecodes)
     * @var VerseTimecode[]|false
     */
    public $timecodes = false;

    /**
     * Start time of the request (temp variable)
     * @var float
     */
    private $start_time = 0.0;

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

    /**
     * LyricalMindOutput constructor
     */
    public function __construct() {
        $this->start_time = microtime(true);
    }

    public function __toString() {
        // Set total time
        $this->total_time = microtime(true) - $this->start_time;

        // Remove start time
        $start_time = $this->start_time;
        unset($this->start_time);

        // Return json encoded object
        $output = json_encode($this);

        // Reset start time
        $this->start_time = $start_time;

        // Check if json_encode failed
        if ($output === false) {
            // Return error
            return json_encode(array(
                'status' => 'error',
                'status_code' => 0,
                'error' => 'LyricalMind: JSON encoding failed'
            ));
        }

        // Return output
        return $output;
    }

    /**
     * Set status of the request
     * @param 'success'|'error' $status Status of the request
     * @param int $status_code Status code of the request (see class description)
     * @param string|false $error Error message if status is error
     * @return LyricalMindOutput $this
     */
    public function SetStatus($status = 'success', $status_code = 0, $error = false) {
        $this->status = $status;
        $this->status_code = $status_code;
        $this->error = $error;
        return $this;
    }
}

?>