<?php

namespace sirup_swakelola;

use \DOMDocument, \DOMXpath;

function get($content) {
	$doc = new DOMDocument('1.0', 'utf-8');
	$doc->loadHTML($content);
	$xpath = new DOMXpath($doc);

	$list = $xpath->query('//dl')[0];
	$broken = $xpath->query('dt[h4]', $list)[0];

	$array = [];
	$subtables = [];

	foreach ($xpath->query('./preceding-sibling::dt', $broken) as $key_element) {
		$key = $xpath->query('.//text()[normalize-space()]', $key_element);
		$key = trim($key[0]->nodeValue);
		$array[$key] = null;
	}

	foreach ($xpath->query('./preceding-sibling::dd', $broken) as $i_key => $val_element) {
		$key = array_keys($array)[$i_key];

		$val = $xpath->query('.//table', $val_element);
		if (count($val) == 0)
			$val = $xpath->query('.//text()[normalize-space()]', $val_element);
		$val = $val[0];

		if ($val->nodeType == XML_TEXT_NODE)
			$val = substr(trim($val->nodeValue), 2);
		else
			$subtables[] = $key;

		$array[$key] = $val;
	}

	foreach ($subtables as $subtable) {
		$rows = [];
		$keys = [];
		$subarray = [];

		foreach ($xpath->query('tr', $array[$subtable]) as $row)
			$rows[] = $row;
		foreach ($xpath->query('.//text()[normalize-space()]', $rows[0]) as $key)
			$keys[] = trim($key->nodeValue);
		foreach ($keys as $key)
			$subarray[$key] = [];
		array_shift($rows);

		foreach ($rows as $row) {
			$vals = [];
			foreach ($xpath->query('.//text()[normalize-space()]', $row) as $val)
				$vals[] = trim($val->nodeValue);
			if (count($vals) != count($keys))
				continue;
			foreach ($keys as $i => $key)
				$subarray[$key][] = $vals[$i];
		}

		$array[$subtable] = $subarray;
	}

	$subarray = [];
	foreach ($xpath->query('./following-sibling::dt', $broken) as $key_element) {
		$key = $xpath->query('.//text()[normalize-space()]', $key_element);
		$key = trim($key[0]->nodeValue);
		$subarray[$key] = null;
	}
	foreach ($xpath->query('./following-sibling::dd//text()[normalize-space()]', $broken) as $i_key => $val) {
		$key = array_keys($subarray)[$i_key];
		$val = substr(trim($val->nodeValue), 2);
		$subarray[$key] = $val;
	}

	$key = $xpath->query('.//text()[normalize-space()]', $broken);
	$key = trim($key[0]->nodeValue);
	$array[$key] = $subarray;

	return $array;
}

?>
