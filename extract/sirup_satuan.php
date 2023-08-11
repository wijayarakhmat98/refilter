<?php

namespace sirup_satuan;

use \DOMDocument, \DOMXpath;

function parse_nama($nama) {
	if ($nama === null)
		return null;
	$nama = normalize_whitespace($nama);
	$_nama = strtolower($nama);
	switch ($_nama) {
		case $_nama == '-':
		case $_nama == 'update':
			return null;
	}
	$x = true;
	foreach (str_split($_nama) as $char)
		if ($char != 'x') {
			$x = false;
			break;
		}
	if ($x)
		return null;
	return $nama;
}

function parse_alamat($alamat) {
	if ($alamat === null)
		return null;
	$alamat = normalize_whitespace($alamat);
	$_alamat = strtolower($alamat);
	switch ($_alamat) {
		case $_alamat == '.':
		case $_alamat == '-':
		case $_alamat == 'update':
		case $_alamat == 'alamat':
		case $_alamat == 'jln':
		case $_alamat == 'jl.':
			return null;
	}
	$x = true;
	foreach (str_split($_alamat) as $char)
		if ($char != 'x') {
			$x = false;
			break;
		}
	if ($x)
		return null;
	return $alamat;
}

function normalize_whitespace($text) {
	return trim(preg_replace('/\s+/', ' ', $text));
}

function get($content) {
	$doc = new DOMDocument('1.0', 'utf-8');
	$doc->loadHTML($content);
	$xpath = new DOMXpath($doc);

	$list = $xpath->query('//dl')[0];

	$array = [];

	foreach ($xpath->query('dt', $list) as $key_element) {
		$key = $xpath->query('.//text()[normalize-space()]', $key_element);
		$key = trim($key[0]->nodeValue);
		$array[$key] = null;
	}

	foreach ($xpath->query('dd', $list) as $i_key => $val_element) {
		$key = array_keys($array)[$i_key];
		$val = $xpath->query('.//text()[normalize-space()]', $val_element);
		$val = trim($val[0]->nodeValue);
		$val = preg_replace('/^[: ]*/', '', $val);
		$array[$key] = (strlen($val) > 0) ? $val : null;
	}

	$anchor = $xpath->query(sprintf('dd[*[%s]]', has_class('__cf_email__')), $list);
	if (count($anchor) > 0) {
		$anchor = $anchor[0];
		$email = $xpath->query(sprintf('//*[%s]', has_class('__cf_email__'), $anchor))[0];
		$email = $email->getAttribute('data-cfemail');
		$email = decode($email);
		$key = $xpath->query('./preceding-sibling::dt[1]', $anchor);
		$key = trim($key[0]->nodeValue);
		$array[$key] = $email;
	}

	return $array;
}

function has_class($name) {
	return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $name);
}

function decode($encoded){
	$k = hexdec(substr($encoded, 0, 2));
	for($i = 2, $decoded = ''; $i < strlen($encoded) - 1; $i += 2)
		$decoded .= chr(hexdec(substr($encoded, $i, 2)) ^ $k);
	return $decoded;
}

?>
