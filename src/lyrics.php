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

            // Removes empty lines & lines wich starts and ends with brackets or parenthesis
            $linesClean = array_values(array_filter($lines, function($line) {
                $line = trim($line);
                return !empty($line) && !preg_match('/^\(.*\)$/', $line) && !preg_match('/^\[.*\]$/', $line);
            }));

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
     * @param string|false $error Error message if any
     * @return VerseTimecode[]|false Synchronized lyrics line by line
     */
    public function SyncStructure($referenceWords, &$error = false) {
        $DEBUG = false;
        $DEBUG_RESULT = 'none'; // all, times, live or none
        if ($DEBUG) {
            echo('Total ref words: ' . count($referenceWords) . "\n");
        }

        /** @var Timecode[] */
        $timecodes = array();

        // 0. Count words from AssemblyAI reference and define average words per line
        $refWordsCount = count($referenceWords);
        $averageWordsInLine = floor($refWordsCount / $this->linesCount);
        if (count($referenceWords) === 0) {
            $error = 'No words found from reference';
            return false;
        }

        // 0. Check words length, return if delta more than 25% difference
        $maxLength = max($this->wordsCount, $refWordsCount);
        $delta = abs($this->wordsCount - $refWordsCount) / $maxLength;
        if ($DEBUG) echo("Words delta: $delta\n");
        if ($delta > .25) {
            $error = "Not enough words from reference ($refWordsCount/$this->wordsCount words found)";
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
        $offset = 4;
        for ($l = 0; $l < $this->linesCount; $l++) {
            //$DEBUG = $l <= 3;
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
                $timecodes[$l]->end > count($referenceWords) ||
                $timecodes[$l]->start < 0 ||
                $timecodes[$l]->end < 0)
                    break;

            $indexMax = count($referenceWords) - 1;
            $indexStart = 0;
            if ($l !== 0)
                $indexStart = $timecodes[$l - 1]->end + 1;
            if ($indexStart + $offset > $indexMax)
                $indexStart = $indexMax - $offset;

            // Get index start
            for ($i = $l - 1; $i >= 0; $i--) {
                if ($timecodes[$i]->end !== 0) {
                    $indexStart = $timecodes[$i]->end + 1;
                    break;
                }
            }

            // Adjust start
            $foundStart = false;
            for ($w = 0; $w < count($words) - 2; $w++) {
                $firstWordLine = CleanText($words[$w]);
                if ($DEBUG) {
                    $a = $indexStart - $offset;
                    $b = $indexStart + $offset;
                    echo("First word: $firstWordLine\n$a - $b\n");
                }
                $newIndex = AssemblyAIWord::search($referenceWords, $firstWordLine, $indexStart - $offset, $indexStart + $offset, $DEBUG);
                if ($newIndex !== false) {
                    if ($DEBUG) {
                        echo("Precise start found: {$timecodes[$l]->start} => {$newIndex}\n");
                    }

                    $foundStart = true;
                    $timecodes[$l]->start = $newIndex;
                    break;
                } else {
                    if ($DEBUG) {
                        echo("Not found!\n");
                    }
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

                $newIndex = AssemblyAIWord::search($referenceWords, $lastWordLine, $s - $offset, $s + $offset, $DEBUG);
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
            }
            if ($DEBUG) echo("Final found: {$timecodes[$l]->start} - {$timecodes[$l]->end}\n");
        }

        if (count($timecodes) === 0) {
            $error = 'No timecodes found';
            return false;
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
                if ($firstIndex >= count($referenceWords) || $lastIndex >= count($referenceWords))
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
                $firstTime = $referenceWords[$firstIndex]->start;
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

        // Get verses timecodes
        /** @var array<VerseTimecode> */
        $versesTimecodes = array();

        // Get first line of each verse
        $verses = $this->GetVerses();
        $firstLinesIndexes = array_reduce($verses, function($acc, $verse) {
            $acc[] = end($acc) + count($verse);
            return $acc;
        }, [0]);

        for ($i = 0; $i < $this->GetVersesCount(); $i++) {
            $lastVerse = $i === $this->GetVersesCount() - 1;
            $currIndex = $firstLinesIndexes[$i];
            if ($currIndex < 0 || $currIndex >= count($timecodes)) {
                $vt = new VerseTimecode('error', 0.0, 0.0);
                array_push($versesTimecodes, $vt);
                continue;
            }
            $currTimecode = $timecodes[$currIndex];
            $nextTimecode = false;

            if ($lastVerse) {
                $nextTimecode = $timecodes[count($timecodes) - 1];
            } else {
                $nextIndex = $firstLinesIndexes[$i + 1] - 1;
                if ($nextIndex > 0 && $nextIndex < count($timecodes)) {
                    $nextTimecode = $timecodes[$nextIndex];
                }
            }

            // Lyrics index errors
            if ($currTimecode->start < 0 || $currTimecode->end >= $refWordsCount || $nextTimecode === false ||
                $nextTimecode->start < 0 || $nextTimecode->end >= $refWordsCount) {
                $vt = new VerseTimecode('error', 0.0, 0.0);
                array_push($versesTimecodes, $vt);
                continue;
            }

            $status = 'ok';
            $startTime = $referenceWords[$currTimecode->start]->start;
            $endTime = $referenceWords[$nextTimecode->end]->end;

            // Times errors
            if ($endTime == 0 || $endTime < $startTime ||
                $endTime - $startTime < 1000 * count($verses[$i])) {
                $status = 'error';
            }

            $startTime = round($startTime / 1000, 2);
            $endTime = round($endTime / 1000, 2);
            $vt = new VerseTimecode($status, $startTime, $endTime);
            array_push($versesTimecodes, $vt);
        }

        return $versesTimecodes;
    }
}

?>