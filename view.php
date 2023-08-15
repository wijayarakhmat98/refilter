<?php

function traverse($node, $last = []) {
	static $whitelist = [XML_HTML_DOCUMENT_NODE, XML_ELEMENT_NODE, XML_TEXT_NODE, XML_ATTRIBUTE_NODE];
	if (!in_array($node->nodeType, $whitelist))
		return;
	for ($i = 0; $i < count($last); ++$i)
		if ($i < count($last) - 1)
			echo (!$last[$i]) ? '|   ' : '    ';
		else
			if ($node->nodeType == XML_ATTRIBUTE_NODE)
				echo (!$last[$i]) ? '|:: ' : 'L:: ';
			else
				echo (!$last[$i]) ? '+-- ' : 'L-- ';
	if ($node->nodeType == XML_TEXT_NODE)
		echo htmlspecialchars(sprintf("%s\n", trim($node->nodeValue)));
	elseif ($node->nodeType == XML_ATTRIBUTE_NODE)
		echo htmlspecialchars(sprintf("%s: %s\n", $node->nodeName, $node->nodeValue));
	else
		echo htmlspecialchars(sprintf("%s\n", $node->nodeName));
	if ($node->nodeType != XML_ATTRIBUTE_NODE) {
		$children = [];
		if ($node->attributes)
			foreach ($node->attributes as $attribute)
				$children[] = $attribute;
		foreach ($node->childNodes as $child)
			if (in_array($child->nodeType, $whitelist))
				if ($child->nodeType != XML_TEXT_NODE || strlen(trim($child->nodeValue)) > 0)
					$children[] = $child;
		foreach ($children as $i => $child)
			traverse($child, [...$last, !($i < count($children) - 1)]);
	}
}

function better_dump($obj) {
	ob_start();
	var_dump($obj);
	$dump = ob_get_contents();
	ob_end_clean();
	$dump = preg_replace('/]=>\n\s*/', '] => ', $dump);
	echo $dump;
}

require_once('maps.php');
require_once('database.php');

$jump = [1, 2, 4, 8, 16];

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

if (!isset($data['factory']) || strlen($data['factory']) == 0)
	switch ([$data['website'], $data['type']]) {
	case ['sirup', 'satuan']:
		$data['factory'] = 'sirup_satuan';
		break;
	case ['sirup', 'penyedia']:
		$data['factory'] = 'sirup_penyedia';
		break;
	case ['sirup', 'swakelola']:
		$data['factory'] = 'sirup_swakelola';
		break;
	case ['modi', null]:
		$data['factory'] = 'modi';
		break;
	default:
		$data['factory'] = null;
		break;
	}

if (!isset($data['table']) || strlen($data['table']) == 0)
	switch ([$data['website'], $data['type']]) {
	case ['sirup', 'satuan']:
		$data['table'] = 'sirup_satuan';
		break;
	case ['sirup', 'penyedia']:
		$data['table'] = 'sirup_penyedia';
		break;
	case ['sirup', 'swakelola']:
		$data['table'] = 'sirup_swakelola';
		break;
	case ['modi', null]:
		$data['table'] = 'modi_profil';
		break;
	default:
		$data['table'] = null;
		break;
	}

$conf = null;

if ($data['factory'] && $data['table'])
	foreach (json_decode(file_get_contents('maps.json'), true) as $c)
		if (
			$c['source']['website'] == $data['website'] &&
			$c['source']['type'] == $data['type'] &&
			$c['table'] == $data['table']
		) {
			$conf = $c;
			break;
		}

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
		$res = $res[0];
}

if ($res) {
	$raw_id = (int) $res['auto_id'];
	$web_id = $data['id'];
	$content = $res['content'];
}
else {
	$raw_id = null;
	$web_id = null;
	$content = null;
}

$clean = $content;

if ($clean)
	switch([$data['website'], $data['type']]) {
	case ['modi', null]:
		$clean = preg_replace('/<script.*?\/script>/s', '', $clean);
		$clean = preg_replace('/<link.*?\/>/s', '', $clean);
		$clean = preg_replace('/<div.*?class="loading.*?\/div>/s', '', $clean);
		$clean = preg_replace('/<img.*?\/>/s', '', $clean);
		break;
	}

