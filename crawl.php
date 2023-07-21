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

	$post_content = function ($url, $ascending, $trial, $success, $content) {
		echo '<details>';
		switch ($success) {
		case 0:
			$success = 'FAIL';
			printf('<a href="%s">%s</a>', $url, $url);
			break;
		case 1:
			$success = 'SUCCESS';
			printf('<div style="%s">%s</div>', 'white-space: pre-wrap;', htmlspecialchars($content));
			break;
		case 2:
			$success = 'EXISTS';
			printf('<a href="%s">%s</a>', $url, $url);
			break;
		}
		printf('<summary>%s [%s] [ %d ] [%s]</summary>', $url, ($ascending) ? '+++' : '---', $trial, $success);
		echo '</details>';
		ob_flush(); flush();
	};

	$fail = [
		'sirup_penyedia' => new trial_count(3)
	];

	$task = new interleave();

	$task->add(2, create_linear_crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		function ($id) use ($db, $post_content, $fail) {
			$exists = $db['sirup_penyedia']['select']($id);
			if ($exists)
				$post_content(sprintf('https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d', $id), ASCENDING, $fail['sirup_penyedia']($id, true), 2, null);
			return $exists;
		},
		function ($id, $success, $content) use ($db, $post_content, $fail) {
			if ($success)
				$db['sirup_penyedia']['insert']($id, $content);
			$post_content(sprintf('https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d', $id), ASCENDING, $fail['sirup_penyedia']($id, $success), $success, $content);
		},
		ASCENDING, 44023335, 44023345, 2, 3, 3
	));

	$task->add(2, create_linear_crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		function ($id) use ($db, $post_content, $fail) {
			$exists = $db['sirup_penyedia']['select']($id);
			if ($exists)
				$post_content(sprintf('https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d', $id), DESCENDING, $fail['sirup_penyedia']($id, true), 2, null);
			return $exists;
		},
		function ($id, $success, $content) use ($db, $post_content, $fail) {
			if ($success)
				$db['sirup_penyedia']['insert']($id, $content);
			$post_content(sprintf('https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d', $id), DESCENDING, $fail['sirup_penyedia']($id, $success), $success, $content);
		},
		DESCENDING, 16998889, 16999889, 2, 3, 3
	));

	echo '<div style="font-family: Consolas;">';
	while ($task->work())
		;
	echo '<p>Done.</p>';
	echo '</div>';
}

class trial_count {
	public $fail = [];
	public function __construct(public $attempt) {}
	public function __invoke($id, $success) {
		if (!array_key_exists($id, $this->fail))
			$this->fail[$id] = 0;
		$trial = ++$this->fail[$id];
		if ($success || $this->fail[$id] >= $this->attempt)
			unset($this->fail[$id]);
		return $trial;
	}
}

function create_linear_crawl($urlf, $exists_callback, $result_callback, $ascending, $lb, $ub, $margin, $attempt, $cooldown) {
	assert($lb <= $ub && $margin >= 1 && $attempt >= 1 && $cooldown >= 0);

	$job = [new generator_queue($ascending, $lb, $ub, $margin)];
	for ($i = 1; $i < $attempt; ++$i)
		$job[] = new attempt_queue();

	return new crawl_queue($urlf, $exists_callback, $result_callback, $job, true, $cooldown);
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
 * exists_callback($id)
 * result_callback($id, $success, $content)
 *
 * Never push to the first queue.
 * push($id, $timestamp)
 */
class crawl_queue {
	public function __construct(
		public $urlf,
		public $exists_callback,
		public $result_callback,
		public $job,
		public $require_status,
		public $cooldown
	) {}

	public function work() {
		$active = false;
		$id = null;

		for ($i = count($this->job) - 1; $i >= 0; --$i) {
			if ($this->job[$i]->active())
				$active = true;
			$id = $this->job[$i]->pop();
			if ($id)
				break;
		}
		$retry = $i + 1;

		if (!$id)
			return $active;

		$exists = ($this->exists_callback)($id);
		$success = true;
		if (!$exists) {
			$content = @file_get_contents(sprintf($this->urlf, $id));
			$success = $content !== false;
			if (!$success && $retry < count($this->job))
				$this->job[$retry]->push($id, time() + $this->cooldown);
		}
		if ($this->require_status)
			$this->job[0]->status($id, $success);
		if (!$exists)
			($this->result_callback)($id, $success, $content);
		return true;
	}
}

class generator_queue {
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

class attempt_queue {
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
