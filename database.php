<?php

class db_exists {
	public $stmt;

	public function __construct(public $dbconn, $table, $params, $nullable = []) {
		assert(count($params) > 0);
		$this->stmt = bin2hex(random_bytes(16));
		$query = 'select ';
		for ($i = 0; $i < count($params); ++$i)
			$query = sprintf('%s%s%s', $query, $params[$i], ($i < count($params) - 1) ? ', ' : '');
		$query = sprintf('%s from %s where ', $query, $table);
		for ($i = 0; $i < count($params); ++$i) {
			if (in_array($params[$i], $nullable))
				$query = sprintf('%s(%s = $%d or (%s is NULL and $%d is NULL))', $query, $params[$i], $i + 1, $params[$i], $i + 1);
			else
				$query = sprintf('%s%s = $%d', $query, $params[$i], $i + 1);
			if ($i < count($params) - 1)
				$query = sprintf('%s and ', $query);
		}
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

?>
