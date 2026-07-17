#!/usr/bin/env php
<?php
/**
title=测试 misc minify scripts.
cid=0

- JS 压缩命令非零退出时脚本失败 @1
- CSS 压缩命令非零退出时脚本失败 @1
- JS 压缩命令路径参数会被转义 @1
*/
include dirname(__FILE__, 3) . '/test/lib/init.php';

$root = dirname(__FILE__, 2);
$tmp  = sys_get_temp_dir() . '/zentao_minify_test_' . getmypid();
mkdir($tmp);
mkdir("$tmp/bin");

$java = "$tmp/bin/java";
file_put_contents($java, "#!/usr/bin/env sh\nprintf '%s\\n' \"$@\" > " . escapeshellarg("$tmp/java_args") . "\nexit 7\n");
chmod($java, 0755);

$input  = "$tmp/input file.js";
$output = "$tmp/output file.js";
$jar    = "$tmp/yui jar.jar";
file_put_contents($input, 'var a = 1;');
file_put_contents($jar, 'fake');

$env = 'PATH=' . escapeshellarg("$tmp/bin:" . getenv('PATH')) . ' YUICOMPRESSOR_JAR=' . escapeshellarg($jar);

exec("$env php " . escapeshellarg("$root/minifyJS.php") . ' ' . escapeshellarg($input) . ' ' . escapeshellarg($output) . ' 2>&1', $jsOutput, $jsCode);
exec("$env php " . escapeshellarg("$root/minifyCSS.php") . ' ' . escapeshellarg($input) . ' ' . escapeshellarg($output) . ' 2>&1', $cssOutput, $cssCode);

$args = file_exists("$tmp/java_args") ? file_get_contents("$tmp/java_args") : '';

r((int)($jsCode !== 0)) && p() && e('1');  // JS 压缩命令非零退出时脚本失败
r((int)($cssCode !== 0)) && p() && e('1'); // CSS 压缩命令非零退出时脚本失败
r((int)(strpos($args, $jar) !== false && strpos($args, $input) !== false && strpos($args, $output) !== false)) && p() && e('1'); // JS 压缩命令路径参数会被转义

array_map('unlink', glob("$tmp/bin/*"));
array_map('unlink', array_filter(glob("$tmp/*"), 'is_file'));
@rmdir("$tmp/bin");
@rmdir($tmp);
