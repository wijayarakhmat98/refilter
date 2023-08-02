<?php

function main() {
	pg_prepare(
		$dbconn = pg_connect('dbname=refilter user=postgres password=1234'),
		$stmt = bin2hex(random_bytes(16)),
		'select content from raw where website = $1 and (type = $2 or (type is null and $2 is null)) and id = $3'
	);

	@($doc = new DOMDocument('1.0', 'utf-8'))
		->loadHTML(pg_fetch_all(
			pg_execute($dbconn, $stmt, ['modi', null, 14357]))[0]['content']
		);
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

	$array = [
		'profile' => $profile,
		'alamat' => $alamat,
		'direksi' => $direksi,
		'perizinan' => $perizinan
	];

	echo '<div style="white-space: pre; font-family: Consolas;">';
	print_r($array);
	echo '</div>';
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

main();

?>
