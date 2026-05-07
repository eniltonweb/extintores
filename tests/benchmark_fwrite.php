<?php
/**
 * Benchmark for file writing efficiency: loop vs single write
 */

function benchmark_fwrite_loop($num_options, $tempFile) {
    $options = [];
    for ($i = 0; $i < $num_options; $i++) {
        $options[] = "<option value='{$i}'>EXT-{$i}</option>";
    }

    $start = microtime(true);
    $fp = fopen($tempFile, 'w');
    if ($fp) {
        foreach ($options as $opt) {
            fwrite($fp, $opt . "\n");
        }
        fclose($fp);
    }
    return microtime(true) - $start;
}

function benchmark_fwrite_single($num_options, $tempFile) {
    $options = [];
    for ($i = 0; $i < $num_options; $i++) {
        $options[] = "<option value='{$i}'>EXT-{$i}</option>";
    }

    $start = microtime(true);
    $fp = fopen($tempFile, 'w');
    if ($fp) {
        fwrite($fp, implode("\n", $options) . "\n");
        fclose($fp);
    }
    return microtime(true) - $start;
}

$num_options = 10000;
$tempFileLoop = tempnam(sys_get_temp_dir(), 'bench_loop');
$tempFileSingle = tempnam(sys_get_temp_dir(), 'bench_single');

echo "Running benchmark with $num_options options...\n";

$timeLoop = benchmark_fwrite_loop($num_options, $tempFileLoop);
echo "Baseline (fwrite in loop): " . number_format($timeLoop, 6) . " seconds\n";

$timeSingle = benchmark_fwrite_single($num_options, $tempFileSingle);
echo "Optimized (single fwrite with implode): " . number_format($timeSingle, 6) . " seconds\n";

$improvement = ($timeLoop - $timeSingle) / $timeLoop * 100;
echo "Improvement: " . number_format($improvement, 2) . "%\n";

@unlink($tempFileLoop);
@unlink($tempFileSingle);
