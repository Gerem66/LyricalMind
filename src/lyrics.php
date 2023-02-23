<?php

class Timecode {
    /**
     * Verse content
     * @var string $line
     */
    public $line;

    /**
     * Index of first word from WordReference (AssemblyAI result)
     * @var int $start
     */
    public $start;

    /**
     * Index of last word from WordReference (AssemblyAI result)
     * @var int $end
     */
    public $end;

    /**
     * Timecode constructor
     * @param string $line Verse content
     * @param int $start Index of first word from WordReference (AssemblyAI result)
     * @param int $end Index of last word from WordReference (AssemblyAI result)
     */
    public function __construct($line, $start, $end) {
        $this->line = $line;
        $this->start = $start;
        $this->end = $end;
    }
}

class Lyrics {
    private $verses = array();
    private $versesClean = array();
    public $versesCount = 0;
    public $linesCount = 0;
    public $wordsCount = 0;

    public function __construct($text) {
        $versesRaw = explode("\n\n", $text);
        foreach ($versesRaw as $verseRaw) {
            // Split verse into lines
            $lines = explode("\n", $verseRaw);

            // Remove parenthesis at the end of the line
            $linesClean = array_map(function($line) {
                $line = trim($line);
                $line = preg_replace('/\([^)]+\)$/', '', $line);
                return $line;
            }, $lines);

            // Add verse to the list
            array_push($this->verses, $lines);
            array_push($this->versesClean, $linesClean);

            // Update stats
            $this->versesCount++;
            $this->linesCount += count($lines);
            foreach ($lines as $line) {
                $words = explode(' ', $line);
                $this->wordsCount += count($words);
            }
        }
    }

    public function __toString() {
        $output = '';
        foreach ($this->verses as $line) {
            foreach ($line as $line) {
                $output .= $line . "\n";
            }
        }
        return $output;
    }

    public function GetVerses() {
        return $this->verses;
    }

    public function GetVersesCount() {
        return $this->versesCount;
    }

