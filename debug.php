<?php

function main() {
	echo '<p>Hello, world!</p>';
	// debug1();
	// debug2();
	// debug3();
	// debug4();
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

/* Connect to PostgreSQL */
function debug4() {
	$dbconn = pg_connect("user=postgres password=1234");
	echo '<p>';
	var_dump($dbconn);
	echo '</p>';

	$query = 'SELECT current_database()';
	$result = pg_query($query);

	echo '<table>';
	while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		echo '<tr>';
		foreach ($line as $col_value)
			echo '<td>'.$col_value.'</td>';
		echo '</tr>';
	}
	echo '</table>';

	pg_free_result($result);
	pg_close($dbconn);
}

main();

?>
