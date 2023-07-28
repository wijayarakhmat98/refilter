<?php

$data = $_GET;

if (!isset($data['website']) || strlen($data['website']) == 0) {
	$data['website'] = 'sirup';
	if (!isset($data['type']) || strlen($data['type']) == 0)
		$data['type'] = 'penyedia';
}

if (!isset($data['type']) || strlen($data['type']) == 0)
	$data['type'] = null;

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

switch ([$data['website'], $data['type']]) {
case ['sirup', 'satuan']:
	$data['urlf'] = 'https://sirup.lkpp.go.id/sirup/home/showPaKpaModal?idSatker=%d';
	break;
case ['sirup', 'penyedia']:
	$data['urlf'] = 'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d';
	break;
case ['sirup', 'swakelola']:
	$data['urlf'] = 'https://sirup.lkpp.go.id/sirup/home/detailPaketSwakelolaPublic2017?idPaket=%d';
	break;
case ['modi', null]:
	$data['urlf'] = 'https://modi.esdm.go.id/portal/detailPerusahaan/%d';
	break;
default:
	$data['urlf'] = '';
	break;
}

$data['url'] = sprintf($data['urlf'], $data['id']);

$dbconn = pg_connect('dbname=refilter user=postgres password=1234');

$stmt1 = bin2hex(random_bytes(16));
pg_prepare($dbconn, $stmt1, '
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

$res = pg_execute($dbconn, $stmt1, [$data['website'], $data['type'], $data['id']]);
if ($res === false)
	$res = null;
else {
	$res = pg_fetch_all($res);
	if (count($res) == 0)
		$res = null;
	else
		$res = $res[0]['content'];
}

$content = $res;
$clean = $res;

if ($res != null)
	switch([$data['website'], $data['type']]) {
	case ['modi', null]:
		$clean = preg_replace('/<script.*?\/script>/s', '', $clean);
		$clean = preg_replace('/<link.*?\/>/s', '', $clean);
		$clean = preg_replace('/<div.*?class="loading.*?\/div>/s', '', $clean);
		$clean = preg_replace('/<img.*?\/>/s', '', $clean);
		break;
	}

$stmt2 = bin2hex(random_bytes(16));
pg_prepare($dbconn, $stmt2, '
	select distinct
		id
	from
		raw
	where
		website = $1 and
		(type = $2 or (type is null and $2 is null))
	order by
		id asc
');

$res = pg_execute($dbconn, $stmt2, [$data['website'], $data['type']]);
if ($res === false)
	$res = null;
else {
	$res = pg_fetch_all($res);
	if (count($res) == 0)
		$res = null;
	else
		$res[0];
}

if ($res == null)
	$stat = null;
else {
	$stat = [];

	$stat['entries'] = [];
	for ($i = 0; $i < count($res); ++$i)
		$stat['entries'][] = $res[$i]['id'];
	$stat['uniques'] = array_unique($stat['entries']);
	$stat['duplicate entries'] = array_diff_assoc($stat['entries'], $stat['uniques']);
	$stat['duplicate uniques'] = array_unique($stat['duplicate entries']);

	$stat['lower bound'] = $res[0]['id'];
	$stat['upper bound'] = $res[array_key_last($res)]['id'];
	$stat['uniques'] = count($stat['uniques']);
	$stat['holes'] = $stat['upper bound'] - $stat['lower bound'] + 1 - $stat['uniques'];
	$stat['duplicate uniques'] = count($stat['duplicate uniques']);
	$stat['entries'] = count($stat['entries']);
	$stat['duplicate entries'] = count($stat['duplicate entries']);
}

$stat_order = ['lower bound', 'upper bound', 'uniques', 'holes', 'duplicate uniques', 'entries', 'duplicate entries'];

?>

<div style="height: 100vh; display: flex; flex-direction: column; padding: 1rem; box-sizing: border-box;">
	<div style="flex: 0;">
		<div style="display: flex;">
			<div style="flex: 0;">
				<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" style="display: inline-block; margin: 0;">
					<input type="hidden" name="website" value="<?php echo $data['website']; ?>" />
					<input type="hidden" name="type" value="<?php echo $data['type']; ?>" />
					<input type="hidden" name="id" value="<?php echo $data['id'] - 1; ?>" />
					<button type="submit"><?php echo $data['id'] - 1; ?></button>
				</form>
			</div>
			<div style="flex: 1; text-align: center;">
				<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" style="display: inline-block; margin: 0;">
					<label for="website">Website:</label>
					<input type="text" name="website" value="<?php echo $data['website']; ?>" />
					<label for="type">Type:</label>
					<input type="text" name="type" value="<?php echo $data['type']; ?>" />
					<label for="id">ID:</label>
					<input type="number" name="id" value="<?php echo $data['id']; ?>" />
					<button type="submit">Find</button>
				</form>
			</div>
			<div style="flex: 0;">
				<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" style="display: inline-block; margin: 0;">
					<input type="hidden" name="website" value="<?php echo $data['website']; ?>" />
					<input type="hidden" name="type" value="<?php echo $data['type']; ?>" />
					<input type="hidden" name="id" value="<?php echo $data['id'] + 1; ?>" />
					<button type="submit"><?php echo $data['id'] + 1; ?></button>
				</form>
			</div>
		</div>
	</div>
	<div style="flex: 0;">
		<div style="text-align: center;">
<?php if ($stat != null): ?>
		<p><?php
			for ($i = 0; $i < count($stat_order); ++$i)
				printf('%s: %d%s', $stat_order[$i], $stat[$stat_order[$i]], ($i < count($stat_order) - 1) ? '&emsp;' : '');
		?></p>
<?php else: ?>
		<p>Not found</p>
<?php endif; ?>
		</div>
	</div>
	<div style="flex: 1; overflow: auto;">
		<div style="height: 100%; display: flex;">
			<div style="flex: 1; overflow: auto;">
<?php if ($stat != null): ?>
<?php if ($content == null): ?>
				<div style="width: 100%; height: 100%; display: table; text-align: center;">
					<span style="display: table-cell; vertical-align: middle;">
						<?php printf('<a href="%s">%s</a>', $data['url'], $data['url']); ?>
					</span>
				</div>
<?php else: ?>
				<div style="width: 100%; height: 100%; padding: 1rem; box-sizing: border-box;">
					<iframe style="width: 100%; height: 100%;" srcdoc="<?php echo htmlspecialchars($clean) ?>"></iframe>
				</div>
<?php endif; ?>
<?php endif; ?>
			</div>
			<div style="flex: 1; overflow: auto;">
<?php if ($stat != null): ?>
<?php if ($content == null): ?>
				<div style="width: 100%; height: 100%; display: table; text-align: center;">
					<span style="display: table-cell; vertical-align: middle;">Not found.</span>
				</div>
<?php else: ?>
				<div style="width: 100%; height: 100%; overflow: auto;">
					<div style="white-space: pre; font-family: Consolas;"><?php echo htmlspecialchars($content); ?></div>
				</div>
<?php endif; ?>
<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<style>
	body {
		padding: 0;
		margin: 0;
	}
</style>
