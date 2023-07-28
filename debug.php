<?php

function main() {
	echo '<p>Hello, world!</p>';
	// debug1();
	// debug2();
	// debug3();
	// debug4();
	// debug5();
	// debug6();
	// debug7();
	// debug8();
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
	$dbconn = pg_connect('user=postgres password=1234');
	echo '<p>'; var_dump($dbconn); echo '</p>';

	$stmt = bin2hex(random_bytes(16));
	$res = pg_prepare($dbconn, $stmt, 'select current_database()');
	$res = pg_execute($dbconn, $stmt, []);

	echo '<table>';
	while ($line = pg_fetch_array($res, null, PGSQL_ASSOC)) {
		echo '<tr>';
		foreach ($line as $col_value)
			echo '<td>'.$col_value.'</td>';
		echo '</tr>';
	}
	echo '</table>';

	pg_free_result($res);
	pg_close($dbconn);
}

/* Write to PostgreSQL */
function debug5() {
	$dbconn = pg_connect('dbname=test user=postgres password=1234');

	$stmt1 = bin2hex(random_bytes(16));
	$res = pg_prepare($dbconn, $stmt1, 'select * from test where id = $1');

	$stmt2 = bin2hex(random_bytes(16));
	$res = pg_prepare($dbconn, $stmt2, 'insert into test(id, content) values($1, $2)');

	$res = pg_execute($dbconn, $stmt1, [0]);
	if ($res !== false) {
		$res = pg_fetch_all($res);
		if (count($res) == 0) {
			$res = pg_execute($dbconn, $stmt2, [0, 'abc']);
			if ($res !== false)
				echo '<p>Insert successful.</p>';
			else
				echo '<p>Insert failed.</p>';
		}
		else {
			echo '<p>Entry exists</p>';
		}
	}
	else {
		echo '<p>Query failed.</p>';
	}
}

/* PgSql\Result fetch */
function debug6() {
	$dbconn = pg_connect('dbname=test user=postgres password=1234');

	$stmt = bin2hex(random_bytes(16));
	$res = pg_prepare($dbconn, $stmt, 'select * from test');

	$res = pg_execute($dbconn, $stmt, []);
	echo '<h1>pg_fetch_all($res)</h1>';
	echo '<p>'; var_dump(pg_fetch_all($res)); echo '</p>';

	$res = pg_execute($dbconn, $stmt, []);
	echo '<h1>pg_fetch_array($res)</h1>';
	echo '<p>'; var_dump(pg_fetch_array($res)); echo '</p>';

	$res = pg_execute($dbconn, $stmt, []);
	echo '<h1>pg_fetch_row($res)</h1>';
	echo '<p>'; var_dump(pg_fetch_row($res)); echo '</p>';

	$res = pg_execute($dbconn, $stmt, []);
	echo '<h1>pg_fetch_assoc($res)</h1>';
	echo '<p>'; var_dump(pg_fetch_assoc($res)); echo '</p>';

	$res = pg_execute($dbconn, $stmt, []);
	echo '<h1>pg_fetch_object($res)</h1>';
	echo '<p>'; var_dump(pg_fetch_object($res)); echo '</p>';

	$res = pg_execute($dbconn, $stmt, []);
	echo '<h1>pg_fetch_result($res, 1, 0)</h1>';
	echo '<p>'; var_dump(pg_fetch_result($res, 1, 0)); echo '</p>';

	$res = pg_execute($dbconn, $stmt, []);
	echo '<h1>pg_fetch_all_columns($res, 1)</h1>';
	echo '<p>'; var_dump(pg_fetch_all_columns($res, 1)); echo '</p>';
}

/* INI file */
function debug7() {
	$ini_array = parse_ini_file('crawl.ini', true, INI_SCANNER_TYPED);
	echo '<div style="white-space: pre-wrap;">';
	print_r($ini_array);
	echo '</div>';
}

/* Decode Cloudflare obfuscated email */
function debug8() {
	function cfDecodeEmail($encodedString){
		$k = hexdec(substr($encodedString,0,2));
		for($i=2,$email='';$i<strlen($encodedString)-1;$i+=2){
			$email.=chr(hexdec(substr($encodedString,$i,2))^$k);
		}
		return $email;
	}
	echo cfDecodeEmail('fb9e9682bb8b9e959c938e998e959cd5919a8f9e959c8b89948dd59c94d5929f');
}

main();

?>