$doc = new DOMDocument('1.0', 'utf-8');
if ($content)
	@$doc->loadHTML($content);

if ($content && $data['factory']) {
	require_once('extract/'.$data['factory'].'.php');
	$extract = @($data['factory'].'\get')($content);
}
else {
	$extract = null;
}

if ($extract && $conf) {
	$maps = maps\extract($extract, $conf,
		fn($t) => null,
		fn($t) =>
			$t == 'raw_id' ? fn($v) => $raw_id : (
			$t == 'web_id' ? fn($v) => $web_id : (
			null
		)),
		fn($t) => null
	);
	list($column_name, $column_type) = maps\column($conf);
	$insert = new db_insert($dbconn, $conf['table'], $column_name, $column_type);
	$insert_signature = $insert->signature($maps);
	$insert_query = $insert->query($insert_signature);
	$insert_flat = $insert->flatten($maps);
	array_unshift($insert_flat, 'dummy');
	unset($insert_flat[0]);
}
else {
	$maps = null;
	$column_name = null;
	$column_type = null;
	$insert_signature = null;
	$insert_query = null;
	$insert_flat = null;
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
<?php
for ($i = count($jump) - 1; $i >= 0; --$i)
	printf('
			<div style="flex: 0;">
				<form action="%s" method="GET" style="display: inline-block; margin: 0 0.1rem;">
					<input type="hidden" name="website" value="%s" />
					<input type="hidden" name="type" value="%s" />
					<input type="hidden" name="id" value="%s" />
					<input type="hidden" name="factory" value="%s" />
					<input type="hidden" name="table" value="%s" />
					<button type="submit">%s</button>
				</form>
			</div>
		',
		htmlspecialchars($_SERVER['PHP_SELF']),
		$data['website'],
		$data['type'],
		$data['id'] - $jump[$i],
		$data['factory'],
		$data['table'],
		$data['id'] - $jump[$i]
	);
?>
			<div style="flex: 1;">
				<form id="form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" style="display: inline-block; margin: 0; padding: 0 0.1rem; box-sizing: border-box; width: 100%;">
					<div style="display: flex;">
						<div style="flex: 1;">
						</div>
						<div style="flex: 0 1 auto; margin: 0 0.1rem;">
							<div style="display: flex;">
								<div style="flex: 0; margin: 0 0.1rem;">
									<label for="website">Website:</label>
								</div>
								<div style="flex: 1; margin: 0 0.1rem;">
									<input type="text" name="website" value="<?php echo $data['website']; ?>" style="width: 100%;" />
								</div>
							</div>
						</div>
						<div style="flex: 0 1 auto; margin: 0 0.1rem;">
							<div style="display: flex;">
								<div style="flex: 0; margin: 0 0.1rem;">
									<label for="type">Type:</label>
								</div>
								<div style="flex: 1; margin: 0 0.1rem;">
									<input type="text" name="type" value="<?php echo $data['type']; ?>" style="width: 100%;" />
								</div>
							</div>
						</div>
						<div style="flex: 0 1 auto; margin: 0 0.1rem;">
							<div style="display: flex;">
								<div style="flex: 0; margin: 0 0.1rem;">
									<label for="id">ID:</label>
								</div>
								<div style="flex: 1; margin: 0 0.1rem;">
									<input type="text" name="id" value="<?php echo $data['id']; ?>" style="width: 100%;" />
								</div>
							</div>
						</div>
						<div style="flex: 0 1 auto; margin: 0 0.1rem;">
							<div style="display: flex;">
								<div style="flex: 0; margin: 0 0.1rem;">
									<label for="factory">Factory:</label>
								</div>
								<div style="flex: 1; margin: 0 0.1rem;">
									<input type="text" name="factory" value="<?php echo $data['factory']; ?>" style="width: 100%;" />
								</div>
							</div>
						</div>
						<div style="flex: 0 1 auto; margin: 0 0.1rem;">
							<div style="display: flex;">
								<div style="flex: 0; margin: 0 0.1rem;">
									<label for="table">Table:</label>
								</div>
								<div style="flex: 1; margin: 0 0.1rem;">
									<input type="text" name="table" value="<?php echo $data['table']; ?>" style="width: 100%;" />
								</div>
							</div>
						</div>
						<div style="flex: 0 1 auto; margin: 0 0.1rem;">
							<button type="submit">Go</button>
						</div>
						<div style="flex: 1;">
						</div>
					</div>

				</form>
			</div>
<?php
for ($i = 0; $i < count($jump); ++$i)
	printf('
			<div style="flex: 0;">
				<form action="%s" method="GET" style="display: inline-block; margin: 0 0.1rem;">
					<input type="hidden" name="website" value="%s" />
					<input type="hidden" name="type" value="%s" />
					<input type="hidden" name="id" value="%s" />
					<input type="hidden" name="factory" value="%s" />
					<input type="hidden" name="table" value="%s" />
					<button type="submit">%s</button>
				</form>
			</div>
		',
		htmlspecialchars($_SERVER['PHP_SELF']),
		$data['website'],
		$data['type'],
		$data['id'] + $jump[$i],
		$data['factory'],
		$data['table'],
		$data['id'] + $jump[$i]
	);
?>
		</div>
	</div>
	<div style="flex: 0;">
		<div style="text-align: center;">
<?php if ($stat != null): ?>
		<p><?php
			for ($i = 0; $i < count($stat_order); ++$i)
				printf('%s: %d%s', $stat_order[$i], $stat[$stat_order[$i]], ($i < count($stat_order) - 1) ? '&emsp;' : '');
		?></p>
<?php endif; ?>
		</div>
	</div>
	<div style="flex: 1; overflow: auto;">
<?php if ($stat == null): ?>
		<div style="width: 100%; height: 100%; display: table; text-align: center;">
			<span style="display: table-cell; vertical-align: middle;">
				Not found.
			</span>
		</div>
<?php else: ?>
<?php if ($content == null): ?>
		<div style="width: 100%; height: 100%; display: table; text-align: center;">
			<span style="display: table-cell; vertical-align: middle;">
				<?php printf('<a href="%s">%s</a>', $data['url'], $data['url']); ?>
			</span>
		</div>
<?php else: ?>
		<div style="height: 100%; display: flex;">
			<div style="flex: 1; overflow: auto;">
				<div style="width: 100%; height: 100%; padding: 0 1rem 1rem 1rem; box-sizing: border-box;">
					<div style="height: 100%; display: flex; flex-direction: column;">
						<div style="flex: 0;">
							<p style="margin: 0 0 1rem 0;"><?php printf('<a href="%s">%s</a>', $data['url'], $data['url']); ?></p>
						</div>
						<div style="flex: 1;">
							<iframe id="frame" class="scroll" style="width: 100%; height: 100%;" srcdoc="<?php echo htmlspecialchars($clean) ?>"></iframe>
						</div>
					</div>
				</div>
			</div>
			<div style="flex: 1; overflow: auto;">
				<div style="width: 100%; height: 100%;">
					<div style="height: 100%; display: flex; flex-direction: column;">
						<div style="flex: 0;">
							<div style="height: 100%; margin: 0 0 1rem 0;">
								<button style="margin: 0 0.1rem;" onclick="javascript:change_tab('tab_1');">Source</button>
								<button style="margin: 0 0.1rem;" onclick="javascript:change_tab('tab_2');">Tree</button>
								<button style="margin: 0 0.1rem;" onclick="javascript:change_tab('tab_3');">Extract</button>
								<button style="margin: 0 0.1rem;" onclick="javascript:change_tab('tab_4');">Maps</button>
								<button style="margin: 0 0.1rem;" onclick="javascript:change_tab('tab_5');">Insert</button>
							</div>
						</div>
						<div style="flex: 1; overflow: auto;">
							<div id="tab_control">
								<div id="tab_1" class="scroll" style="width: 100%; height: 100%; overflow: auto; display: block;">
									<div style="white-space: pre; font-family: Consolas;"><?php echo htmlspecialchars($content); ?></div>
								</div>
								<div id="tab_2" class="scroll" style="width: 100%; height: 100%; overflow: auto; display: none;">
									<div style="white-space: pre; font-family: Consolas;"><?php traverse($doc); ?></div>
								</div>
								<div id="tab_3" class="scroll" style="width: 100%; height: 100%; overflow: auto; display: none;">
									<div style="white-space: pre; font-family: Consolas;"><?php
										if ($extract)
											better_dump($extract);
									?></div>
								</div>
								<div id="tab_4" class="scroll" style="width: 100%; height: 100%; overflow: auto; display: none;">
									<div style="white-space: pre; font-family: Consolas;"><?php
										if ($maps)
											better_dump(array_combine($column_name, $maps));
									?></div>
								</div>
								<div id="tab_5" class="scroll" style="width: 100%; height: 100%; overflow: auto; display: none;">
									<div style="white-space: pre; font-family: Consolas;"><?php
										if ($insert_flat) {
											echo "signature\n";
											foreach (explode(';', $insert_signature) as $sig)
												echo "\t", $sig, "\n";
											echo "\n";
											better_dump($insert_flat);
											echo "\n";
											echo $insert_query;
										}
									?></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php endif; ?>
<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
	function save_scroll() {
		var scroll_pos = localStorage.getItem('scroll_pos');
		scroll_pos = (scroll_pos === null) ? {} : JSON.parse(scroll_pos);
		var div = document.getElementsByClassName('scroll');
		for (var i = 0; i < div.length; ++i)
			if (div[i]['style']['display'] != 'none')
				switch (div[i]['nodeName']) {
				case 'DIV':
					scroll_pos[div[i]['id']] = {
						x: div[i]['scrollLeft'],
						y: div[i]['scrollTop']
					};
					break;
				case 'IFRAME':
					scroll_pos[div[i]['id']] = {
						x: div[i]['contentWindow']['scrollX'],
						y: div[i]['contentWindow']['scrollY']
					};
					break;
				}
		scroll_pos = JSON.stringify(scroll_pos);
		localStorage.setItem('scroll_pos', scroll_pos);
	}

	function load_scroll() {
		var scroll_pos = localStorage.getItem('scroll_pos');
		scroll_pos = (scroll_pos === null) ? {} : JSON.parse(scroll_pos);
		var div = document.getElementsByClassName('scroll');
		for (var i = 0; i < div.length; ++i)
			if (div[i]['style']['display'] != 'none')
				if (div[i]['id'] in scroll_pos)
					switch (div[i]['nodeName']) {
					case 'DIV':
						div[i]['scrollLeft'] = scroll_pos[div[i]['id']]['x'];
						div[i]['scrollTop'] = scroll_pos[div[i]['id']]['y'];
						break;
					case 'IFRAME':
						div[i]['onload'] = ((iframe, x, y) =>
							() => iframe['contentWindow']['scroll'](x, y)
						)(
							div[i],
							scroll_pos[div[i]['id']]['x'],
							scroll_pos[div[i]['id']]['y']
						);
						break;
					}
	}

	function activate_tab(tab_id) {
		var tab_control = document.getElementById('tab_control');
		if (tab_control !== null) {
			var active_tab = document.getElementById(tab_id);
			for (var i = 0; i < tab_control.childNodes.length; ++i) {
				var node = tab_control.childNodes[i];
				if (node.nodeType == Node.ELEMENT_NODE)
					node.style.display = (node == active_tab) ? 'block' : 'none';
			}
		}
	}

	function change_tab(tab_id) {
		localStorage.setItem('active_tab', tab_id);
		save_scroll();
		activate_tab(tab_id);
		load_scroll();
	}

	window.onbeforeunload = function (event) {
		save_scroll();
	}

	var tab_id = localStorage.getItem('active_tab');
	if (tab_id !== null)
		activate_tab(tab_id);
	load_scroll();
</script>

<style>
	body {
		padding: 0;
		margin: 0;
	}
</style>
