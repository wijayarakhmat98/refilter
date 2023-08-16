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

function extract($array, $conf, $preprocess, $process, $postprocess) {
	$p = [];
	foreach ($conf['maps'] as $map)
		$p[] = initialize_pointer($array, $map['array']);
	$ns = $conf['factory'];
	$rows = [];
	for (;;) {
		$done = true;
		$null = true;
		$vals = [];
		for ($i = 0; $i < count($conf['maps']); ++$i) {
			$a = $conf['maps'][$i]['array'];
			if ($f = $p[$i]['path'] ?? null) {
				$val = navigate($array, $f);
				if ($val !== null && isset($a['get']) && $a['get'] == 'key')
					$val = end($f);
				if (!($a['meta'] ?? null) && $val !== null)
					$null = false;
			}
			else
				$val = null;
			$vals[] = $val;
			pointer_next($array, $p[$i]);
			if ($p[$i]['repeat'] == 0)
				$done = false;
		}
		if (!$null) {
			for ($i = 0; $i < count($conf['maps']); ++$i) {
				$a = $conf['maps'][$i]['array'];
				$t = $a['type'];
				$val = $vals[$i];
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
				if ($f = $a['postprocess'] ?? null)
					$val = ($ns.'\\'.$f)($val);
				elseif ($f = $postprocess($t))
					$val = $f($val);
				$vals[$i] = $val;
			}
			$rows[] = $vals;
		}
		if ($done)
			break;
	}
	return $rows;
}

function initialize_pointer($array, $a) {
	$p = [
		'counter' => [],
		'cursor_substitute' => [],
		'cursor' => $a['cursor'] ?? $a['path'] ?? [],
		'path_substitute' => [],
		'path' => $a['path'] ?? null,
		'repeat' => 0
	];
	if ($p['path']) {
		foreach ($p['cursor'] as $pos => $key)
			if (is_array($key)) {
				$p['counter'][] = 0;
				$p['cursor_substitute'][] = $pos;
			}
		foreach ($p['path'] as $pos => $key)
			if (is_array($key))
				$p['path_substitute'][] = $pos;
		pointer_build($array, $p);
	}
	return $p;
}

function pointer_next($array, &$p) {
	for ($i = count($p['counter']) - 1; $i >= 0; --$i) {
		++$p['counter'][$i];
		$keys = array_keys(navigate($array, array_slice($p['cursor'], 0, $p['cursor_substitute'][$i])) ?? []);
		if ($p['counter'][$i] < count($keys))
			break;
		else
			$p['counter'][$i] = 0;
	}
	if ($i < 0)
		++$p['repeat'];
	if ($p['path'])
		pointer_build($array, $p);
}

function pointer_build($array, &$p) {
	$cursor = [];
	$pc = 0;
	foreach ($p['cursor'] as $pos => $key)
		if (in_array($pos, $p['cursor_substitute']))
			$cursor[] = array_keys(navigate($array, $cursor) ?: [null])[$p['counter'][$pc++]];
		else
			$cursor[] = $key;
	$p['cursor'] = $cursor;
	$path = [];
	$pc = 0;
	foreach ($p['path'] as $pos => $key)
		if (in_array($pos, $p['path_substitute']))
			$path[] = array_keys(navigate($array, $path) ?: [null])[$p['counter'][$pc++]];
		else
			$path[] = $key;
	$p['path'] = $path;
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
		if ($path === null)
			return null;
		else
			return $tree;
}

?>
