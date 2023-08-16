<?php

require_once('maps.php');
require_once('database.php');

function main() {
	echo '<div style="white-space: pre; font-family: Consolas;">';

	$conf_src = file_get_contents('maps.json');
	$conf = json_decode($conf_src, true);

	// $conf = $conf[0];
	// $conf = $conf[1];
	// $conf = $conf[2];
	// $conf = $conf[3];
	$conf = $conf[6];
	// $conf = $conf[7];
	// $conf = $conf[8];

	require_once(sprintf('extract/%s.php', $conf['factory']));

	$src = $conf['source'];

	switch ([$src['website'], $src['type'], $conf['table']]) {
		case ['sirup', 'satuan', 'sirup_satuan']:
			$lb = 162326;
			$ub = 162326 + 10000;
			break;
		case ['sirup', 'penyedia', 'sirup_penyedia']:
			$lb = 38401264;
			$ub = 38401264 + 10000;
			break;
		case ['sirup', 'swakelola', 'sirup_swakelola']:
			$lb = 31800157;
			$ub = 31800157 + 10000;
			break;
		case ['modi', null, 'modi_profil']:
		case ['modi', null, 'modi_alamat']:
		case ['modi', null, 'modi_direksi']:
		case ['modi', null, 'modi_perizinan']:
			$lb = 14357;
			$ub = 14357 + 3000;
			break;
	}

	$dbconn = pg_connect('dbname=refilter user=postgres password=1234');
	$stmt = bin2hex(random_bytes(16));
	pg_prepare($dbconn, $stmt, 'select auto_id, content from raw where website = $1 and (type = $2 or (type is NULL and $2 is NULL)) and id = $3');

	$exists = new db_exists($dbconn, $conf['table'], ['id']);
	$insert = new db_insert($dbconn, $conf['table'], ...maps\column($conf));

	$get = $conf['factory'].'\get';

	for ($web_id = $lb; $web_id < $ub; ++$web_id) {
		$whoami = sprintf("%s %s %d", $src['website'], $src['type'] ?? 'null', $web_id);
		if ($exists($web_id)) {
			printf("%s: SKIP\n", $whoami);
			continue;
		}
		$res = pg_fetch_all(pg_execute($dbconn, $stmt, [$src['website'], $src['type'], $web_id]));
		if (count($res) == 0) {
			printf("%s: NOT EXISTS\n", $whoami);
			continue;
		}
		$res = $res[0];
		$raw_id = (int) $res['auto_id'];
		$instance_id = 0;
		$rows = maps\extract($get($res['content']), $conf,
			fn($t) => null,
			function ($t) use ($raw_id, $web_id, &$instance_id) {
				switch ($t) {
					case 'raw_id': return fn($v) => $raw_id;
					case 'web_id': return fn($v) => $web_id;
					case 'instance_id':
						return function ($v) use (&$instance_id) {
							return $instance_id++;
						};
				};
			},
			fn($t) => null
		);
		if (count($rows) > 0)
			foreach ($rows as $i => $vals)
				if (!$insert(...$vals)) {
					printf("%s: FAIL [%d]\n", $whoami, $i);
					better_dump($vals);
				}
				else
					printf("%s: INSERT [%d]\n", $whoami, $i);
		else
			printf("%s: EMPTY\n", $whoami);
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
