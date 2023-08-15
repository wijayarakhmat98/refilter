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
	public $array_pos = [];
	public $stmt = [];

	public function __construct(public $dbconn, public $table, public $params, public $types = []) {
		foreach ($params as $pos => $param)
			if (array_key_exists($param, $types) && is_array($types[$param]))
				$this->array_pos[] = $pos;
	}

	public function __invoke(...$args) {
		$signature = $this->signature($args);
		if (!array_key_exists($signature, $this->stmt)) {
			$query = $this->query($signature);
			$this->stmt[$signature] = bin2hex(random_bytes(16));
			pg_prepare($this->dbconn, $this->stmt[$signature], $query);
		}
		$flat = $this->flatten($args);
		return pg_execute($this->dbconn, $this->stmt[$signature], $flat);
	}

	public function signature($args) {
		$signature = [];
		foreach ($this->array_pos as $pos)
			$signature[] = self::array_signature($args[$pos], $this->types[$this->params[$pos]]);
		$signature = implode(';', $signature);
		return $signature;
	}

	public function query($signature) {
		$param_pad = 0;
		$query = 'insert into '.$this->table.'(';
		foreach ($this->params as $pos => $param) {
			$query .= $param.(($pos < count($this->params) - 1) ? ', ' : '');
			if (strlen($param) > $param_pad)
				$param_pad = strlen($param);
		}
		$query = $query.")\nvalues(\n";
		$arg = new class() { public $pos = 1; };
		$sig = [null, ...explode(';', $signature)];
		foreach ($this->params as $pos => $param) {
			$query .= "\t/* ".sprintf('%-'.$param_pad.'s', $param).' */ ';
			if (in_array($pos, $this->array_pos))
				$query .= preg_replace_callback('/%/', fn() => '$'.$arg->pos++, next($sig));
			else
				$query .= '$'.$arg->pos++;
			$query .= ($pos < count($this->params) - 1) ? ",\n" : '';
		}
		$query .= "\n)";
		return $query;
	}

	public function flatten($args) {
		$flat = [];
		foreach ($this->params as $pos => $param) {
			$type = null;
			if (array_key_exists($param, $this->types))
				for ($type = $this->types[$param]; is_array($type); $type = $type[0])
					;
			$vals = [];
			if (array_key_exists($param, $this->types) && is_array($this->types[$param]) && is_array($args[$pos]))
					array_walk_recursive($args[$pos], function ($val) use (&$vals) { $vals[] = $val; });
			else
				$vals[] = $args[$pos];
			if ($type !== null)
				foreach ($vals as $i => $val)
					if ($val !== null)
						$vals[$i] = self::process($val, $type);
			$flat = array_merge($flat, $vals);
		}
		return $flat;
	}

	public static function process($val, $type) {
		switch ($type) {
			case 'boolean':
				$val = ($val) ? 'TRUE' : 'FALSE';
				break;
			case 'date':
				$val = $val->format('Y-m-d');
				break;
			case 'timestamp':
				$val = $val->format('Y-m-d H:i:s.u');
				break;
		}
		return $val;
	}

	public static function array_signature($array, $type) {
		if (!is_array($array))
			return '%';
		$signature = '[';
		foreach ($array as $i => $val) {
			$signature .= self::array_signature($val, null);
			if ($i < count($array) - 1)
				$signature .= ', ';
		}
		$signature .= ']';
		if ($type !== null) {
			for (; is_array($type); $type = $type[0])
				;
			$signature = 'ARRAY'.$signature.'::'.$type.'[]';
		}
		return $signature;
	}
}

?>
