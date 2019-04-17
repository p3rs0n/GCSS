<?php

namespace Greativ\GCSS;

class Mixin {

	private $name = '', $variables = array(), $code = '', $rules = array();

	public function __construct($code) {
		$this->code = $code;
		$this->parseCode();
	}

	private function parseCode() {

		$content = $this->code;
		$content = preg_replace('( *[;] *)', ';' . PHP_EOL, $content);
		$content = str_replace(array('{', '}'), PHP_EOL, $content);

		$lines = explode(PHP_EOL, $content);
		unset($content);

		foreach ($lines as $line) {
			$line = GCSS::findSet($line);
			if (strpos($line, '@mixin') !== false) {
				$line = str_replace('@mixin', '', $line);
				$line = explode('(', $line);
				$this->name = trim($line[0]);
				$line = rtrim($line[1], ') ');
				if ($line) {
					if (strpos($line, ',') !== false) {
						$this->variables = array_map('trim', explode(',', $line));
					} else {
						$this->variables = array(trim($line));
					}
				}
			} else {
				if ($line = trim($line)) {
					array_push($this->rules, $line);
				}
			}
		}
	}

	public function getResult($values) {
		if ($this->variables) {
			if (count($this->variables) != count($values)) {
				return '';
			}
			$result = '';
			foreach ($this->rules as $rule) {
				if (strpos($rule, '$') !== false) {
					$result .= str_replace($this->variables, $values, $rule);
				} else {
					$result .= $rule;
				}
			}
			return $result;
		} else {
			return implode($this->rules);
		}
	}

	public function getName() {
		return $this->name;
	}

}