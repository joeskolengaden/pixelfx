<?php
// Returns the progress (0-100, or -1 on error) of a smoothing job.
// plugin.php?plugin=pixelfx&page=progress.php&nopage=1&job=..
@header('Content-Type: application/json');
$job = isset($_GET['job']) ? basename($_GET['job']) : '';
$prog = "/tmp/$job.progress";
$p = (is_file($prog)) ? trim(file_get_contents($prog)) : '0';
if ($p === '') $p = '0';
echo json_encode(array('progress' => $p));
