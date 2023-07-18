<?php

function main() {
	echo '<p>Hello, world!</p>';
	// debug1();
	// debug2();
	// debug3();
}

/* Content download */
function debug1() {
	$urlf = 'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d';
	$id = 38401264;

	$html = file_get_contents(sprintf($urlf, $id));

	echo '<details>';
	printf('<summary>%s</summary>', sprintf($urlf, $id));
	printf('<div style="%s">%s</div>', 'white-space: pre-wrap;', htmlspecialchars($html));
	echo '</details>';
}

/* Flush buffer */
function debug2() {
	for ($i = 0; $i < 10; ++$i) {
		printf('<p>%d</p>', $i + 1);
		ob_flush();
		flush();
		sleep(1);
	}
	echo '<p>Done</p>';
}

/* Nonexistent content */
function debug3() {
	$url = 'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/99999999';
	printf('<p>%s</p>', $url);
	$html = @file_get_contents($url);
	if ($html)
		printf('<div style="%s">%s</div>', 'white-space: pre-wrap;', htmlspecialchars($html));
	else
		echo '<p>Content does not exists.</p>';
}

main();

?>
