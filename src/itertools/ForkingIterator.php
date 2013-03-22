<?php

namespace itertools;

use Exception;
use IteratorIterator;
use ArrayIterator;


class ForkingIterator extends IteratorIterator {

	const IS_CHILD = 0;
	const ENABLED = true;
	const DISABLED = false;

	protected $isChild = false;
	protected $childCount = 0;
	protected $forkingEnabled;
	protected $maxChildren;

	public function __construct($inner, $options = array()) {
		parent::__construct(is_array($inner) ? new ArrayIterator($inner) : $inner);
		$this->forkingEnabled = array_key_exists('forkingEnabled', $options) ? $options['forkingEnabled'] : self::ENABLED;
		$this->maxChildren = array_key_exists('maxChildren', $options) ? $options['maxChildren'] : PHP_INT_MAX;
	}

	public static function supportsFork() {
		return function_exists('pcntl_fork');
	}

	public function isForkingEnabled() {
		return $this->forkingEnabled && self::supportsFork();
	}

	protected function fork() {
		$pid = pcntl_fork();
		if($pid == -1) {
			throw new Exception('Could not fork');
		}
		if($pid == self::IS_CHILD) {
			$this->isChild = true;
		}
		$this->childCount += 1;
		return $pid;
	}

	protected function wait() {
		pcntl_wait($status);
		$this->childCount -= 1;
	}

	public function rewind() {
		if(!$this->isForkingEnabled()) {
			return parent::rewind();
		}

		parent::rewind();
		do {
			if($this->fork() == self::IS_CHILD) {
				return;
			}
			while($this->childCount >= $this->maxChildren) {
				$this->wait();
			}
			parent::next();
		} while(parent::valid());

		while($this->childCount > 0) {
			$this->wait();
		}
	}

	public function valid() {
		if(!$this->isForkingEnabled()) {
			return parent::valid();
		}
		if(!$this->isChild) {
			return false;
		}
		return parent::valid();
	}

	public function next() {
		if(!$this->isForkingEnabled()) {
			return parent::next();
		}
		exit;
	}
}
