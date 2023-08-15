<?php

namespace sirup_swakelola;

use \DOMDocument, \DOMXpath;

function clean_volume($volume) {
	if ($volume === null)
		return null;
	$volume = normalize_whitespace($volume);
	$_volume = strtolower($volume);
	switch ($_volume) {
		case '-':
		case '0':
		case "'0":
		case '0.0':
			return null;
	}
	return $volume;
}

function sum_pagu($pagu) {
	if (count($pagu) == 0)
		return null;
	$sum = 0;
	foreach ($pagu as $part)
		$sum += $part;
	return $sum;
}

define('MONTH', [
		'januari' => 1,
		'februari' => 2,
		'maret' => 3,
		'april' => 4,
		'mei' => 5,
		'juni' => 6,
		'juli' => 7,
		'agustus' => 8,
		'september' => 9,
		'oktober' => 10,
		'november' => 11,
		'desember' => 12
]);

function parse_month($date) {
	$date = normalize_whitespace($date);
	$date = strtolower($date);
	if ($date == 'n/a')
		return null;
	list($month, $year) = explode(' ', $date);
	$year = (int) $year;
	$month = MONTH[$month];
	return sprintf('%04d-%02d-01 00:00:00.00', $year, $month);
}

function rotate_table($table_col) {
	$table_row = array_fill(0, count($table_col[array_keys($table_col)[0]]), []);
	foreach ($table_col as $column => $rows)
		foreach ($rows as $row => $val)
			$table_row[$row][$column] = $val;
	return $table_row;
}

function normalize_whitespace($text) {
	return trim(preg_replace('/\s+/', ' ', $text));
}

function get($content) {
	$doc = new DOMDocument('1.0', 'utf-8');
	$doc->loadHTML($content);
	$xpath = new DOMXpath($doc);

	$list = $xpath->query('//dl')[0];
	$broken = $xpath->query('dt[h4]', $list)[0];

	$array = [];
	$subtables = [];

	foreach ($xpath->query('./preceding-sibling::dt|./preceding-sibling::dd', $broken) as $element) {
		if ($element->nodeName == 'dt') {
			$key = $xpath->query('.//text()[normalize-space()]', $element);
			$key = trim($key[0]->nodeValue);
			$array[$key] = [];
		}
		elseif ($element->nodeName == 'dd') {
			$val = $xpath->query('.//table', $element);
			if (count($val) == 0)
				$val = $xpath->query('.//text()[normalize-space()]', $element);
			$val = $val[0];

			if ($val->nodeType == XML_TEXT_NODE) {
				$val = trim($val->nodeValue);
				$val = preg_replace('/^[: ]*/', '', $val);
				if (strlen($val) == 0)
					$val = null;
			}
			else
				$subtables[] = $key;

			if ($val !== null)
				$array[$key][] = $val;
		}
	}

	foreach ($array as $key => $val) {
		switch (true) {
		case $key == 'Penyelenggara Swakelola':
			break;
		case count($val) == 0:
			$array[$key] = null;
			break;
		case (count($val) == 1):
			$array[$key] = $val[0];
			break;
		}
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
		$val = trim($val->nodeValue);
		$val = preg_replace('/^[: ]*/', '', $val);
		$subarray[$key] = $val;
	}

	$key = $xpath->query('.//text()[normalize-space()]', $broken);
	$key = trim($key[0]->nodeValue);
	$array[$key] = $subarray;

	return $array;
}

?>
