<?php

require_once('database.php');

function main() {
	echo '<div style="white-space: pre; font-family: Consolas;">';

	$conf_src = file_get_contents('maps.json');
	$conf = json_decode($conf_src);

	$conf = $conf[0];

	$dbconn = pg_connect('dbname=refilter user=postgres password=1234');
	$stmt = bin2hex(random_bytes(16));
	pg_prepare($dbconn, $stmt, 'select auto_id, content from raw where website = $1 and (type = $2 or (type is NULL and $2 is NULL)) and id = $3');

	$namespace = $conf->{'factory'};

	require_once(sprintf('extract/%s.php', $namespace));

	$source_website = $conf->{'source'}->{'website'};
	$source_type = $conf->{'source'}->{'type'};
	$table = $conf->{'table'};

	$get = sprintf('%s\get', $namespace);

	$array_paths = [];
	$array_parses = [];
	$array_types = [];
	$column_names = [];

	foreach ($conf->{'maps'} as $conf_c) {
		$array_paths[] = (property_exists($conf_c->{'array'}, 'path'))
				? $conf_c->{'array'}->{'path'} : null;
		$array_parses[] = (property_exists($conf_c->{'array'}, 'parse'))
				? sprintf('%s\%s', $namespace, $conf_c->{'array'}->{'parse'}) : null;
		$array_types[] = $conf_c->{'array'}->{'type'};
		$column_names[] = $conf_c->{'column'}->{'name'};
	}

	$exists = new db_exists($dbconn, $table, ['id']);
	$insert = new db_insert($dbconn, $table, $column_names);

	for ($web_id = 162326; $web_id < 162326 + 10; ++$web_id) {
		printf("%s %s %d: ", $source_website, $source_type, $web_id);
		if ($exists($web_id)) {
			echo "SKIP\n";
			continue;
		}
		else
			echo "INSERT\n";
		$res = pg_fetch_all(pg_execute($dbconn, $stmt, [$source_website, $source_type, $web_id]))[0];
		$raw_id = $res['auto_id'];
		$content = $res['content'];
		$array = $get($content);
		$vals = [];
		for ($i = 0; $i < count($column_names); ++$i) {
			$array_path = $array_paths[$i];
			$array_parse = $array_parses[$i];
			$array_type = $array_types[$i];
			$val = ($array_path === null) ? null : navigate($array, $array_path);
			if ($array_parse)
				$val = $array_parse($val);
			else
				switch ($array_type) {
					case "raw_id": $val = $raw_id; break;
					case "web_id": $val = $web_id; break;
				}
			$vals[] = $val;
		}
		$insert(...$vals);
	}

	echo '</div>';
}

function navigate($tree, $path) {
	return ($path) ? navigate($tree[$path[0]], array_slice($path, 1)) : $tree;
}

main();

?>
