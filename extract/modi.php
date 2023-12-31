<?php

namespace modi;

use \DOMDocument, \DOMXpath;

function parse_revisi_alamat($revisi) {
	$revisi = normalize_whitespace($revisi);
	$_revisi = strtolower($revisi);
	if ($_revisi == 'alamat awal perusahaan')
		return 0;
	else
		return (int) preg_replace('/perubahan alamat perusahaan ke-/', '', $_revisi);
}

function parse_float($float) {
	$float = normalize_whitespace($float);
	$float = preg_replace('/,/', '.', $float);
	return $float;
}

function parse_periode_awal_direksi($periode) {
	$periode = normalize_whitespace($periode);
	$_periode = strtolower($periode);
	$periode_awal = normalize_whitespace(preg_split('/sampai/', $_periode)[0]);
	if ($periode_awal == '-')
		return null;
	else
		return $periode_awal;
}

function parse_periode_akhir_direksi($periode) {
	$periode = normalize_whitespace($periode);
	$_periode = strtolower($periode);
	$periode_akhir = normalize_whitespace(preg_split('/sampai/', $_periode)[1]);
	if ($periode_akhir == '-')
		return null;
	else
		return $periode_akhir;
}

function parse_update($update) {
	$update = normalize_whitespace($update);
	$_update = strtolower($update);
	if ($_update == '-')
		return null;
	return $update;
}

function parse_revisi_direksi($revisi) {
	$revisi = normalize_whitespace($revisi);
	$_revisi = strtolower($revisi);
	if ($_revisi == 'direksi awal perusahaan')
		return 0;
	else
		return (int) preg_replace('/perubahan direksi perusahaan ke-/', '', $_revisi);
}

function clean_akte($akte) {
	if ($akte === null)
		return null;
	$akte = normalize_whitespace($akte);
	$_akte = strtolower($akte);
	switch ($_akte) {
		case '-':
		case '.':
		case '0':
		case "'-":
			return null;
	}
	return $akte;
}

function normalize_whitespace($text) {
	return trim(preg_replace('/\s+/', ' ', $text));
}

function get($content) {
	$doc = new DOMDocument('1.0', 'utf-8');
	@$doc->loadHTML($content);
	$xpath = new DOMXpath($doc);

	$profile = [];
	$alamat = [];
	$direksi = [];
	$perizinan = null;

	$profile_element = $xpath->query(sprintf('//div[%s]', '@id="profile"'))[0];
	$alamat_element = $xpath->query(sprintf('//div[%s]', '@id="alamat"'))[0];
	$direksi_element = $xpath->query(sprintf('//div[%s]', '@id="direksi"'))[0];
	$perizinan_element = $xpath->query(sprintf('//div[%s]', '@id="perizinan"'))[0];

	foreach ($xpath->query('.//b', $profile_element) as $key_element) {
		$key = $xpath->query('.//text()[normalize-space()]', $key_element);
		$key = trim($key[0]->nodeValue);
		$profile[$key] = null;
	}

	foreach ($xpath->query('.//h5', $alamat_element) as $key_element) {
		$key = $xpath->query('.//text()[normalize-space()]', $key_element);
		$key = trim($key[0]->nodeValue);
		$alamat[$key] = null;
	}

	foreach ($xpath->query('.//h5', $direksi_element) as $key_element) {
		$key = $xpath->query('.//text()[normalize-space()]', $key_element);
		$key = trim($key[0]->nodeValue);
		$direksi[$key] = null;
	}

	$perizinan = $xpath->query('.//table', $perizinan_element)[0];

	foreach ($xpath->query('.//table', $profile_element) as $i_key_profile => $table) {
		$key_profile = array_keys($profile)[$i_key_profile];
		if ($i_key_profile == 0) {
			$array = [];
			foreach ($xpath->query('.//th', $table) as $key_element) {
				$key = [];
				foreach ($xpath->query('.//text()[normalize-space()]', $key_element) as $text)
					$key[] = trim($text->nodeValue);
				$key = implode(' ', $key);
				$array[$key] = [];
			}
			foreach ($xpath->query('.//tr', $table) as $i_key => $row) {
				$key = array_keys($array)[$i_key];
				$val_element = $xpath->query('.//td', $row)[1];
				$val = $xpath->query('.//text()[normalize-space()]', $val_element);
				if (count($val) > 0)
					$val = trim($val[0]->nodeValue);
				else
					$val = null;
				$array[$key] = $val;
			}
			$profile[$key_profile] = $array;
		}
		else
			$profile[$key_profile] = simple_table($xpath, $table);
	}

	foreach ($xpath->query('.//table', $alamat_element) as $i_key => $table) {
		$key = array_keys($alamat)[$i_key];
		$alamat[$key] = simple_table($xpath, $table);
	}

	foreach ($xpath->query('.//table', $direksi_element) as $i_key => $table) {
		$key = array_keys($direksi)[$i_key];
		$direksi[$key] = simple_table($xpath, $table);
	}

	$perizinan = simple_table($xpath, $perizinan);

	foreach ($xpath->query(sprintf('.//div[%s]', has_class('timeline-label')), $alamat_element) as $i_key => $timeline_element) {
		$key_alamat = array_keys($alamat)[$i_key];
		$timeline = $xpath->query('.//text()[normalize-space()]', $timeline_element);
		$key = trim($timeline[0]->nodeValue);
		$val = trim($timeline[1]->nodeValue);
		$alamat[$key_alamat][$key] = $val;
	}

	foreach ($xpath->query(sprintf('.//div[%s]', has_class('timeline-label')), $direksi_element) as $i_key => $timeline_element) {
		$key_direksi = array_keys($direksi)[$i_key];
		$timeline = $xpath->query('.//text()[normalize-space()]', $timeline_element);
		$key = trim($timeline[0]->nodeValue);
		$val = trim($timeline[1]->nodeValue);
		$direksi[$key_direksi][$key] = $val;
	}

	$array = [
		'profile' => $profile,
		'alamat' => $alamat,
		'direksi' => $direksi,
		'perizinan' => $perizinan
	];

	return $array;
}

function has_class($name) {
	return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $name);
}

function simple_table($xpath, $table) {
	$array = [];
	foreach ($xpath->query('.//tr', $table) as $i_row => $row)
		if ($i_row == 0)
			foreach ($xpath->query('.//th', $row) as $key_element) {
				$key = [];
				foreach ($xpath->query('.//text()[normalize-space()]', $key_element) as $text)
					$key[] = trim($text->nodeValue);
				$key = implode(' ', $key);
				$array[$key] = [];
			}
		else
			foreach ($xpath->query('.//td', $row) as $i_key => $val_element) {
				$key = array_keys($array)[$i_key];
				$val = $xpath->query('.//text()[normalize-space()]', $val_element);
				if (count($val) > 0)
					$val = trim($val[0]->nodeValue);
				else
					$val = null;
				$array[$key][] = $val;
			}
	return $array;
}

?>
