<?php

// {"text":"Still","start":25010,"end":25126,"confidence":0.84123,"speaker":null}

class AssemblyAIWord {
    /**
     * Text recognized by AssemblyAI
     * @var string $text
     */
    public $text;

    /**
     * Clean (lower & ponctuation removed) text recognized by AssemblyAI
     * @var string $clean_text
     */
    public $clean_text;

    /**
     * Start of text in ms
     * @var int $start
     */
    public $start;

    /**
     * End of text in ms
     * @var int $end
     */
    public $end;

    /**
     * Confidence of text defined by AssemblyAI
     * @var double $confidence
     */
    public $confidence;

    /**
     * Speaker of text. Default: null
     * @var string|null $speaker
     */
    public $speaker = null;

    public function __construct($word) {
        foreach ($word as $key => $value) {
            $this->{$key} = $value;
        }
        $this->clean_text = CleanText($word['text']);
    }

    /**
     * Search for a word in the lyrics
     * @param AssemblyAIWord[] $reference_words
     * @param string $word
     * @param int $start_index Start index of reference words (positive integer)
     * @param int|false $end_index End index of reference words (positive integer or false if no end)
     * @return int|false Return absolute index of word in reference words or false if not found
     */
    public static function search($reference_words, $word, $start_index = 0, $end_index = false) {

        // Check if indexes are valid
        $refWordsLength = count($reference_words);
        if ($start_index < 0) {
            $start_index = 0;
        }
        if ($start_index >= $refWordsLength) {
            $start_index = $refWordsLength - 1;
        }
        if ($end_index !== false && $end_index < 0) {
            $end_index = 0;
        }
        if ($end_index === false || $end_index >= $refWordsLength) {
            $end_index = $refWordsLength - 1;
        }

        // Define search range
        $average = ($start_index + $end_index) / 2;
        $firstValue = floor($average);
        $lastValue = ($average % 1 !== 0) ? $end_index + 1 : $start_index - 1;

        // Define increment function (positive/negative: 1, -2, 3, -4...)
        $incrementValue = 1;
        $resetIncrementValue = function() use (&$incrementValue) { $incrementValue = 1; };
        $increment = function(&$i) use (&$incrementValue) {
            $i += $incrementValue;
            $incrementValue = $incrementValue > 0 ? -$incrementValue - 1 : -$incrementValue + 1;
        };

        // Precise search
        $resetIncrementValue();
        for ($i = $firstValue; $i != $lastValue; $increment($i)) {
            if ($i < 0 || $i >= $refWordsLength) continue;

            $refWord = $reference_words[$i];
            if ($refWord->clean_text === $word) {
                return $i;
            }
        }

        // Approximate search
        $minKey = false;
        $minDistance = 99;
        $resetIncrementValue();
        for ($i = $firstValue; $i != $lastValue; $increment($i)) {
            if ($i < 0 || $i >= $refWordsLength) continue;

            $refWord = $reference_words[$i];
            $distance = $refWord->compare($word);
            if ($distance < $minDistance) {
                $minKey = $i;
                $minDistance = $distance;
            }
        }

        // Not found
        if ($minKey === false || $minDistance > strlen($word) / 2) {
            return false;
        }

        return $minKey;
    }

    /**
     * Word to compare
     * @param string $word
     * @return int Levenshtein distance
     */
    public function compare($word) {
        return levenshtein($this->clean_text, $word, 1, 2, 2);
    }
}

class AssemblyAI {
    private string $API_KEY = '';

    /**
     * AssemblyAI constructor.
     * @param string $API_KEY
     */
    public function __construct($API_KEY) {
        $this->API_KEY = $API_KEY;
    }

    /**
     * Upload audio file to AssemblyAI and return upload URL
     * @param string $filepath Path to audio file
     * @param int|null $http_status HTTP status code
     * @return string|false
     */
    public function UploadFile($filepath, &$http_status = null) {
        if (!$filepath || !file_exists($filepath)) {
            return false;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.assemblyai.com/v2/upload',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => file_get_contents($filepath),
            CURLOPT_HTTPHEADER => [ "authorization: {$this->API_KEY}" ],
        ]);

        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_status !== 200) {
            return false;
        }

        return json_decode($response, true)['upload_url'];
    }

    /**
     * @param string $url Upload URL
     * @param string $language_code Language code (default: en_us)
     * @param int|null $http_status HTTP status code
     * @return string|false Transcript ID
     */
    public function SubmitAudioFile($url, $language_code = 'en_us', &$http_status = null) {
        if (!$url) return false;

        $curl = curl_init();
        $data = array(
            'audio_url' => $url,
            'language_code' => $language_code
        );
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.assemblyai.com/v2/transcript',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [ "authorization: {$this->API_KEY}" ],
        ]);

        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_status !== 200) {
            return false;
        }

        $response = json_decode($response, true);
        if (!checkDict($response, 'id')) {
            return false;
        }

        return $response['id'];
    }

    /**
     * Check if transcript is ready
     * @param string $transcript_id Transcript ID
     * @param 'queued'|'processing'|'error'|'completed' $state Transcript state
     * @return mixed|false AssemblyAI response (or false on error)
     */
    public function GetTrancscriptState($transcript_id, &$state) {
        if (!$transcript_id) return false;

        // Init curl
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.assemblyai.com/v2/transcript/$transcript_id",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [ "authorization: {$this->API_KEY}" ],
        ]);

        // Get transcript status
        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Response error
        if ($http_status !== 200) {
            $state = 'error';
            return false;
        }

        // Invalid response
        $response = json_decode($response, true);
        if ($response === null || !isset($response['status'])) {
            $state = 'error';
            return false;
        }

        // Return state
        $state = $response['status'];
        return $response;
    }

    /**
     * Wait for transcript to be ready
     * @param string $transcript_id
     * @param string|false $error
     * @return AssemblyAIWord|false Object with transcript data and error message
     */
    public function WaitTranscript($transcript_id, &$error = null) {
        if (!$transcript_id) return false;

        $status = null;
        $response = false;
        while ($status === null || $status === 'queued' || $status === 'processing') {
            // Get transcript status
            $response = $this->GetTrancscriptState($transcript_id, $status);

            // Check errors
            if ($status === 'error' || $response === false) {
                return false;
            }

            // Wait for the transcript to be ready
            if ($status === 'queued' || $status === 'processing') {
                sleep(4);
            }
        }

        if ($status !== 'completed') {
            $error = "AssemblyAI error: Invalid status ($status)";
            return false;
        }

        $convertFunc = fn($mixedWord) => new AssemblyAIWord($mixedWord);
        $response = array_map($convertFunc, $response['words']);

        return $response;
    }
}

?>