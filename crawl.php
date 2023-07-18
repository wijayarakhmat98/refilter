<?php

define('MAX_EXECUTION_TIME', 3 * 24 * 60 * 60);

define('ASCENDING' , true );
define('DESCENDING', false);

function main() {
	set_time_limit(MAX_EXECUTION_TIME);

	echo '<h1>Ascending Crawl</h1>';
	crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		44018484, 44019484, 2, 3, 3, ASCENDING
	);

	echo '<h1>Descending Crawl</h1>';
	crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		16998889, 16999889, 2, 3, 3, DESCENDING
	);
}

class major_queue {
	public $ascending;
	public $lb;
	public $ub;
	public $margin;
	public $head;
	public $fail;

	public function __construct($lb, $ub, $margin, $ascending) {
		$this->ascending = $ascending;
		$this->lb = $lb;
		$this->ub = $ub;
		$this->margin = $margin;
		$this->head = ($ascending) ? $lb : $ub;
		$this->fail = [];
	}

	public function pop() {
		if (count($this->fail) >= $this->margin)
			return null;
		if ($this->ascending) {
			if ($this->head > $this->ub)
				return null;
		} else {
			if ($this->head < $this->lb)
				return null;
		}
		return $this->head;
	}

	public function status($id, $success) {
		if ($success) {
			if ($id == $this->head) {
				$this->fail = [];
			} else {
				$shift = array_search($id, $this->fail);
				if ($shift !== false)
					$this->fail = array_slice($this->fail, $shift + 1);
			}
		}
		if ($id == $this->head) {
			if (!$success)
				$this->fail[] = $id;
			if ($this->ascending)
				++$this->head;
			else
				--$this->head;
		}
	}

	public function active() {
		return count($this->fail) < $this->margin;
	}
}

class minor_queue {
	public $queue = [];
	public $time = [];

	public function push($id, $timestamp) {
		$this->queue[] = $id;
		$this->time[] = $timestamp;
	}

	public function pop() {
		if (count($this->queue) == 0 || time() < $this->time[0])
			return null;
		array_shift($this->time);
		return array_shift($this->queue);
	}

	public function active() {
		return count($this->queue) > 0;
	}
}

function crawl($urlf, $lb, $ub, $margin, $attempt, $cooldown, $ascending) {
	assert($lb <= $ub && $margin >= 1 && $attempt >= 1 && $cooldown >= 0);

	$job = [new major_queue($lb, $ub, $margin, $ascending)];
	for ($i = 1; $i < $attempt; ++$i)
		$job[] = new minor_queue();

	while (true) {
		$active = false;
		$id = null;

		for ($i = $attempt - 1; $i >= 0; --$i) {
			if ($job[$i]->active())
				$active = true;
			$id = $job[$i]->pop();
			if ($id)
				break;
		}
		$retry = $i + 1;

		if (!$id) { if (!$active) break; else continue; }

		$url = sprintf($urlf, $id);
		$html = @file_get_contents($url);

		$job[0]->status($id, (bool) $html);
		if (!$html && $retry < count($job))
			$job[$retry]->push($id, time() + $cooldown);

		post_content($url, $retry, $html);
	}
}

function post_content($url, $retry, $html) {
	echo '<details>';
	printf('<summary>%s [%d] [%s]</summary>', $url, $retry, ($html) ? 'SUCCESS' : 'FAIL');
	if ($html)
		printf('<div style="%s">%s</div>', 'white-space: pre-wrap;', htmlspecialchars($html));
	else
		printf('<a href="%s">%s</a>', $url, $url);
	echo '</details>';
	ob_flush(); flush();
}

main();

?>
