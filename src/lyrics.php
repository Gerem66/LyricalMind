<?php

class Timecode {
    /**
     * Verse content
     * @var string $line
     */
    public $line;

    /**
     * Index of first word from WordReference (WhisperX result)
     * @var int $start
     */
    public $start;

    /**
     * Index of last word from WordReference (WhisperX result)
     * @var int $end
     */
    public $end;

    /**
     * True if the timecode is defined with precise timecode
     * @var bool $definitive
     */
    public $definitive = false;

    /**
     * Timecode constructor
     * @param string $line Verse content
     * @param int $start Index of first word from WordReference (WhisperX result)
     * @param int $end Index of last word from WordReference (WhisperX result)
     */
    public function __construct($line, $start, $end) {
        $this->line = $line;
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Get timecode as object (for JSON)
     * @param STTWord[] $refWords List of words from WordReference (WhisperX result)
     * @return object|null Timecode object or null if one of the reference word is null
     */
    public function GetTimecode($refWords) {
        $firstRefWord = $refWords[$this->start];
        $lastRefWord = $refWords[$this->end];
        return array(
            'line' => $this->line,
            'start' => $firstRefWord->start / 1000,
            'end' => $lastRefWord->end / 1000
        );
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

            // Removes empty lines & lines wich starts and ends with brackets or parenthesis
            $linesClean = array_values(array_filter($linesClean, function($line) {
                $line = trim($line);
                return !empty($line) && !preg_match('/^\(.*\)$/', $line) && !preg_match('/^\[.*\]$/', $line);
            }));

            // Add verse to the list
            array_push($this->verses, $lines);
            array_push($this->versesClean, $linesClean);

            // Update stats
            $this->versesCount++;
            $this->linesCount += count($linesClean);
            foreach ($linesClean as $line) {
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
     * @param STTWord[] $referenceWords Reference words from WhisperX
     * @param string|false $error Error message if any
     * @return VerseTimecode[][]|false Synchronized lyrics line by line
     */
    public function SyncStructure($referenceWords, &$error = false) {
        $DEBUG = false;
        $DEBUG_RESULT = 'none'; // all, times, live or none
        if ($DEBUG) {
            echo('Total ref words: ' . count($referenceWords) . "\n");
        }

        /** @var Timecode[] */
        $timecodes = array();

        // Count words from WhisperX reference and define average words per line
        $refWordsCount = count($referenceWords);
        $averageWordsInLine = floor($refWordsCount / $this->linesCount);
        if ($refWordsCount === 0) {
            $error = 'No words found from reference';
            return false;
        }

        // Check words length, return if delta more than 25% difference
        $maxLength = max($this->wordsCount, $refWordsCount);
        $delta = abs($this->wordsCount - $refWordsCount) / $maxLength;
        if ($delta > .75) {
            $error = "Not enough words from reference ($refWordsCount/$this->wordsCount words found)";
            return false;
        }

        // 1. Set default structure - average words per line
        $start = 0;
        for ($i = 0; $i < $this->GetVersesCount(); $i++) {
            $linesCount = count($this->versesClean[$i]);
            for ($j = 0; $j < $linesCount; $j++) {
                $line = $this->versesClean[$i][$j];
                $end = min($start + $averageWordsInLine, $refWordsCount - 1);
                $timecode = new Timecode($line, $start, $end);

                array_push($timecodes, $timecode);
                $start += $averageWordsInLine;
            }
        }

        // 2. Search for timecodes
        $offset = 4;
        for ($l = 0; $l < $this->linesCount; $l++) {

            // TODO - Remove cause it's useless
            if ($l < 0 || $l >= $this->linesCount) {
                $class = 'LinesCount: ' . json_encode($this->linesCount) . "\t";
                $class .= 'Verses: ' . json_encode($this->verses) . "\t";
                $class .= 'Timecodes: ' . json_encode($timecodes);
                print_r("Timecode is null at index $l - $class", 'lyrics');
                exit;
            }

            //$DEBUG = $l <= 3;
            $line = $timecodes[$l]->line;
            $words = explode(' ', $line);
            $words = array_map('CleanText', $words);

            $indexStart = 0;
            $indexMax = $refWordsCount - 1;

            // Get index start from last correct timecode
            for ($i = $l - 1; $i >= 0; $i--) {
                if ($timecodes[$i]->end !== 0) {
                    $indexStart = $timecodes[$i]->end + 1;
                    break;
                }
            }

            // Set index start to max if out of range
            if ($indexStart + $offset > $indexMax)
                $indexStart = $indexMax - $offset;

            // Adjust start
            $foundStart = false;
            for ($w = 0; $w < count($words) - 2; $w++) {
                $firstWordLine = $words[$w];

                $newIndex = STTWord::search(
                    $referenceWords,
                    $firstWordLine,
                    $indexStart - $offset,
                    $indexStart + $offset
                );

                if ($newIndex !== false) {
                    $foundStart = true;
                    $timecodes[$l]->start = $newIndex;
                    break;
                }
            }

            // Search last
            $foundEnd = false;
            for ($w = 0; $w < count($words) - 2; $w++) {
                $lastWordLine = CleanText($words[count($words) - $w - 1]);
                $s = $timecodes[$l]->start + count($words) - 1;

                if ($DEBUG) {
                    $wordsCount = count($words);
                    echo("Hmm: {$timecodes[$l]->start} + {$wordsCount} = {$s}\n");
                    echo("AAA: $s - " . join(', ', array_slice(array_column($referenceWords, 'clean_text'), $s - $offset, 2*$offset)) . "\n");
                }

                $newIndex = STTWord::search($referenceWords, $lastWordLine, $s - $offset, $s + $offset);
                if ($newIndex !== false) {
                    if ($DEBUG) {
                        echo("Precise end found: {$timecodes[$l]->end} => {$newIndex}\n");
                    }

                    $foundEnd = true;
                    $timecodes[$l]->end = $newIndex;
                    break;
                } else {
                    if ($DEBUG) echo("Not found!\n");
                }
            }

            if ($foundStart === false && $foundEnd === false) {
                if ($DEBUG) echo("Nothing found\n");
                $timecodes[$l]->start = 0;
                $timecodes[$l]->end = 0;
            } else if (($foundStart && !$foundEnd) || $timecodes[$l]->end < $timecodes[$l]->start) {
                if ($DEBUG) echo("Start found, end not found\n");
                $timecodes[$l]->end = $timecodes[$l]->start + count($words);
            } else if (!$foundStart && $foundEnd) {
                if ($DEBUG) echo("End found, start not found\n");
                $timecodes[$l]->start = $timecodes[$l]->end - count($words);
            } else {
                $timecodes[$l]->definitive = true;
            }
            if ($DEBUG) echo("Final found: {$timecodes[$l]->start} - {$timecodes[$l]->end}\n");
        }

        if (count($timecodes) === 0) {
            $error = 'No timecodes found';
            return false;
        }

        // 3. Last check - if there are non definitive timecodes, deduce them from median
        $alive = true;
        while ($alive) {
            // 3.1. Find median
            $medianData = array();
            for ($i = 1; $i < count($timecodes); $i++) {
                $timecode = $timecodes[$i];
                $prevTimecode = $timecodes[$i - 1];

                if (!$timecode->definitive || !$prevTimecode->definitive)
                    continue;

                $delta = $timecode->start - $prevTimecode->start;
                array_push($medianData, $delta);
            }
            $median = Median($medianData);

            // 3.2. Apply median to non definitive timecodes
            $modification = 0;
            for ($i = 1; $i < count($timecodes) - 1; $i++) {
                $timecode = $timecodes[$i];
                $prevTimecode = $timecodes[$i - 1];
                $nextTimecode = $timecodes[$i + 1];
                if ($timecode->definitive) {
                    continue;
                }

                if ($prevTimecode->definitive && $nextTimecode->definitive) {
                    $timecode->start = ($prevTimecode->start + $nextTimecode->start) / 2;
                    $timecode->end = $timecode->start + count(explode(' ', $timecode->line));

                    if ($timecode->start < 0) $timecode->start = 0;
                    if ($timecode->end < 0) $timecode->end = 0;

                    $timecode->definitive = true;
                    $modification++;
                } else if ($prevTimecode->definitive) {
                    $timecode->start = $prevTimecode->start + $median;
                    $timecode->end = $timecode->start + count(explode(' ', $timecode->line));

                    if ($timecode->start < 0) $timecode->start = 0;
                    if ($timecode->end < 0) $timecode->end = 0;

                    $timecode->definitive = true;
                    $modification++;
                } else if ($nextTimecode->definitive) {
                    $timecode->end = $nextTimecode->start - $median;
                    $timecode->start = $timecode->end - count(explode(' ', $timecode->line));

                    if ($timecode->end < 0) $timecode->end = 0;
                    if ($timecode->start < 0) $timecode->start = 0;

                    $timecode->definitive = true;
                    $modification++;
                }
                $timecode->start = minmax($timecode->start, 0, $refWordsCount - 1);
                $timecode->end = minmax($timecode->end, 0, $refWordsCount - 1);
            }
            if ($modification === 0) {
                $alive = false;
            }
        }

        // 4. Disable timecodes that are too close to each other
        for ($i = 1; $i < count($timecodes); $i++) {
            $timecode = $timecodes[$i];

            $startTime = $referenceWords[$timecode->start]->start;
            $endTime = $referenceWords[$timecode->end]->end;
            $duration = ($endTime - $startTime) / 1000;

            if ($duration < .5 || $duration > 15) {
                $timecode->definitive = false;
            }
        }

        // Print line sync & timecodes line by line
        if ($DEBUG_RESULT === 'all' || $DEBUG_RESULT === 'times') {
            // Get first line of each verse
            $firstLinesIndexes = array();
            $verses = $this->GetVerses();
            $total = 0;
            for ($i = 0; $i < count($verses); $i++) {
                $firstLinesIndexes[] = $total;
                $total += count($verses[$i]);
            }

            for ($i = 0; $i < count($timecodes); $i++) {
                $timecode = $timecodes[$i];
                $firstIndex = $timecode->start;
                $lastIndex = $timecode->end;
                if ($firstIndex >= $refWordsCount || $lastIndex >= $refWordsCount)
                    break;
                if ($firstIndex < 0 || $lastIndex < 0)
                    continue;

                $txtIndex = str_pad($i, 3, ' ', STR_PAD_LEFT);
                $txtFirstTime = round($referenceWords[$firstIndex]->start / 1000, 2);
                $txtLastTime = round($referenceWords[$lastIndex]->end / 1000, 2);    
                $txtTimecodes = str_pad("Line $txtIndex: $txtFirstTime - $txtLastTime", 30, ' ', STR_PAD_RIGHT);

                $refWords = array_slice($referenceWords, $firstIndex, $lastIndex - $firstIndex + 1); // TODO: +1 needed ?
                $refLine = join(' ', array_column($refWords, 'text'));
                $txtLine = str_pad($timecode->line, 70, ' ', STR_PAD_RIGHT);
                $txtRefLine = str_pad($refLine, 70, ' ', STR_PAD_RIGHT);
                if (in_array($i, $firstLinesIndexes)) {
                    echo("\033[1;32m");
                    //$txtLine = "\033[1;32m$txtLine\033[0m";
                    //$txtRefLine = "\033[1;32m$txtRefLine\033[0m";
                    $txtRefLine .= "\033[0m";
                }
                echo("$txtTimecodes    $txtLine| $txtRefLine\n");
            }
        }

        // Print line by line in live
        if ($DEBUG_RESULT === 'all' || $DEBUG_RESULT === 'live') {
            echo("Press enter to start live lyrics...\n");
            $handle = fopen('php://stdin', 'r');
            $line = fgets($handle);
            fclose($handle);

            $currTime = 0;
            for ($i = 0; $i < count($timecodes); $i++) {
                $timecode = $timecodes[$i];
                $firstIndex = $timecode->start;
                $lastIndex = $timecode->end;

                if ($firstIndex >= count($referenceWords) || $lastIndex >= count($referenceWords))
                    continue;

                // Define duration to the next line
                $firstTime = @$referenceWords[$firstIndex]->start;
                $duration = $firstTime - $currTime;

                // Check
                if ($duration <= 0) {
                    echo("Warning: Duration < 0 (line start before previous one)\n");
                } else {
                    // Wait
                    usleep($duration * 1000);
                    $currTime += $duration;
                }

                // Print
                echo($timecode->line . "\n");
            }
        }

        // Get first line of each verse
        $verses = $this->GetVerses();
        $firstLinesIndexes = array_reduce($verses, function($acc, $verse) {
            $acc[] = end($acc) + count($verse);
            return $acc;
        }, [0]);

        $output = array();
        for ($i = 0; $i < $this->GetVersesCount() - 2; $i++) {
            $start = $firstLinesIndexes[$i];
            $end = $firstLinesIndexes[$i + 1] - 1;

            // Ignore firsts lines if they are not defined
            while ($start < $end && $timecodes[$start]->definitive === false) {
                $start++;
            }

            // Ignore lasts lines if they are not defined
            while ($end > $start && $timecodes[$end]->definitive === false) {
                $end--;
            }

            // Ignore all verses if one of them is not defined
            //$verseError = false;
            //for ($j = $start; $j < $end; $j++) {
            //    if (!$timecodes[$j]->definitive) {
            //        $verseError = true;
            //        break;
            //    }
            //}
            //if ($verseError) {
            //    continue;
            //}

            $verse = array();
            for ($j = $start; $j < $end; $j++) {
                if ($timecodes[$j] === null) {
                    continue;
                }
                $verse[] = $timecodes[$j]->GetTimecode($referenceWords);
            }

            while (count($verse) >= 4) {
                $output[] = array_splice($verse, 0, 4);
            }
        }

        return $output;
    }
}

?>