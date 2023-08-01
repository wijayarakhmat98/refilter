<?php

function main() {
	pg_prepare(
		$dbconn = pg_connect('dbname=refilter user=postgres password=1234'),
		$stmt = bin2hex(random_bytes(16)),
		'select content from raw where website = $1 and type = $2 and id = $3'
	);

	($doc = new DOMDocument('1.0', 'utf-8'))
		->loadHTML(pg_fetch_all(
			pg_execute($dbconn, $stmt, ['sirup', 'penyedia', 38401264]))[0]['content']
		);
	$xpath = new DOMXpath($doc);

	$table = $xpath->query(sprintf('//div[%s]/table', '@id="detil"'));
	$table = $table[0];

	$array = [];
	$subtables = [];

	foreach ($xpath->query('tr', $table) as $row) {
		$cell_key = $xpath->query(sprintf('td[%s]', has_class('label-left')), $row)[0];
		$cell_val = $xpath->query(sprintf('td[not(%s)]', has_class('label-left')), $row)[0];

		$key = $xpath->query('.//text()[normalize-space()]', $cell_key);
		$key = trim($key[0]->nodeValue);

		$val = $xpath->query('.//table', $cell_val);
		if (count($val) == 0)
			$val = $xpath->query('.//text()[normalize-space()]', $cell_val);
		$val = $val[0];

		if ($val->nodeType == XML_TEXT_NODE)
			$val = trim($val->nodeValue);
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

	echo '<div style="white-space: pre; font-family: Consolas;">';
	print_r($array);
	echo '</div>';
}

function has_class($name) {
	return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $name);
}

main();

?>
