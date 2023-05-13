<?php

class STTWord {
    /**
     * Text recognized by WhisperX
     * @var string $text
     */
    public $text;

    /**
     * Clean (lower & ponctuation removed) text recognized by WhisperX
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
     * Confidence of text defined by WhisperX
     * @var double $score Confidence between 0 and 1
     */
    public $score;

    public function __construct($word) {
        $this->text = $word['word'];
        $this->clean_text = CleanText($this->text);
        $this->start = intval($word['start'] * 1000);
        $this->end = intval($word['end'] * 1000);
        $this->score = $word['score'];
    }

    /**
     * Search for a word in the lyrics
     * @param STTWord[] $reference_words
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

/**
 * @param string $filepath Path to audio file
 * @return STTWord[]|false Array of words or false on failure
 */
function SpeechToText($filepath) {
    $name = pathinfo($filepath, PATHINFO_FILENAME);
    $command = "whisperx --compute_type float32 --highlight_words True --output_format=json --output_dir /tmp/$name $filepath";

    # Run command
    bash($command, hide: true);
    if (!file_exists("/tmp/$name/$name.json")) return false;

    # Get JSON
    $json = file_get_contents("/tmp/$name/$name.json");
    if ($json === false) return false;

    # Decode JSON
    $data = json_decode($json, true);
    if ($data === null || !isset($data['word_segments'])) return false;

    # Convert to STTWord
    $convertFunc = fn($mixedWord) => new STTWord($mixedWord);
    $words = array_map($convertFunc, $data['word_segments']);

    # Remove temporary files
    unlink("/tmp/$name/$name.json");
    rmdir("/tmp/$name");

    return $words;
}

?>