<?php

function main() {
	$urlf = 'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d';
	$lb = 38401264;
	$up = 38401274;

	for ($id = $lb; $id <= $up; ++$id) {
		$html = file_get_contents(sprintf($urlf, $id));
		echo '<details>';
		printf('<summary>%s</summary>', sprintf($urlf, $id));
		printf('<div style="%s">%s</div>', 'white-space: pre-wrap;', htmlspecialchars($html));
		echo '</details>';
		ob_flush();
		flush();
	}
}

main();

?>
