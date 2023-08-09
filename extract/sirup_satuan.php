<?php

namespace sirup_satuan;

use \DOMDocument, \DOMXpath;

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
		$val = substr(trim($val[0]->nodeValue), 2);
		$array[$key] = $val;
	}

	$email_anchor = $xpath->query(sprintf('dd[*[%s]]', has_class('__cf_email__')), $list)[0];
	$email_element = $xpath->query(sprintf('//*[%s]', has_class('__cf_email__'), $email_anchor))[0];
	$encoded_email = $email_element->getAttribute('data-cfemail');
	$email = decode_email($encoded_email);

	$key = $xpath->query('./preceding-sibling::dt[1]', $email_anchor);
	$key = trim($key[0]->nodeValue);
	$array[$key] = $email;

	return $array;
}

function has_class($name) {
	return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $name);
}

function decode_email($encoded_email){
	$k = hexdec(substr($encoded_email, 0, 2));
	for($i = 2, $email = ''; $i < strlen($encoded_email) - 1; $i += 2)
		$email .= chr(hexdec(substr($encoded_email, $i, 2)) ^ $k);
	return $email;
}

?>
