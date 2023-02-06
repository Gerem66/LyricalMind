<?php

class Line {
    public $content = '';
    public $mode = 'default';

    public function __construct($content, $mode) {
        $this->content = $content;
        $this->mode = $mode;
    }

    public function __toString() {
        return $this->content;
    }

    public function PrintDebug() {
        echo "[{$this->mode}] {$this->content}\n";
    }
}

class Timecode {
    public $type;
    public $start;
    public $end;

    public function __construct($type, $start, $end) {
        $this->type = $type;
        $this->start = $start;
        $this->end = $end;
    }
}

class Lyrics {
    /** @var Line[] */
    public $lines = [];
    public $versesCount = 0;
    public $history = [];

    public function __construct($text) {
        $this->lines = $this->__parse($text);
    }

    public function __toString() {
        return implode("\n", array_map(function($line) { return $line->content; }, $this->lines));
    }

    private function __parse($text) {
        $lyrics = [];
        $lines = explode("\n", $text);
        $this->history = ['default'];

        function getMode($line) {
            $allModes = ['default', 'title', 'intro', 'verse', 'chorus', 'outro', 'bridge', 'interlude', 'pre-chorus', 'hook'];
            foreach ($allModes as $mode) {
                $parenthesis = strpos($line, '(') !== false && strpos($line, ')') !== false;
                $crochets = strpos($line, '[') !== false && strpos($line, ']') !== false;
                $endOfLine = substr($line, -1) === ':';
                if (strpos($line, $mode) !== false && strlen($line) <= strlen($mode) + 5) {
                    return $mode;
                }
                if (strpos($line, $mode) !== false && ($parenthesis || $crochets || $endOfLine)) {
                    return $mode;
                }
            }
            return null;
        }

        foreach ($lines as $l) {
            $line = strtolower(trim($l));
            if (!$line || substr($line, 0, 7) === '(title)') {
                continue;
            }
            $mode = getMode($line);
            if ($mode) {
                $modeCount = 1;
                foreach ($this->history as $historyMode) {
                    if (substr($historyMode, 0, strlen($mode)) === $mode) {
                        $modeCount++;
                    }
                }
                $this->history[] = $mode . $modeCount;
                $this->versesCount++;
                continue;
            }
            $lyrics[] = new Line($line, end($this->history));
        }
        return $lyrics;
    }

    public function PrintDebug() {
        foreach ($this->lines as $line) {
            $line->PrintDebug();
        }
    }

    public function GetStructure() {
        return array_slice($this->history, 1);
    }

    public function GetVersesCount() {
        return $this->versesCount;
    }

    public function GetVerses($index) {
        $keepVerse = fn($line) => str_starts_with($line->mode, $index);
        $lines = array_filter($this->lines, $keepVerse);
        $verse = join("\n", array_map(fn($line) => $line->content, $lines));
        return $verse;
    }

    /**
     * @param AssemblyAI $assemblyAI
     * @param string $referenceAudioPath
     * @return Timecode[]
     */
    function SyncSongStructure($assemblyAI, $referenceAudioPath) {
        $couplets = array();

        // Get timecodes from audio reference
        $referenceWords = null;
        $audio_url = $assemblyAI->UploadFile($referenceAudioPath);
        $transcriptID = $assemblyAI->SubmitAudioFile($audio_url, 'en_US');
        list($result, $error) = $assemblyAI->GetTranscript($transcriptID);
        if ($result === false || $error !== false) {
            echo("Error transcribing file: $error");
            return false;
        }
        $referenceWords = $result['words']; // Or 'text'

        // Find timecodes of couplets from reference lyrics (Match lyrics with AssemblyAI results)
        $referenceWordsIndex = 0;
        foreach ($this->GetStructure() as $c) {
            $startWord = $referenceWords[$referenceWordsIndex];
            $couplet = explode("\n", $this->GetVerses($c, true));
            foreach ($couplet as $line) {
                $lineWords = explode(' ', $line);

                $lastWordOffset = -1;
                function GetLastWord() {
                    global $lineWords, $lastWordOffset;
                    return $lineWords[-($lastWordOffset + 1)];
                }

                $i = 0;
                $fail = false;
                while ($fail || $i === 0 || $i >= 20) {
                    $fail = false;
                    $lastWordOffset += 1;
                    // Precise word
                    try {
                        $words = array_column($referenceWords, 'text', $referenceWordsIndex);
                        $i = array_search(GetLastWord(), $words) + 1;
                    } catch (Exception $e) {
                    }

                    // Approximate word
                    if ($i === 0 || $i > 20) {
                        $i = count(explode(' ', $line)) - 3;
                        while ($i < 20 && levenshtein(GetLastWord(), $referenceWords[$referenceWordsIndex + $i]['text']) > 3) {
                            $i += 1;
                            if ($referenceWordsIndex + $i >= count($referenceWords)) {
                                $fail = true;
                                break;
                            }
                        }
                        $i += 1;
                    }
                }
                $i += $lastWordOffset;
                $referenceWordsIndex += $i;
            }
            $endWord = $referenceWords[$referenceWordsIndex - 1];
            $couplets[] = array('c' => $c, 'start' => $startWord['start'], 'end' => $endWord['end']);
        }
        return $couplets;
    }
}

?>