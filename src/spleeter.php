<?php

/**
 * @param string $inputFile
 * @param string $outputFile
 * @param string $tempDirectory
 * @return boolean Success of Spleeter command
 */
function SPLEETER_separateAudioFile($inputFile, $outputFile, $tempDirectory = '/tmp/spleeter/') {
    if (!str_ends_with($tempDirectory, '/')) $tempDirectory .= '/';
    if (file_exists($outputFile)) return true;
    if (!file_exists($inputFile)) return false;

    $pathSplit = explode('/', $inputFile);
    $id = explode('.', end($pathSplit))[0];
    $tempDirectoryID = $tempDirectory . $id . '/';

    // Spleeter
    $command = "python3 -m spleeter separate \"$inputFile\" -p spleeter:2stems -o \"{$tempDirectory}\"";
    $status = bash($command, $output);
    if ($status !== 0) return false;

    // Move vocal file (with FFMPEG to 320kbps)
    $moved = FFMPEG_reduceAudioFile($tempDirectoryID . 'vocals.wav', $outputFile);
    if ($moved === false) return false;

    // Remove temp files
    $command = "rm -rf \"$tempDirectoryID\"";
    $status = bash($command, $output);
    if ($status !== 0) return false;

    return true;
}

?>