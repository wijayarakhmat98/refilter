<?php

define('MAX_EXECUTION_TIME', 3 * 24 * 60 * 60);

define('ASCENDING' , true );
define('DESCENDING', false);

function main() {
	set_time_limit(MAX_EXECUTION_TIME);

	$task = [];

	$task[] = new crawl_queue(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		ASCENDING , 44023335, 44023345, 2, 3, 3
	);

	$task[] = new crawl_queue(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		DESCENDING, 16998889, 16999889, 2, 3, 3
	);

	$success = [true, true];

	echo '<div style="font-family: Consolas;">';
	for (;;) {
		$terminate = true;
		for ($i = 0; $i < count($task); ++$i)
			if ($success[$i] = $task[$i]->work())
				$terminate = false;
		if ($terminate)
			break;
	}
	echo '<p>Done.</p>';
	echo '</div>';
}

function post_content($url, $html, $ascending, $retry) {
	echo '<details>';
	printf('<summary>%s [%s] [%d] [%s]</summary>', $url, ($ascending) ? 'ASCENDING ' : 'DESCENDING', $retry, ($html) ? 'SUCCESS' : 'FAIL');
	if ($html)
		printf('<div style="%s">%s</div>', 'white-space: pre-wrap;', htmlspecialchars($html));
	else
		printf('<a href="%s">%s</a>', $url, $url);
	echo '</details>';
	ob_flush(); flush();
}

class crawl_queue {
	public $urlf;
	public $attempt;
	public $cooldown;
	public $job;

	public $ascending;

	public function __construct($urlf, $ascending, $lb, $ub, $margin, $attempt, $cooldown) {
		assert($lb <= $ub && $margin >= 1 && $attempt >= 1 && $cooldown >= 0);

		$this->urlf = $urlf;
		$this->attempt = $attempt;
		$this->cooldown = $cooldown;

		/*
		 * The number of attempts should always at least be 1,
		 * and the first job will always be the major queue, therefore
		 * it is guaranteed to exists.
		 */
		$this->job = [new major_queue($ascending, $lb, $ub, $margin)];
		for ($i = 1; $i < $attempt; ++$i)
			$this->job[] = new minor_queue();

		$this->ascending = $ascending;
	}

	public function work() {
		$active = false;
		$id = null;

		/*
		 * Priority starts from the most attempt to the fewest.
		 * This is to prevent the major queue to progress too
		 * further from the minor queue, piling up reattempt that
		 * never gets processed despite passing its schedule.
		 */
		for ($i = $this->attempt - 1; $i >= 0; --$i) {
			if ($this->job[$i]->active())
				$active = true;
			$id = $this->job[$i]->pop();
			if ($id)
				break;
		}
		$retry = $i + 1;

		if (!$id)
			return $active;

		$url = sprintf($this->urlf, $id);
		$html = @file_get_contents($url);
		$this->job[0]->status($id, (bool) $html);

		/*
		 * Only minor_queue has push, major_queue don't.
		 *
		 * When id isn't null, retry is guaranteed to at
		 * least be 1. Otherwise, when retry is 0, id is
		 * null and this code will not be reached.
		 */
		if (!$html && $retry < count($this->job))
			$this->job[$retry]->push($id, time() + $this->cooldown);

		post_content($url, $html, $this->ascending, $retry);

		return true;
	}
}

class major_queue {
	public $ascending;
	public $lb;
	public $ub;
	public $margin;
	public $head;
	public $fail;

	public function __construct($ascending, $lb, $ub, $margin) {
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

	/*
	 * pop() may return null when the amount of nonexistent
	 * content encountered so far has reached the specified
	 * margin. Yet it may not terminate as further reattempt
	 * can invalidate the current margin. In such case,
	 * pop() may continue its progression.
	 *
	 * fail queue is maintained to be sorted from the oldest
	 * to the most recent encounters.
	 */
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

	/*
	 * queue is maintained to be sorted from the nearest
	 * to the furthest from scheduled reattempt.
	 */
	public function pop() {
		if (count($this->queue) == 0 || time() < $this->time[0])
			return null;
		array_shift($this->time);
		return array_shift($this->queue);
	}

	/*
	 * Queue pop() may return null but stays active, where
	 * it isn't empty but is waiting on the cool-down.
	 */
	public function active() {
		return count($this->queue) > 0;
	}
}

main();

?>
