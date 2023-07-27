<?php

function post_content ($url, $type, $trial, $status, $content) {
	echo '<details>';
	switch ($status) {
	case FAIL:
		$status = 'FAIL';
		printf('<a href="%s"><p>%s</p></a>', $url, $url);
		break;
	case SUCCESS:
		$status = 'SUCCESS';
		printf('<div><p>%s</p></div>', htmlspecialchars($content));
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

function dump_insert($insert, $urlf, $fail) {
	return function ($type) use ($insert, $urlf, $fail) {
		return function ($id, $status, $content) use ($insert, $urlf, $fail, $type) {
			$insert($id, $status, $content);
			post_content(sprintf($urlf, $id), $type, ($fail)($id, $status != FAIL), $status, $content);
		};
	};
}

function dump_linear_bound($urlf, $conf, $holes, $lb, $ub) {
	printf(
		'<details>
			<summary>%s<p>start: %d, lb: %d, ub: %d</p></summary>
			<div style="white-space: pre-wrap;"><p>',
		$urlf, $conf['start'], $lb, $ub
	);
	var_dump($holes);
	printf(
			'</p></div>
		</details>'
	);
	ob_flush(); flush();
}

define('ASCENDING' , true );
define('DESCENDING', false);

function main() {
	echo '<div style="white-space: pre-wrap; font-family: Consolas;">';

	$ini_array = parse_ini_file("crawl.ini", true, INI_SCANNER_TYPED);

	$max_execution_time = $ini_array['max_execution_time'];
	set_time_limit($max_execution_time);

	$dbname = $ini_array['dbname'];
	$user = $ini_array['user'];
	$password = $ini_array['password'];
	$table = $ini_array['table'];

	foreach (array_keys($ini_array) as $key)
		if (!is_array($ini_array[$key]))
			unset($ini_array[$key]);

	$dbconn = pg_connect(sprintf('dbname=%s user=%s password=%s', $dbname, $user, $password));
	$db = [];
	$db[$table]['exists'] = new db_exists($dbconn, $table, ['website', 'type', 'id']);
	$db[$table]['insert'] = new db_insert($dbconn, $table, ['website', 'type', 'id', 'content']);

	$task = new interleave();
	$fail = [];

	foreach (array_keys($ini_array) as $urlf) {
		$conf = $ini_array[$urlf];
		$ma = $conf['margin'];
		$at = $conf['attempt'];
		$co = $conf['cooldown'];
		$ra = $conf['weight_asc'];
		$rd = $conf['weight_dsc'];
		$rr = $conf['weight_ret'];

		$exists = function ($id) use ($db, $table, $conf) {
			return $db[$table]['exists']($conf['website'], $conf['type'], $id);
		};

		$insert = function ($id, $status, $content) use ($db, $table, $conf) {
			if ($status == SUCCESS)
				$db[$table]['insert']($conf['website'], $conf['type'], $id, $content);
		};

		$dmp_i = dump_insert($insert, $urlf, new trial_count($at));

		list($ho, $lb, $ub) = linear_bound($conf['start'], $conf['ub'], $ma, ASCENDING, $exists);
		dump_linear_bound($urlf, $conf, $ho, $lb, $ub);
		$task->add($ra, linear_crawl($urlf, $exists, $dmp_i('[G] [+++]'), ASCENDING, $lb, $ub, $ma, $at, $co));
		$task->add($rr, set_crawl($urlf, $exists, $dmp_i('[s] [+++]'), $ho, $at, $co));

		list($ho, $lb, $ub) = linear_bound($conf['lb'], $conf['start'] - 1, $ma, DESCENDING, $exists);
		dump_linear_bound($urlf, $conf, $ho, $lb, $ub);
		$task->add($rd, linear_crawl($urlf, $exists, $dmp_i('[G] [---]'), DESCENDING, $lb, $ub, $ma, $at, $co));
		$task->add($rr, set_crawl($urlf, $exists, $dmp_i('[s] [---]'), $ho, $at, $co));
	}

	while ($task->work())
		;
	echo '<p>Done.</p>';

	echo '</div>';
}

class db_exists {
	public $stmt;

	public function __construct(public $dbconn, $table, $params) {
		assert(count($params) > 0);
		$this->stmt = bin2hex(random_bytes(16));
		$query = 'select ';
		for ($i = 0; $i < count($params); ++$i)
			$query = sprintf('%s%s%s', $query, $params[$i], ($i < count($params) - 1) ? ', ' : '');
		$query = sprintf('%s from %s where ', $query, $table);
		for ($i = 0; $i < count($params); ++$i)
			$query = sprintf('%s%s = $%d%s', $query, $params[$i], $i + 1, ($i < count($params) - 1) ? ' and ' : '');
		pg_prepare($dbconn, $this->stmt, $query);
	}

	public function __invoke(...$args) {
		$res = pg_execute($this->dbconn, $this->stmt, $args);
		return ($res === false) ? null : count(pg_fetch_all($res)) > 0;
	}
}

class db_insert {
	public $stmt;

	public function __construct(public $dbconn, $table, $params) {
		assert(count($params) > 0);
		$this->stmt = bin2hex(random_bytes(16));
		$query = sprintf('insert into %s(', $table);
		for ($i = 0; $i < count($params); ++$i)
			$query = sprintf('%s%s%s', $query, $params[$i], ($i < count($params) - 1) ? ', ' : '');
		$query = sprintf('%s) values(', $query);
		for ($i = 0; $i < count($params); ++$i)
			$query = sprintf('%s$%d%s', $query, $i + 1, ($i < count($params) - 1) ? ', ' : '');
		$query = sprintf('%s)', $query);
		pg_prepare($dbconn, $this->stmt, $query);
	}

	public function __invoke(...$args) {
		return pg_execute($this->dbconn, $this->stmt, $args);
	}
}

class interleave {
	public $group = [];
	public $task = [];
	public $pc = 0;

	public function add($rate, $task) {
		$group = bin2hex(random_bytes(16));
		for ($i = 0; $i < $rate; ++$i) {
			$this->group[] = $group;
			$this->task[] = $task;
		}
	}

	public function work() {
		if (count($this->task) == 0)
			return false;

		if ($this->task[$this->pc]->work())
			++$this->pc;
		else {
			$group = [];
			$task = [];
			$pc = -1;
			for ($i = 0; $i < count($this->task); ++$i)
				if ($this->group[$i] == $this->group[$this->pc]) {
					if ($pc < 0)
						$pc = $i;
				}
				else {
					$group[] = $this->group[$i];
					$task[] = $this->task[$i];
				}
			$this->group = $group;
			$this->task = $task;
			$this->pc = $pc;
		}

		if ($this->pc >= count($this->task))
			$this->pc = 0;

		return true;
	}
}

function linear_bound($lb, $ub, $margin, $ascending, $exists_callback) {
	assert($margin >= 1);
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
