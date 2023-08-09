<?php

namespace sirup_penyedia;

use \DOMDocument, \DOMXpath;

function parse_uraian_spesifikasi($text) {
	$text = normalize_whitespace($text);
	$_text = strtolower($text);
	if ($_text == '-')
		return null;
	$empty = true;
	foreach (explode(';', $_text) as $fragment)
		if (strlen(normalize_whitespace($fragment)) > 0) {
			$empty = false;
			break;
		}
	if ($empty)
		return null;
	return $text;
}

function parse_pemilihan($pemilihan) {
	$pemilihan = normalize_whitespace($pemilihan);
	$_pemilihan = strtolower($pemilihan);
	return ($_pemilihan == "belum ditentukan") ? null : $pemilihan;
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

function parse_boolean($boolean) {
	$boolean = strtolower($boolean);
	return $boolean == "ya";
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
		if (count($val) == 0)
			$val = null;
		else
			$val = $val[0];

		if ($val !== null)
			if ($val->nodeType == XML_TEXT_NODE)
				$val = trim($val->nodeValue);
			else
				$subtables[] = $key;

		$array[$key] = $val;
	}

	foreach ($subtables as $subtable) {
		$subarray = [];

		switch ($subtable) {

			case 'Pengadaan Berkelanjutan atau Sustainable Public Procurement (SPP)':

				foreach ($xpath->query('tr', $array[$subtable]) as $row) {
					$cells = $xpath->query('td', $row);
					$key = $xpath->query('.//text()[normalize-space()]', $cells[0]);
					$key = trim($key[0]->nodeValue);
					$val = $xpath->query('.//text()[normalize-space()]', $cells[1]);
					$val = trim($val[0]->nodeValue);
					$subarray[$key] = $val;
				}

				break;

			default:

				$rows = [];
				$keys = [];

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

				switch ($subtable) {

					case 'Pemanfaatan Barang/Jasa':
					case 'Jadwal Pelaksanaan Kontrak':
					case 'Jadwal Pemilihan Penyedia':

						foreach ($subarray as $key => $val)
							$subarray[$key] = $val[0];

						break;

				}

				break;
		}

		$array[$subtable] = $subarray;
	}

	return $array;
}

function has_class($name) {
	return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $name);
}

?>
