<?php
require_once 'class/HashChecker.php';

define('TARGET_DIR', 'C:\xampp\htdocs\wordpress\wp-content\plugins\demo-plugin\\');
define('DEF_EXCLUDE', 'log\\,assets\\json\\');

// 提供部分參數透過命令列控制
// php hash_checker.php --dir=my_plugin/ --exclude=".git/*,tmp/*" --hashfile=hashfile.txt --reportfile=report.txt
$options = getopt("", ["dir:", "exclude", "hashfile:", "reportfile:"]);

$dir = $options['dir'] ?? TARGET_DIR;
$hashFile = $options['hashfile'] ?? 'hashfile.txt';
$reportFile = $options['reportfile'] ?? 'report.txt';
$exclude = explode(',', $options['exclude'] ?? DEF_EXCLUDE);

$checker = new HashChecker($dir, $hashFile, $reportFile, $exclude);
$checker->run();