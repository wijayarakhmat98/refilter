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

	$post_content = function ($url, $type, $trial, $status, $content) {
		echo '<details>';
		switch ($status) {
		case FAIL:
			$status = 'FAIL';
			printf('<a href="%s"><p>%s</p></a>', $url, $url);
			break;
		case SUCCESS:
			$status = 'SUCCESS';
			printf('<div style="%s"><p>%s</p></div>', 'white-space: pre-wrap;', htmlspecialchars($content));
			break;
		case EXISTS:
			$status = 'EXISTS';
			printf('<p>Please refer to database.</p>', $url, $url);
			break;
		}
		printf('<summary>%s %s [ %d ] [%s]</summary>', $url, $type, $trial, $status);
		echo '</details>';
		ob_flush(); flush();
	};

	$fail = [
		'sirup_penyedia' => new trial_count(3)
	];

	$res_call = function ($type, $id, $status, $content) use ($db, $post_content, $fail) {
		if ($status == SUCCESS)
			$db['sirup_penyedia']['insert']($id, $content);
		$post_content(sprintf('https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d', $id), $type, $fail['sirup_penyedia']($id, $status != FAIL), $status, $content);
	};

	$task = new interleave();

	list($holes, $lb, $ub) = linear_bound(44023335, 44023345, 2, ASCENDING, $db['sirup_penyedia']['select']);
	echo '<p>'; var_dump($holes); echo '</p>';
	printf('<p>lb: %d, ub: %d</p>', $lb, $ub);

	$task->add(2, linear_crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		$db['sirup_penyedia']['select'],
		function ($x, $y, $z) use ($res_call) { $res_call('[ L ] [+++]', $x, $y, $z); },
		ASCENDING, $lb, $ub, 2, 3, 3
	));

	$task->add(1, set_crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		$db['sirup_penyedia']['select'],
		function ($x, $y, $z) use ($res_call) { $res_call('[ s ] [+++]', $x, $y, $z); },
		$holes, 3, 3
	));

	list($holes, $lb, $ub) = linear_bound(16998889, 16999889, 2, DESCENDING, $db['sirup_penyedia']['select']);
	echo '<p>'; var_dump($holes); echo '</p>';
	printf('<p>lb: %d, ub: %d</p>', $lb, $ub);

	$task->add(2, linear_crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		$db['sirup_penyedia']['select'],
		function ($x, $y, $z) use ($res_call) { $res_call('[ L ] [---]', $x, $y, $z); },
		DESCENDING, $lb, $ub, 2, 3, 3
	));

	$task->add(1, set_crawl(
		'https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/%d',
		$db['sirup_penyedia']['select'],
		function ($x, $y, $z) use ($res_call) { $res_call('[ s ] [---]', $x, $y, $z); },
		$holes, 3, 3
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

function linear_bound($lb, $ub, $margin, $ascending, $exists_callback) {
	assert($margin >= 0);
	$holes = [];
	$fail = [];
	if ($ascending) {
		for ($id = $lb; $id <= $ub; ++$id)
			if ($exists_callback($id)) {
				array_push($holes, ...$fail);
				$fail = [];
				$lb = $id + 1;
			}
			else {
				$fail[] = $id;
				if (count($fail) >= $margin)
					break;
			}
		if ($id > $ub) {
			array_push($holes, ...$fail);
			$lb = $id;
		}
	}
	else {
		for ($id = $ub; $id >= $lb; --$id)
			if ($exists_callback($id)) {
				array_push($holes, ...$fail);
				$fail = [];
				$ub = $id - 1;
			}
			else {
				$fail[] = $id;
				if (count($fail) >= $margin)
					break;
			}
		if ($id < $lb) {
			array_push($holes, ...$fail);
			$ub = $id;
		}
	}
	return [$holes, $lb, $ub];
}

function linear_crawl($urlf, $exists_callback, $result_callback, $ascending, $lb, $ub, $margin, $attempt, $cooldown) {
	assert($margin >= 1 && $attempt >= 1 && $cooldown >= 0);

	$job = [new generator_queue($ascending, $lb, $ub, $margin)];
	for ($i = 1; $i < $attempt; ++$i)
		$job[] = new attempt_queue();

	$result_callback = function ($id, $status, $content) use ($job, $result_callback) {
		$job[0]->status($id, $status != FAIL);
		$result_callback($id, $status, $content);
	};

	return new queue_manager($urlf, $exists_callback, $result_callback, $job, $cooldown);
}

function set_crawl($urlf, $exists_callback, $result_callback, $set, $attempt, $cooldown) {
	assert($attempt >= 1 && $cooldown >= 0);

	$job = [new simple_queue($set)];
	for ($i = 1; $i < $attempt; ++$i)
		$job[] = new attempt_queue();

	return new queue_manager($urlf, $exists_callback, $result_callback, $job, $cooldown);
}

define('FAIL'   , 0);
define('SUCCESS', 1);
define('EXISTS' , 2);

/*
 * exists_callback($id)
 * result_callback($id, $status, $content)
 *
 * Queue must have pop() and is_empty().
 * Non-first queue must have push($id, $timestamp).
 */
class queue_manager {
	public function __construct(
		public $urlf,
		public $exists_callback,
		public $result_callback,
		public $job,
		public $cooldown
	) {}

	public function work() {
		$active = false;
		$id = null;

		for ($i = count($this->job) - 1; $i >= 0; --$i) {
			if (!$this->job[$i]->is_empty())
				$active = true;
			$id = $this->job[$i]->pop();
			if ($id)
				break;
		}
		$retry = $i + 1;

		if (!$id)
			return $active;

		if (($this->exists_callback)($id)) {
			($this->result_callback)($id, EXISTS, null);
		}
		else {
			$content = @file_get_contents(sprintf($this->urlf, $id));
			if ($content === false) {
				if ($retry < count($this->job))
					$this->job[$retry]->push($id, time() + $this->cooldown);
				($this->result_callback)($id, FAIL, null);
			}
			else {
				($this->result_callback)($id, SUCCESS, $content);
			}
		}
		return true;
	}
}

/*
 * status($id, $success) must be called
 * to both move the head and manage the
 * internal margin state.
 */
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

	public function is_empty() {
		return (($this->ascending) ? $this->head > $this->ub : $this->head < $this->lb) || count($this->fail) >= $this->margin;
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
	 * Queue pop() may return null but it isn't empty
	 * while waiting on the cool-down.
	 */
	public function is_empty() {
		return count($this->queue) == 0;
	}
}

class simple_queue {
	public function __construct(public $queue) {}
	public function push($item) {
		$this->queue[] = $item;
	}
	public function pop() {
		return array_shift($this->queue);
	}
	public function is_empty() {
		return count($this->queue) == 0;
	}
}

main();

?>
