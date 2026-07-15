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

$input = $argv[1];
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

// Build Java command
$cmd = "java -jar $yuicompressor --type js -o $output $input 2>&1";

// Execute
$result = shell_exec($cmd);

if($result && strpos($result, 'Exception') !== false)
{
    echo "Error compressing file:\n$result\n";
    exit(1);
}

exit(0);
