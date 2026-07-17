#!/usr/bin/env php
<?php
/**
 * Minify JS using YUICompressor
 */

if($argc < 3)
{
    echo "Usage: minifyJS.php <input> <output>\n";
    exit(1);
}

$input  = $argv[1];
$output = $argv[2];

// Get YUICompressor JAR path from environment or use default
$yuicompressor = getenv('YUICOMPRESSOR_JAR');
if(empty($yuicompressor)) $yuicompressor = '/tmp/yuicompressor.jar';

// Check if input file exists
if(!file_exists($input))
{
    echo "Error: Input file does not exist: $input\n";
    exit(1);
}

if(!file_exists($yuicompressor))
{
    echo "Error: YUICompressor JAR does not exist: $yuicompressor\n";
    exit(1);
}

// Build Java command
$cmd = 'java -jar ' . escapeshellarg($yuicompressor) . ' --type js -o ' . escapeshellarg($output) . ' ' . escapeshellarg($input) . ' 2>&1';

// Execute
$outputLines = array();
$code        = 0;
exec($cmd, $outputLines, $code);
if($code !== 0)
{
    echo "Error compressing file:\n" . implode("\n", $outputLines) . "\n";
    exit(1);
}

exit(0);
