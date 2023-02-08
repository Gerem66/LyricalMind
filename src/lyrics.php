<?php

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
    public $verses = array();
    public $versesCount = 0;

    public function __construct($text) {
        $versesRaw = explode("\n\n", $text);
        foreach ($versesRaw as $verseRaw) {
            $lines = explode("\n", $verseRaw);
            array_push($this->verses, $lines);
            $this->versesCount++;
        }
    }

    public function __toString() {
        $output = '';
        foreach ($this->verses as $verse) {
            foreach ($verse as $line) {
                $output .= $line . "\n";
            }
        }
        return $output;
    }

    public function GetVersesCount() {
        return $this->versesCount;
    }

    private function formatText($text) {
        $text = strtolower($text);
        return preg_replace('/[[:punct:]]/', '', $text);
    }

    /**
     * Get precise word from reference words
     * @param array $referenceWords Reference words from AssemblyAI
     * @param array $lineWords Line to search for the word
     * @param int $referenceWordsIndex Index of the current word in reference words
     * @return int|false Index of the word
     */
    private function getPreciseWord($referenceWords, $referenceWordsIndex, $lineWords) {
        $lastWord = $lineWords[count($lineWords) - 1];
        //print_r("Last word: $lastWord\n");
        $offset = intval(count($lineWords) * .5);
        //print_r("Max offset: $offset\n");
        $words = array_column($referenceWords, 'text');
        $words = array_splice($words, $referenceWordsIndex + $offset, 2 * $offset);
        //print_r("Words: " . implode(' ', $words) . "\n");
        $index = array_search($lastWord, $words);
        if ($index !== false) {
            $index += $offset;
        }
        return $index;
    }

    private function getApproximativeWord($referenceWords, $referenceWordsIndex, $lineWords) {
        // 1. Soit X la moitié du nombre de mots dans la ligne
        $lineLength = count($lineWords);
        $offset = intval($lineLength * .4);
        //print_r("Max offset: $offset\n");

        // 4. Sinon, on recommence le point 2 avec le mot précédent (puis on l'ajoutera au résultat)
        for ($start = 0; $start < $offset; $start++) {
            //$test = array_column($referenceWords, 'text');
            //print_r("ASearch: " . implode(' ', array_slice($test, $referenceWordsIndex + $lineLength - $offset, 2 * $offset)) . "\n");

            // 2. Chercher la distance de levenshtein la plus petite entre X et 3X
            $minDistance = 999;
            $minDistanceIndex = false;
            for ($i = -$offset; $i < $offset; $i++) {
                if ($referenceWordsIndex + $lineLength + $i < 0 ||
                    $referenceWordsIndex + $lineLength + $i >= count($referenceWords)) {
                    break;
                }
                $word = $referenceWords[$referenceWordsIndex + $lineLength + $i]['text'];
                $lastWord = $lineWords[$lineLength - $start - 1];
                $distance = levenshtein($word, $lastWord);
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $minDistanceIndex = $i;
                }
            }
    
            // 3. Si la distance est inférieure à 3, on a trouvé le mot
            $index = $minDistanceIndex + $lineLength + $start;
            if ($index >= 0 && $minDistance < 2) {
                return $index;
            }
        }
        return false;
    }

    /**
     * Sync lyrics with AssemblyAI results
     * @param array $referenceWords Reference words from AssemblyAI
     * @return Timecode[]
     */
    public function SyncSongStructure($referenceWords) {
        foreach ($referenceWords as $key => $word) {
            $referenceWords[$key]['text'] = $this->formatText($word['text']);
        }

        $structure = array();
        $referenceWordsIndex = 0;

        // Find timecodes of verses from reference lyrics (Match lyrics with AssemblyAI results)
        foreach ($this->verses as $key => $verse) {
            $startWord = $referenceWords[$referenceWordsIndex];

            foreach ($verse as $line) {
                $lineWords = explode(' ', $line);

                // Precise word
                //print_r("Search: $line\n");
                $index = $this->getPreciseWord($referenceWords, $referenceWordsIndex, $lineWords);
                //print_r("Precise word: $index\n");

                // Approximate word
                if ($index === false) {
                    $index = $this->getApproximativeWord($referenceWords, $referenceWordsIndex, $lineWords);
                    //print_r("Approximative word: $index\n");
                }

                // Warn
                if ($index === false) {
                    //$testLine = implode(' ', array_splice(array_column($referenceWords, 'text'), $referenceWordsIndex, 15));
                    //echo("Warn: Can't find word in reference words: $line ($referenceWordsIndex - $testLine)\n");
                    //$referenceWordsIndex += count($lineWords);
                    continue;
                }

                $tempLine = array_slice($referenceWords, $referenceWordsIndex, $index + 1);
                $tempLine = array_column($tempLine, 'text');
                $tempLine = implode(' ', $tempLine);
                //echo("[Verse $key] $line | $tempLine\n");
                $referenceWordsIndex += $index + 1;
            }

            if ($referenceWordsIndex === 0) {
                //echo("Warn: Can't find any word in reference words\n");
                continue;
            }

            $endWord = $referenceWords[$referenceWordsIndex - 1];
            //print_r($endWord['text'] . "\n");
            $newStruct = array(
                'verse' => $key,
                'start' => $startWord['start'],
                'end' => $endWord['end']
            );
            array_push($structure, $newStruct);
        }
        return $structure;
    }
}

?>