    /**
     * @param AssemblyAIWord[] $referenceWords Reference words from AssemblyAI
     * @return Timecode[]|false Synchronized lyrics line by line
     */
    public function SyncStructure($referenceWords) {
        $DEBUG = false;
        $DEBUG_RESULT = 'live'; // all, times, live or none
        if ($DEBUG) {
            echo('Total ref words: ' . count($referenceWords) . "\n");
        }

        /** @var Timecode[] */
        $timecodes = array();

        // 0. Count words from AssemblyAI reference and define average words per line
        $refWordsCount = count($referenceWords);
        $averageWordsInLine = floor($refWordsCount / $this->linesCount);
        if (count($referenceWords) === 0) {
            return false;
        }

        // 0. Check words length, return if delta more than 50% difference
        $maxLength = max($this->wordsCount, $refWordsCount);
        $delta = abs($this->wordsCount - $refWordsCount);
        if ($delta > $maxLength * .5) {
            return false;
        }

        // 1. Set default structure - average words per line
        $start = 0;
        for ($i = 0; $i < $this->GetVersesCount(); $i++) {
            $linesCount = count($this->versesClean[$i]);
            for ($j = 0; $j < $linesCount; $j++) {
                $line = $this->versesClean[$i][$j];
                $end = $start + $averageWordsInLine;
                $timecode = new Timecode($line, $start, $end);

                array_push($timecodes, $timecode);
                $start += $averageWordsInLine + 1;
            }
        }

        // 2. Corrections - Adjust start and end
        for ($offset = 10; $offset >= 10; $offset--) { // >= 3
            for ($l = 0; $l < $this->linesCount; $l++) {
                $line = $timecodes[$l]->line;
                $words = explode(' ', $line);
                $words = array_map('CleanText', $words);

                if ($DEBUG) {
                    echo("----------\n");
                    echo("Line index: $l\n");
                    echo("Offset: $offset\n");
                    print_r($timecodes[$l]);
                    echo("Words: " . implode(' ', $words) . "\n");
                }
                if ($timecodes[$l]->start > count($referenceWords) ||
                    $timecodes[$l]->end > count($referenceWords))
                        break;

                $firstRefWord = $referenceWords[$timecodes[$l]->start];
                $lastRefWord = $referenceWords[$timecodes[$l]->end];

                // Adjust start
                $indexMax = count($referenceWords) - 1;
                $indexStart = 0;
                $indexEnd = $indexMax;
                if ($l !== 0)
                    $indexStart = $timecodes[$l - 1]->end + 1;
                if ($l !== $indexMax)
                    $indexEnd = $timecodes[$l + 1]->start - 1;
                if ($indexStart + $offset > $indexMax)
                    $indexStart = $indexMax - $offset;

                $foundStart = false;
                $newIndex = 0;
                $firstRefWord = $referenceWords[$indexStart];
                for ($w = 0; $w < count($words); $w++) {

                    // Search first - Precise word
                    $firstWordLine = CleanText($words[$w]);
                    if ($firstWordLine !== $firstRefWord->cleanText) {
                        $nearWords = GetRefWordsFromIndex($referenceWords, $indexStart, $offset);
                        $nearWords = array_column($nearWords, 'cleanText');

                        if ($DEBUG) {
                            $txtNearWords = join(', ', $nearWords);
                            echo("Near words [f: $firstWordLine] ($indexStart +/- $offset): $txtNearWords\n");
                        }

                        $newIndexOffset = array_search($firstWordLine, $nearWords);
                        if ($newIndexOffset !== false) {
                            $newIndex = $indexStart + $newIndexOffset - $offset;
                            if ($DEBUG) echo("New index offset: $newIndexOffset\n");

                            if ($newIndex < 0 || $newIndexOffset === $offset) {
                                if ($DEBUG) echo("Nothing\n");
                                continue;
                            }

                            $foundStart = true;
                            $timecodes[$l]->start = max(0, $newIndex - $w);
                            if ($DEBUG) echo("Precise start found: {$indexStart} => {$timecodes[$l]->start}\n");
                            break;
                        } else {
                            if ($DEBUG) echo("Not found!\n");
                        }
                    }
                }



                $foundEnd = false;
                for ($w = 0; $w < count($words); $w++) {
                    // Search last - Precise word
                    if (count($words) - $w < 0 || count($words) - $w >= count($words))
                        continue;
                    $lastWordLine = CleanText($words[count($words) - $w]);
                    if ($lastWordLine !== $lastRefWord->cleanText) {
                        $nearWords = GetRefWordsFromIndex($referenceWords, $indexEnd, $offset);
                        $nearWords = array_column($nearWords, 'cleanText');

                        if ($DEBUG) {
                            $txtNearWords = join(', ', $nearWords);
                            echo("Near words [l: $lastWordLine] ($indexEnd +/- $offset): $txtNearWords\n");
                        }

                        // Search from last
                        $nearWords = array_reverse($nearWords);
                        $newIndexOffset = array_search($lastWordLine, $nearWords);
                        if ($newIndexOffset !== false) {
                            $newIndexOffset = count($nearWords) - $newIndexOffset - 1;
                            $newIndex = $indexEnd + $newIndexOffset - $offset;
                            if ($DEBUG) echo("New index offset: $newIndexOffset\n");

                            if ($newIndex < 0 || $newIndexOffset === $offset) {
                                if ($DEBUG) echo("Nothing\n");
                                continue;
                            }

                            $foundEnd = true;
                            $timecodes[$l]->end = max(0, $newIndex + $w);
                            if ($DEBUG) echo("Precise end found: {$indexEnd} => {$timecodes[$l]->start}\n");
                            break;
                        } else {
                            if ($DEBUG) echo("Not found!\n");
                        }
                    }
                }

                if ($foundStart === false && $foundEnd === false) {
                    if ($DEBUG) echo("Nothing found\n");
                    $timecodes[$l]->start = 0;
                    $timecodes[$l]->end = 0;
                } else if (($foundStart && !$foundEnd) || $timecodes[$l]->end < $timecodes[$l]->start) {
                    if ($DEBUG) echo("Start found, end not found\n");
                    $timecodes[$l]->end = $timecodes[$l]->start + count($words);
                } else if ((!$foundStart && $foundEnd) || $timecodes[$l]->start < $timecodes[$l]->end) {
                    if ($DEBUG) echo("End found, start not found\n");
                    $timecodes[$l]->start = $timecodes[$l]->end - count($words);
                }
            }
        }

        // Search first word (offset +/- .5l)
        // Search last word (offset +/- .5l)

        // Print result
        //if ($DEBUG) print_r($timecodes);

        // Print line sync & timecodes line by line
        if ($DEBUG_RESULT === 'all' || $DEBUG_RESULT === 'times') {
            for ($i = 0; $i < count($timecodes); $i++) {
                //if ($i === 10) break; // TODO: Remove
                $timecode = $timecodes[$i];
                $firstIndex = $timecode->start;
                $lastIndex = $timecode->end;
                if ($firstIndex >= count($referenceWords) || $lastIndex >= count($referenceWords))
                    break;
                if ($firstIndex < 0 || $lastIndex < 0)
                    continue;

                $txtIndex = str_pad($i, 3, ' ', STR_PAD_LEFT);
                $txtFirstTime = round($referenceWords[$firstIndex]->start / 1000, 2);
                $txtLastTime = round($referenceWords[$lastIndex]->end / 1000, 2);    
                $txtTimecodes = str_pad("Line $txtIndex: $txtFirstTime - $txtLastTime", 30, ' ', STR_PAD_RIGHT);

                $refWords = array_slice($referenceWords, $firstIndex, $lastIndex - $firstIndex);
                $refLine = join(' ', array_column($refWords, 'text'));
                $txtLine = str_pad($timecode->line, 70, ' ', STR_PAD_RIGHT);
                $txtRefLine = str_pad($refLine, 70, ' ', STR_PAD_RIGHT);
                echo("$txtTimecodes    $txtLine| $txtRefLine\n");
            }
        }

        // Print line by line in live
        if ($DEBUG_RESULT === 'all' || $DEBUG_RESULT === 'live') {
            $currTime = 0;
            for ($i = 0; $i < count($timecodes); $i++) {
                $timecode = $timecodes[$i];
                $firstIndex = $timecode->start;
                $lastIndex = $timecode->end;

                // Define duration to the next line
                $firstTime = $referenceWords[$firstIndex]->start;
                $duration = $firstTime - $currTime;

                // Check
                if ($duration <= 0) {
                    echo("Warning: Duration < 0 (line start before previous one)\n");
                    //return;
                } else {
                    // Wait
                    usleep($duration * 1000);
                    $currTime += $duration;
                }

                // Print
                echo($timecode->line . "\n");
            }
        }
    }
}

?>