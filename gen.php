<?php
// Backend: start a frame-smoothing job. Called via
// plugin.php?plugin=pixelfx&page=gen.php&nopage=1&src=..&out=..&fps=..
global $settings;
@header('Content-Type: application/json');

$seqDir  = isset($settings['sequenceDirectory']) ? $settings['sequenceDirectory'] : '/home/fpp/media/sequences';
$plugDir = (isset($settings['pluginDirectory']) ? $settings['pluginDirectory'] : '/home/fpp/media/plugins') . '/pixelfx';

$src = isset($_GET['src']) ? basename($_GET['src']) : '';
$out = isset($_GET['out']) ? basename($_GET['out']) : '';
$fps = isset($_GET['fps']) ? intval($_GET['fps']) : 0;

$out = preg_replace('/\.fseq$/i', '', $out);
$out = preg_replace('/[^A-Za-z0-9 _\.\-]/', '_', $out);
if ($src === '' || $out === '' || $fps < 1) { echo json_encode(array('status' => 'error', 'msg' => 'Missing parameters.')); exit; }

$srcFile = preg_match('/\.fseq$/i', $src) ? $src : ($src . '.fseq');
$srcPath = "$seqDir/$srcFile";
$dstPath = "$seqDir/$out.fseq";
$tool    = "$plugDir/pixelfx-smooth";

if (!is_file($srcPath)) { echo json_encode(array('status' => 'error', 'msg' => 'Source sequence not found.')); exit; }
if (!is_executable($tool)) { echo json_encode(array('status' => 'error', 'msg' => 'Smoothing tool not built. Reinstall/Update the plugin.')); exit; }

$job  = 'pfxsmooth_' . time() . '_' . mt_rand(1000, 9999);
$prog = "/tmp/$job.progress";
$logf = "/tmp/$job.log";

$cmd = escapeshellarg($tool) . ' ' . escapeshellarg($srcPath) . ' ' . escapeshellarg($dstPath) . ' ' . intval($fps) . ' ' . escapeshellarg($prog);
exec("nohup $cmd > " . escapeshellarg($logf) . " 2>&1 &");

echo json_encode(array('status' => 'ok', 'job' => $job));
