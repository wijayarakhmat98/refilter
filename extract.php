<?php

set_time_limit(604800);

require_once('maps.php');
require_once('database.php');

function main() {
	echo '<div style="white-space: pre; font-family: Consolas;">';

	$dbconn = pg_connect('dbname=refilter user=postgres password=1234');

	$stmt = bin2hex(random_bytes(16));
	pg_prepare($dbconn, $stmt, 'select auto_id, id, content from raw where website = $1 and (type = $2 or (type is NULL and $2 is NULL))');

	$conf_src = file_get_contents('maps.json');
	$confs = json_decode($conf_src, true);

	foreach ($confs as $conf) {
		require_once(sprintf('extract/%s.php', $conf['factory']));

		$exists = new db_exists($dbconn, $conf['table'], ['id']);
		$insert = new db_insert($dbconn, $conf['table'], ...maps\column($conf));

		$src = $conf['source'];
		$get = $conf['factory'].'\get';

		$results = pg_execute($dbconn, $stmt, [$src['website'], $src['type']]);

		while ($result = pg_fetch_assoc($results)) {
			$raw_id = $result['auto_id'];
			$web_id = $result['id'];
			$content = $result['content'];

			$whoami = sprintf("%s %s %s %d", $src['website'], $src['type'] ?? 'null', $conf['table'], $web_id);

			if ($exists($web_id)) {
				printf("%s: SKIP\n", $whoami);
				continue;
			}

			$extract = $get($content);

			$instance_id = 0;
			$rows = maps\extract($extract, $conf,
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

			printf("%s: ", $whoami);
			if (count($rows) > 0)
				foreach ($rows as $i => $vals)
					if (!$insert(...$vals)) {
						printf("FAIL [%d]\n", $i);
						better_dump($vals);
					}
					else
						printf("INSERT [%d]\n", $i);
			else
				printf("EMPTY\n");
		}
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
