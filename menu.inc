<?php
// Menu entries for the pixelfx plugin. FPP's core menu includes this file for
// each menu section, with $plugin (repo name) and $menu (current section:
// status/content/output/help) already set. Entries whose 'type' matches the
// current $menu are printed as links to plugin.php?plugin=<name>&page=<page>.

$menuEntries = Array(
	Array(
		'text' => 'Pixel FX',
		'type' => 'content',
		'page' => 'plugin.php',
		'wrap' => 1
	),
	Array(
		'text' => 'Pixel FX - Frame Generator',
		'type' => 'content',
		'page' => 'smoothgen.php',
		'wrap' => 1
	),
	Array(
		'text' => 'Pixel FX - Help',
		'type' => 'help',
		'page' => 'https://github.com/joeskolengaden/pixelfx',
		'wrap' => 1
	)
);

foreach ($menuEntries as $entry)
{
	if ($entry['type'] != $menu)
		continue;

	if (preg_match('/^http.?:\/\//', $entry['page']))
	{
		printf("<li><a href='%s' target='_blank'>%s</a></li>\n",
			$entry['page'], $entry['text']);
	}
	else
	{
		$nopage = '';
		if (isset($entry['wrap']) && ($entry['wrap'] == 0))
			$nopage = '&nopage=1';

		printf("<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n",
			$plugin, $entry['page'], $nopage, $entry['text']);
	}
}
?>
