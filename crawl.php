<?php

define('MAX_EXECUTION_TIME', 3 * 24 * 60 * 60);

define('ASCENDING' , true );
define('DESCENDING', false);

function main() {
	set_time_limit(MAX_EXECUTION_TIME);

	$dbconn = pg_connect('dbname=test user=postgres password=1234');

	$db = [
		'sirup_penyedia' => []
	];

	$db['sirup_penyedia']['select'] = new class($dbconn) {
		public $stmt;
		public function __construct(public $dbconn) {
			$this->stmt = bin2hex(random_bytes(16));
			pg_prepare($dbconn, $this->stmt, 'select id from sirup_penyedia where id = $1');
		}
		public function __invoke($id) {
			$res = pg_execute($this->dbconn, $this->stmt, [$id]);
			return ($res === false) ? null : count(pg_fetch_all($res)) > 0;
		}
	};

	$db['sirup_penyedia']['insert'] = new class($dbconn) {
		public $stmt;
		public function __construct(public $dbconn) {
			$this->stmt = bin2hex(random_bytes(16));
			pg_prepare($dbconn, $this->stmt, 'insert into sirup_penyedia(id, content) values($1, $2)');
		}
		public function __invoke($id, $content) {
			return pg_execute($this->dbconn, $this->stmt, [$id, $content]);
		}
	};

	$task = new interleave();

	$task->add(2, new crawl_queue(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		function ($id, $success, $content) use ($db) {
			static $post = new post_content(
				'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
				ASCENDING, 3
			);
			if ($success && !$db['sirup_penyedia']['select']($id))
				$db['sirup_penyedia']['insert']($id, $content);
			$post($id, $success, $content);
		},
		ASCENDING, 44023335, 44023345, 2, 3, 3
	));

	$task->add(2, new crawl_queue(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		function ($id, $success, $content) use ($db) {
			static $post = new post_content(
				'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
				DESCENDING, 3
			);
			if ($success && !$db['sirup_penyedia']['select']($id))
				$db['sirup_penyedia']['insert']($id, $content);
			$post($id, $success, $content);
		},
		DESCENDING, 16998889, 16999889, 2, 3, 3
	));

	echo '<div style="font-family: Consolas;">';
	while ($task->work())
		;
	echo '<p>Done.</p>';
	echo '</div>';
}

class post_content {
	public $urlf;
	public $ascending;
	public $attempt;
	public $fail;

	public function __construct($urlf, $ascending, $attempt) {
		$this->urlf = $urlf;
		$this->ascending = ($ascending) ? '+++' : '---';
		$this->fail = [];
		$this->attempt = $attempt;
	}

	public function __invoke($id, $success, $content) {
		$url = sprintf($this->urlf, $id);
		$trial = 1;
		if (!$success) {
			if (!array_key_exists($id, $this->fail))
				$this->fail[$id] = 0;
			$trial = ++$this->fail[$id];
		}
		echo '<details>';
		printf('<summary>%s [%s] [ %d ] [%s]</summary>', $url, $this->ascending, $trial, ($success) ? 'SUCCESS' : 'FAIL');
		if ($success)
			printf('<div style="%s">%s</div>', 'white-space: pre-wrap;', htmlspecialchars($content));
		else
			printf('<a href="%s">%s</a>', $url, $url);
		echo '</details>';
		ob_flush(); flush();
		if (!$success && $this->fail[$id] >= $this->attempt)
			unset($this->fail[$id]);
	}
}

class interleave {
	/* rate, task, and active are parallels */
	public $rate = [];
	public $task = [];
	public $active = [];

	public $pc = -1;
	public $jmp = 0;

	public function add($rate, $task) {
		assert($rate > 0);

		$this->rate[] = $rate;
		$this->task[] = $task;
		$this->active[] = true;
	}

	/* This is invalid until add() is called at least once. */
	public function work() {
		assert(count($this->task) > 0);

		if ($this->jmp <= 0) {
			for ($i = $this->pc + 1; ; ++$i) {
				if ($i >= count($this->task))
					$i = 0;
				if ($this->active[$i])
					break;
				if ($i == $this->pc)
					return false;
			}
			$this->pc = $i;
			$this->jmp = $this->rate[$this->pc];
		}

		$this->active[$this->pc] = $this->task[$this->pc]->work();
		--$this->jmp;

		if (!$this->active[$this->pc])
			$this->jmp = 0;

		return true;
	}
}

/*
 * result_callback($id, $success, $content)
 */
class crawl_queue {
	public $urlf;
	public $result_callback;
	public $attempt;
	public $cooldown;
	public $job;

	public function __construct($urlf, $result_callback, $ascending, $lb, $ub, $margin, $attempt, $cooldown) {
		assert($lb <= $ub && $margin >= 1 && $attempt >= 1 && $cooldown >= 0);

		$this->urlf = $urlf;
		$this->result_callback = $result_callback;
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

		$content = @file_get_contents(sprintf($this->urlf, $id));
		$success = $content !== false;

		$this->job[0]->status($id, $success);

		/*
		 * Only minor_queue has push, major_queue don't.
		 *
		 * When id isn't null, retry is guaranteed to at
		 * least be 1. Otherwise, when retry is 0, id is
		 * null and this code will not be reached.
		 */
		if (!$success && $retry < $this->attempt)
			$this->job[$retry]->push($id, time() + $this->cooldown);

		($this->result_callback)($id, $success, $content);

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
		return (($this->ascending) ? $this->head <= $this->ub : $this->head >= $this->lb) && count($this->fail) < $this->margin;
	}
}

class minor_queue {
	/* queue and time are parallels */
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
