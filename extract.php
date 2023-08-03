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

	$namespace = $conf->{'extract'};

	require_once(sprintf('extract/%s.php', $namespace));

	$website_name = $conf->{'website'};
	$website_type = $conf->{'type'};
	$table = $conf->{'table'};

	$get = sprintf('%s\get', $namespace);

	$columns = [];
	$paths = [];
	$procs = [];
	$types = [];

	foreach ($conf->{'columns'} as $conf_c) {
		$columns[] = $conf_c->{'table'};
		$path = null;
		$proc = null;
		if (property_exists($conf_c, 'array')) {
			$path =  $conf_c->{'array'}->{'path'};
			if (property_exists($conf_c->{'array'}, 'parse'))
				$proc = sprintf('%s\%s', $namespace, $conf_c->{'array'}->{'parse'});
		}
		$paths[] = $path;
		$procs[] = $proc;
		$types[] = $conf_c->{'type'};
	}

	$exists = new db_exists($dbconn, $table, ['id']);
	$insert = new db_insert($dbconn, $table, $columns);

	for ($web_id = 162326; $web_id < 162326 + 10; ++$web_id) {
		if ($exists($web_id))
			continue;
		$res = pg_fetch_all(pg_execute($dbconn, $stmt, [$website_name, $website_type, $web_id]))[0];
		$raw_id = $res['auto_id'];
		$content = $res['content'];
		$array = $get($content);
		$vals = [];
		for ($i = 0; $i < count($columns); ++$i) {
			$path = $paths[$i];
			$type = $types[$i];
			$proc = $procs[$i];
			$val = ($path === null) ? null : navigate($array, $path);
			if ($proc)
				$val = $proc($val);
			else
				switch ($type[0]) {
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
