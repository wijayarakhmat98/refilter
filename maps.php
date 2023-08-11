<?php

namespace maps;

use \DateTimeZone, \DateTimeImmutable;

define('UTC', new DateTimeZone('UTC'));

function column($conf) {
	$name = [];
	$type = [];
	for ($i = 0; $i < count($conf['maps']); ++$i) {
		$c = $conf['maps'][$i]['column'];
		$name[] = $c['name'];
		$type[$c['name']] = $c['type'];
	}
	return [$name, $type];
}

function extract($array, $conf, $preprocess, $process) {
	$ns = $conf['factory'];
	$vals = [];
	for ($i = 0; $i < count($conf['maps']); ++$i) {
		$a = $conf['maps'][$i]['array'];
		$t = $a['type'];
		if ($f = $a['path'] ?? null)
			$val = navigate($array, $f);
		else
			$val = null;
		if ($f = $a['preprocess'] ?? null)
			$val = ($ns.'\\'.$f)($val);
		elseif ($f = $preprocess($t))
			$val = $f($val);
		if ($f = $a['process'] ?? null)
			$val = ($ns.'\\'.$f)($val);
		elseif ($f = $process($t))
			$val = $f($val);
		elseif ($val !== null)
			$val = process($val, $t);
		$vals[] = $val;
	}
	return $vals;
}

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

?>
