<?php

function main() {
	echo '<h1>Ascending Crawl</h1>';
	ascending_crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		38717036, 38717736, 3
	);
	echo '<h1>Descending Crawl</h1>';
	descending_crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		38071127, 38071927, 3
	);
}

function ascending_crawl($urlf, $lb, $ub, $margin) {
	$fail = 0;
	for ($id = $lb; $id <= $ub; ++$id) {
		if (get_content(sprintf($urlf, $id)))
			$fail = 0;
		else
			if (++$fail >= $margin)
				break;
	}
}

function descending_crawl($urlf, $lb, $ub, $margin) {
	$fail = 0;
	for ($id = $ub; $id >= $lb; --$id) {
		if (get_content(sprintf($urlf, $id)))
			$fail = 0;
		else
			if (++$fail >= $margin)
				break;
	}
}

function get_content($url) {
	$html = @file_get_contents($url);

	echo '<details>';
	printf('<summary>%s [%s]</summary>', $url, ($html) ? 'SUCCESS' : 'FAIL');
	if ($html)
		printf('<div style="%s">%s</div>', 'white-space: pre-wrap;', htmlspecialchars($html));
	else
		printf('<a href="%s">%s</a>', $url, $url);
	echo '</details>';
	ob_flush();
	flush();

	return $html;
}

main();

?>
