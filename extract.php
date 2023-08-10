<?php

require_once('database.php');

function main() {
	echo '<div style="white-space: pre; font-family: Consolas;">';

	$conf_src = file_get_contents('maps.json');
	$conf = json_decode($conf_src);

	$conf = $conf[0];
	// $conf = $conf[1];

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
	$array_preprocesses = [];
	$array_types = [];
	$column_names = [];
	$column_types = [];

	foreach ($conf->{'maps'} as $conf_c) {
		$array_paths[] = (property_exists($conf_c->{'array'}, 'path'))
				? $conf_c->{'array'}->{'path'} : null;
		$array_preprocess = (property_exists($conf_c->{'array'}, 'preprocess'))
				? $conf_c->{'array'}->{'preprocess'} : null;
		$array_preprocesses[] = ($array_preprocess)
				? sprintf('%s\%s', $namespace, $array_preprocess) : null;
		$array_types[] = $conf_c->{'array'}->{'type'};
		$column_names[] = $conf_c->{'column'}->{'name'};
		$column_types[] = $conf_c->{'column'}->{'type'};
	}

	switch ([$source_website, $source_type]) {
		case ['sirup', 'satuan']:
			$lb = 162326;
			$ub = 162326 + 10000;
			break;
		case ['sirup', 'penyedia']:
			$lb = 38401264;
			$ub = 38401276 + 1;
			break;
	}

	$insert_types = [];

	foreach ($column_types as $i => $column_type) {
		$column_name = $column_names[$i];
		if (is_array($column_type))
			$insert_types[$column_name] = $column_type;
		switch ($column_type) {
			case 'boolean':
			case 'date':
			case 'timestamp':
				$insert_types[$column_name] = $column_type;
				break;
		}
	}

	$exists = new db_exists($dbconn, $table, ['id']);
	$insert = new db_insert($dbconn, $table, $column_names, $insert_types);

	for ($web_id = $lb; $web_id < $ub; ++$web_id) {
		printf("%s %s %d: ", $source_website, $source_type, $web_id);
		if ($exists($web_id)) {
			echo "SKIP\n";
			continue;
		}
		$res = pg_fetch_all(pg_execute($dbconn, $stmt, [$source_website, $source_type, $web_id]));
		if (count($res) == 0) {
			echo "NOT EXISTS\n";
			continue;
		}
		else
			echo "INSERT\n";
		$res = $res[0];
		$raw_id = $res['auto_id'];
		$content = $res['content'];
		$array = $get($content);
		$vals = [];
		for ($i = 0; $i < count($column_names); ++$i) {
			$array_path = $array_paths[$i];
			$array_preprocess = $array_preprocesses[$i];
			$array_type = $array_types[$i];
			$val = ($array_path === null) ? null : navigate($array, $array_path);
			if ($array_preprocess)
				$val = $array_preprocess($val);
			switch ($array_type) {
				case "raw_id": $val = $raw_id; break;
				case "web_id": $val = $web_id; break;
				default:
					if ($val !== null)
						$val = process($val, $array_type);
					break;
			}
			$vals[] = $val;
		}
		if (!$insert(...$vals))
			print_r($array);
	}

	echo '</div>';
}

define('UTC', new DateTimeZone('UTC'));

function process($val, $type) {
	if (is_array($type))
		if (count($val) == 0)
			$val = null;
		else foreach ($val as $k => $v)
			$val[$k] = process($v, $type[0]);
	else switch ($type) {
		case "integer":
			$val = (int) $val;
			break;
		case "double":
			$val = (double) $val;
			break;
		case "json":
			$val = json_encode($val);
			break;
		case "datetime":
			$val = new DateTimeImmutable($val, UTC);
			break;
	}
	return $val;
}

function navigate($tree, $path) {
	if ($path)
		if (array_key_exists($path[0], $tree))
			return navigate($tree[$path[0]], array_slice($path, 1));
		else
			return null;
	else
		return $tree;
}

main();

?>
