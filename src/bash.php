<?php

/**
 * @param string $command Shell command
 * @param array $output Output of command
 * @param string|false $user User to run command as (false to run as default user)
 * @param boolean $hide Hide output of command (but don't return status)
 * @return integer Status of command
 */
function bash($command, &$output = null, $user = false, $hide = false, $escapeShellCmd = true) {
    if ($user !== false)
        $command = "sudo -u $user $command";

    if ($escapeShellCmd)
        $command = escapeshellcmd($command);

    if ($hide) {
        $command .= ' > /dev/null 2>&1'; // 2> /dev/null
        $output = shell_exec($command);
        return 0;
    }

    exec($command, $output, $status);
    return $status;
}

/**
 * Compress audio file to 320kbps to output or /tmp/randomstring-inputfile
 * @param string $inputFile
 * @param string $outputFile Outputfile name (optional, if null, will be the same as inputfile)
 * @return boolean Success of FFMPEG command
 */
function FFMPEG_reduceAudioFile($inputFile, $outputFile = null) {
    if ($outputFile === null) {
        $outputFile = '/tmp/' . RandomString(10) . '-' . $inputFile;
        $command = "ffmpeg -i \"$inputFile\" -acodec libmp3lame -ac 2 -ab 320k -ar 44100 \"$outputFile\" -hide_banner -loglevel quiet";
        $status = bash($command);
        if ($status !== 0) return false;

        // Delete inputfile and rename outputfile to inputfile
        $command = "rm \"$inputFile\" && mv \"$outputFile\" \"$inputFile\"";
        $status = bash($command);
        return $status === 0;
    }

    $command = "ffmpeg -i \"$inputFile\" -acodec libmp3lame -ac 2 -ab 320k -ar 44100 \"$outputFile\" -hide_banner -loglevel quiet";
    $status = bash($command, $output);
    return $status === 0;
}

/**
 * Cut audio file to output and hide FFMPEG output
 * @param string $inputFile
 * @param string $outputFile
 * @param int $start Start time
 * @param int $duration Duration
 * @return boolean Success of FFMPEG command
 */
function FFMPEG_cutAudioFile($inputFile, $outputFile, $start, $duration) {
    $command = "ffmpeg -i \"$inputFile\" -acodec copy -ss $start -t $duration \"$outputFile\" -y -hide_banner -loglevel quiet";
    $status = bash($command);
    return $status === 0;
}

?>
