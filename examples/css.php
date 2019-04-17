<?php

use Greativ\GCSS\GCSS;

include(__DIR__.'/../vendor/autoload.php');

GCSS::setDebug();

$expires = 3600;

header('Content-type: text/css');
header("Vary: Accept-Encoding");
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');

$cssGlobalVariables = __DIR__ . '/_variables.gcss';
$cssGlobalMixins = __DIR__ . '/_mixins.gcss';

if (is_file($cssGlobalVariables)) {
	GCSS::loadVariables($cssGlobalVariables);
}
if (is_file($cssGlobalMixins)) {
	GCSS::loadMixins($cssGlobalMixins);
}

$cssDir = __DIR__.'/css';
if (is_dir($cssDir)) {
	$cssFilesFromDir = array();
	if ($handle = opendir($cssDir)) {
		while (false !== ($file = readdir($handle))) {
			if (strpos($file, '.css') !== false) {
				readfile($cssDir.'/'.$file);
			}
		}
		closedir($handle);
	}
}

$cssDir = __DIR__.'/gcss';
if (is_dir($cssDir)) {
	$cssFilesFromDir = array();
	if ($handle = opendir($cssDir)) {
		while (false !== ($file = readdir($handle))) {
			if($file == '_mixins.gcss' || $file == '_variables.gcss'){
				continue;
			}
			if (strpos($file, '.gcss') !== false) {
				$css = new GCSS($cssDir . '/' . $file);
				echo $css->parseCss();
			}
		}
		closedir($handle);
	}
}

$output = ob_get_contents();

header("Pragma: public");
header("Cache-Control: " . "must-revalidate, proxy-revalidate, max-age=" . $expires . ", s-maxage=" . $expires . ", maxage=" . $expires);
header('Etag: "' . md5(ob_get_length()) . '"');
ob_end_clean();
echo $output;