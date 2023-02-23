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
     * @var string $cleanText
     */
    public $cleanText;

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
        $this->cleanText = CleanText($word['text']);
    }

    /**
     * Word to compare
     * @param string $word
     * @return int Levenshtein distance
     */
    public function compare($word) {
        return levenshtein($this->cleanText, $word, 2, 1, 2);
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
     * @param string $filepath
     * @return string|false
     */
    public function UploadFile($filepath) {
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
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo 'cURL Error #:' . $err;
            return false;
        }

        return json_decode($response, true)['upload_url'];
    }

    /**
     * @param string $url
     * @param string $languageCode
     * @return string|false Transcript ID
     */
    public function SubmitAudioFile($url, $languageCode = 'en_us') {
        if (!$url) return false;

        $curl = curl_init();
        $data = array(
            'audio_url' => $url,
            'language_code' => $languageCode
        );
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.assemblyai.com/v2/transcript',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [ "authorization: {$this->API_KEY}" ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo('cURL Error #:' . $err);
            return false;
        }

        $response = json_decode($response, true);
        if (!checkDict($response, 'id')) {
            echo('AssemblyAI: Invalid response (no ID)');
            return false;
        }

        return $response['id'];
    }

    /**
     * @param string $transcriptID
     * @return (AssemblyAIWord|string|false)[] Object with transcript data and error message
     */
    public function GetTranscript($transcriptID, $wait = true) {
        if (!$transcriptID) return false;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.assemblyai.com/v2/transcript/$transcriptID",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [ "authorization: {$this->API_KEY}" ],
        ]);

        $status = null;
        $response = false;
        $error = false;
        while ($status === null || $status === 'queued' || $status === 'processing') {
            // Get transcript status
            $response = curl_exec($curl);
            $err = curl_error($curl);
            if ($err) {
                $error = 'AssemblyAI cURL Error #:' . $err;
                return false;
            }

            $response = json_decode($response, true);
            if ($response === null || !isset($response['status'])) {
                $error = 'AssemblyAI error: Invalid response';
                return false;
            }

            $status = $response['status'];

            // Check errors
            if ($status === 'error') {
                $error = 'AssemblyAI error: ' . $response['error'];
                return false;
            }

            // Wait for the transcript to be ready
            if ($status === 'queued' || $status === 'processing') {
                sleep(4);
            }
        }
        curl_close($curl);

        if ($status === 'success' && $error === false) {
            $convertFunc = fn($mixedWord) => new AssemblyAIWord($mixedWord);
            $response = array_map($convertFunc, $response);
        }

        return array($response, $error);
    }
}

?>