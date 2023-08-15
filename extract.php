<?php

require_once('maps.php');
require_once('database.php');

function main() {
	echo '<div style="white-space: pre; font-family: Consolas;">';

	$conf_src = file_get_contents('maps.json');
	$conf = json_decode($conf_src, true);

	// $conf = $conf[0];
	$conf = $conf[1];

	require_once(sprintf('extract/%s.php', $conf['factory']));

	$src = $conf['source'];

	switch ([$src['website'], $src['type']]) {
		case ['sirup', 'satuan']:
			$lb = 162326;
			$ub = 162326 + 10000;
			break;
		case ['sirup', 'penyedia']:
			$lb = 38401264;
			$ub = 38401264 + 10000;
			break;
	}

	$dbconn = pg_connect('dbname=refilter user=postgres password=1234');
	$stmt = bin2hex(random_bytes(16));
	pg_prepare($dbconn, $stmt, 'select auto_id, content from raw where website = $1 and (type = $2 or (type is NULL and $2 is NULL)) and id = $3');

	$exists = new db_exists($dbconn, $conf['table'], ['id']);
	$insert = new db_insert($dbconn, $conf['table'], ...maps\column($conf));

	$get = $conf['factory'].'\get';

	for ($web_id = $lb; $web_id < $ub; ++$web_id) {
		printf("%s %s %d: ", $src['website'], $src['type'], $web_id);
		if ($exists($web_id)) {
			echo "SKIP\n";
			continue;
		}
		$res = pg_fetch_all(pg_execute($dbconn, $stmt, [$src['website'], $src['type'], $web_id]));
		if (count($res) == 0) {
			echo "NOT EXISTS\n";
			continue;
		}
		$res = $res[0];
		$raw_id = (int) $res['auto_id'];
		$vals = maps\extract($get($res['content']), $conf,
			fn($t) => null,
			fn($t) =>
				$t == 'raw_id' ? fn($v) => $raw_id : (
				$t == 'web_id' ? fn($v) => $web_id : (
				null
			))
		);
		if (!$insert(...$vals)) {
			echo "FAIL\n";
			better_dump($array);
			continue;
		}
		echo "INSERT\n";
	}

	echo '</div>';
}

function better_dump($obj) {
	ob_start();
	var_dump($obj);
	$dump = ob_get_contents();
	ob_end_clean();
	$dump = preg_replace('/]=>\n\s*/', '] => ', $dump);
	echo $dump;
}

main();

?>
