<?php

include '../pokemonshowdown.com/config/servers.inc.php';

header('Content-type: text/css');

$server = @$_REQUEST['server'];
$customcssuri = @$PokemonServers[$server]['customcss'];
if (empty($customcssuri)) {
	die();
}

$cssfile = '../pokemonshowdown.com/config/customcss/' . $server;

// No need to sanitise $server because it should be safe already.
$lastmodified = @filemtime($cssfile);
if ($lastmodified && (time() - $lastmodified) < 3600) {
	// Don't check for modifications more than once an hour.
	readfile($cssfile);
	die();
}

$curl = curl_init($customcssuri);
if ($lastmodified) {
	curl_setopt($curl, CURLOPT_HTTPHEADER, array(
		'If-Modified-Since: ' . gmdate('D, d M Y H:i:s T', $lastmodified)
	));
}
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$curlret = curl_exec($curl);
if ($curlret) {
	$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($code === 304) {
		// No modifications.
		readfile($cssfile);
		touch($cssfile);	// Don't check again for an hour.
	} else if ($code === 200) {
		// Sanitise the CSS.
		require '../pokemonshowdown.com/lib/htmlpurifier/HTMLPurifier.auto.php';
		require '../pokemonshowdown.com/lib/csstidy/class.csstidy.php';
		$config = HTMLPurifier_Config::createDefault();
		$config->set('Filter.ExtractStyleBlocks', TRUE);
		$purifier = new HTMLPurifier($config);
		$level = error_reporting(E_ALL & ~E_STRICT);
		$html = $purifier->purify('<style>' . $curlret . '</style>');
		error_reporting($level);
		list($outputcss) = $purifier->context->get('StyleBlocks');
		file_put_contents($cssfile, $outputcss);
		echo $outputcss;
	} else {
		// Error condition - clear the cached file.
		file_put_contents($cssfile, '');
	}
}
curl_close($curl);
