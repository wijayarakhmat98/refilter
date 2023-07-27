<?php

$data = $_GET;

if (!isset($data['website']) || strlen($data['website']) == 0) {
	$data['website'] = 'sirup';
	if (!isset($data['type']) || strlen($data['type']) == 0)
		$data['type'] = 'penyedia';
}

if (!isset($data['type']) || strlen($data['type']) == 0)
	$data['type'] = null;

if (array_key_exists('jump', $data))
	$data['id'] = $data['jump'];

if (!isset($data['id']) || strlen($data['id']) == 0)
	switch ([$data['website'], $data['type']]) {
	case ['sirup', 'satuan']:
		$data['id'] = 162326;
		break;
	case ['sirup', 'penyedia']:
		$data['id'] = 38401264;
		break;
	case ['sirup', 'swakelola']:
		$data['id'] = 31800157;
		break;
	case ['modi', null]:
		$data['id'] = 14357;
		break;
	default:
		$data['id'] = 0;
		break;
	}
else
	$data['id'] = (int) $data['id'];

?>

<div style="display: flex; flex-flow: column; height: 100%; margin: 0px; padding: 0px;">

<form action="" method="GET">

	<div style="text-align: center">
	<div style="float: left;">

	<button name="jump" value="<?php echo $data['id'] - 1; ?>"><?php echo $data['id'] - 1; ?></button>

	</div>

	<label for="website">Website:</label>
	<input type="text" name="website" value="<?php echo $data['website']; ?>" />

	<label for="type">Type:</label>
	<input type="text" name="type" value="<?php echo $data['type']; ?>" />

	<label for="id">ID:</label>
	<input type="number" name="id" value="<?php echo $data['id']; ?>" />

	<input type="submit" hidden />
	<button type="submit">GO</button>

	<div style="float: right;">

	<button name="jump" value="<?php echo $data['id'] + 1; ?>"><?php echo $data['id'] + 1; ?></button>

	</div>
	</div>

</form>

<div>AAA</div>

<div style="flex: 1; overflow: auto;">
<div style="display: flex; height: 100%;">

<?php

$dbconn = pg_connect('dbname=refilter user=postgres password=1234');

$stmt = bin2hex(random_bytes(16));
pg_prepare($dbconn, $stmt, '
		select
			auto_id, content
		from
			raw
		where
			website = $1 and
			(type = $2 or (type is null and $2 is null)) and
			id = $3
		order by
			auto_id desc
');

$res = pg_execute($dbconn, $stmt, [$data['website'], $data['type'], $data['id']]);

function clean($src, $data) {
	switch([$data['website'], $data['type']]) {
	case ['modi', null]:
		$src = preg_replace('/<script.*?\/script>/s', '', $src);
		$src = preg_replace('/<link.*?\/>/s', '', $src);
		$src = preg_replace('/<div.*?class="loading.*?\/div>/s', '', $src);
		$src = preg_replace('/<img.*?\/>/s', '', $src);
		break;
	}
	return $src;
}

if ($res === false)
	echo '<p>Not found.</p>';
else {
	$res = pg_fetch_all($res);
	if (count($res) == 0)
		echo '<p>Not found.</p>';
	else
		printf('
				<div style="flex: 1;">
					<iframe style="width: 99%%; height: 99%%;" srcdoc="%s"></iframe>
				</div>
				<div style="flex: 1; overflow-y: auto">
					<div style="font-family: Consolas; white-space: pre-wrap;">%s</div>
				</div>
			',
			htmlspecialchars(clean($res[0]['content'], $data)),
			htmlspecialchars($res[0]['content'])
		);
}

?>

</div>
</div>

</div>
