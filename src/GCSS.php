<?php

namespace Greativ\GCSS;

class GCSS {

	private $cssFile;

	/**
	 * @todo $debug peaks olema seotud $_SESSION['SITE_CONFIG']['SET']['DEBUG_MODE']
	 */

	private static $debug = false;

	private $currDepth = 0, $depthRules = array();
	private $styleArr = array(), $mediaArr = array();
	private $inMedia = false, $mediaLine = '';
	private $inMixin = false, $mixinLine = '';
	private $inKeyframe = false, $keyframeLine = '';
	private $inFor = false, $forLine = '', $forConditions = '', $forStartDepth = 0;

	private $mediaTemp = '';

	private $mixins = array(), $values = array();

	private static $globalMixins = array(), $globalValues = array();

	private $mediaStartDepth = 0;
	private $nestedMediaString = '';

	private $finalContent = '';

	public static function setDebug(){
		self::$debug = true;
	}

	public function __construct($cssFile) {
		if (!file_exists($cssFile)) {
			header('HTTP/1.0 404 Not Found');
			exit;
		}
		$this->cssFile = $cssFile;
	}

	public static function instance($cssFile) {
		return new self($cssFile);
	}

	public static function parse($cssFile) {
		return self::instance($cssFile)->parseCss();
	}


	public function parseCss() {

		$content = file_get_contents($this->cssFile);

		$content = preg_replace('!\/\*[^*]*\*+([^\/][^*]*\*+)*\/!', '', $content);
		$content = preg_replace('!(^|[ \t])+\/\/.*[\r\n]!', '', $content);

		$content = preg_replace('/\s\s+/', ' ', $content);
		$content = preg_replace('/\s*\\#{(.*?)}\s*/', " $1 ", $content);
		$content = str_replace(array("\r\n", "\r", "\n", "\t", PHP_EOL), '', $content);
		$content = str_replace(': ', ':', $content);
		$content = preg_replace('( *[;] *)', ';', $content);
		$content = str_replace(array('{', '}'), array(PHP_EOL . '{' . PHP_EOL, PHP_EOL . '}' . PHP_EOL), $content);
		$content = str_replace('@mixin', PHP_EOL.'@mixin', $content);
		$content = str_replace('@for', PHP_EOL.'@for', $content);
		$content = str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $content);

		$lines = explode(PHP_EOL, $content);
		$content = '';

		for ($i = 0; $i < count($lines); $i++) {
			$line = trim($lines[$i]);
			if (!$line) continue;
			if (strripos($line, ';') != (strlen($line) - 1) && $lines[$i+1] == '}') {
				$line .= ';';
			}
			if (strpos($line, '@exec') !== false) {
				$line = $this->findAndReplaceVars($line);
				$line = self::findAndParseCalc($line);
				$line = self::parseFunction($line);

			}
			if (strpos($line, '@mixin') === false && !$this->isInMixin() && strpos($line, '@for') === false && !$this->isInFor()) {
				$line = $this->findAndReplaceVars($line);
				$line = self::findAndParseCalc($line);
			}
			if (strpos($line, '@for') !== false) {
				$line = $this->findFor($line);
			}
			if (strpos($line, '@set') !== false) {
				$line = self::findSet($line);
			}
			if (strpos($line, '@include') !== false) {
				$line = $this->findMixin($line);
			}

			if ($line == '{') {
				if ($this->isInKeyFrame()) {
					$this->keyframeLine .= $line;
				}
				if ($this->isInFor()) {
					$this->forLine .= $line;
				}
				$this->increaseDepth();
			} else if ($line == '}') {
				if ($this->isInKeyFrame()) {
					$this->keyframeLine .= $line;
				}
				if ($this->isInFor()) {
					$this->forLine .= $line;
				}
				if($newLines = $this->decreaseDepth()){
					array_splice($lines, $i + 1, 0, $newLines);
				}
			} else if ($this->isInFor()) {
				$this->forLine .= $line;
			} else if ($this->isInMixin()) {
				$this->mixinLine .= $line;
			} else if ($this->isInKeyFrame()) {
				$this->keyframeLine .= $line;
			} else {
				if (strpos($line, ';') !== false) {
					if (strripos($line, ';') != (strlen($line) - 1)) {
						$line = substr_replace($line, PHP_EOL, strripos($line, ';') + 1, 0);
						$lineParts = explode(PHP_EOL, $line);
						$line = $lineParts[0];
						array_splice($lines, $i + 1, 0, array($lineParts[1]));
					}
					$this->saveStyle($line);
				} else if (strpos($line, '@media') !== false) {
					$this->mediaTrigger($line);
				} else if (strpos($line, '@mixin') !== false) {
					$this->mixinTrigger($line);
				} else if (strpos($line, '@') !== false && strpos($line, 'keyframes') !== false) {
					$this->keyframeTrigger($line);
				} else {
					$currParts = explode(',', $line);
					$finalLine = '';
					foreach ($currParts as $line) {
						$line = trim($line);
						$hasAmpersand = false;
						if (strpos($line, '&') !== false) {
							$hasAmpersand = true;
						}

						$prev = $this->getPrevRule();
						if (strpos($prev, ',') !== false) {
							$tempLine = '';
							$lineParts = explode(',', $prev);
							foreach ($lineParts as $part) {
								if ($hasAmpersand) {
									if($this->isInMedia()){
										$tempLine .= str_replace('&', $part.'&', $line) . ',';
									}else{
										$tempLine .= str_replace('&', $part, $line) . ',';
									}
								} else {
									$tempLine .= $part . ' ' . $line . ',';
								}
							}
							$line = rtrim($tempLine, ',');
						} else {
							if ($hasAmpersand) {
								if($this->isInMedia()) {
									$line = str_replace('&', $prev.'&', $line);
								}else{
									$line = str_replace('&', $prev, $line);
								}
							} else {
								$line = $prev . ' ' . $line;
							}
						}
						$line = trim($line);
						$finalLine .= $line . ',';
					}
					$this->saveRule(rtrim($finalLine, ','));
				}
			}
		}
		$this->finalContent = $this->keyframeLine . $this->finalContent;

		if (self::$debug) {
			$this->finalContent = PHP_EOL . PHP_EOL . '/*************************************' . PHP_EOL . '*' . PHP_EOL . '*' . PHP_EOL . '* ' . $this->cssFile . PHP_EOL . '*' . PHP_EOL . '*' . PHP_EOL . '*************************************/' . PHP_EOL . $this->finalContent;
		}

		return $this->finalContent;
	}

	public static function loadMixins($filePath) {
		$content = file_get_contents($filePath);

		$content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
		$content = str_replace(array("\r\n", "\r", "\n", "\t", PHP_EOL), '', $content);
		$content = str_replace(': ', ':', $content);
		$content = preg_replace('( *[;] *)', ';', $content);
		$content = str_replace('@mixin', PHP_EOL . '@mixin', $content);
		$content = str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $content);

		$lines = explode(PHP_EOL, $content);
		foreach ($lines as $line) {
			if (!$line) continue;
			$mix = new Mixin($line);
			self::$globalMixins[$mix->getName()] = $mix;
		}
	}

	public static function loadVariables($filePath) {
		$content = file_get_contents($filePath);

		$content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
		$content = str_replace(array("\r\n", "\r", "\n", "\t", PHP_EOL), '', $content);
		$content = str_replace(': ', ':', $content);
		$content = preg_replace('( *[;] *)', ';' . PHP_EOL, $content);
		$content = str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $content);

		$lines = explode(PHP_EOL, $content);
		foreach ($lines as $line) {
			if (!$line) continue;
			self::parseGlobalVariableLine($line);
		}
	}

	private static function parseGlobalVariableLine($line) {
		preg_match_all('/\s*\\$([A-Za-z1-9_\-]+)(\s*:\s*(.*?);)?\s*/', $line, $vars);
		$found = $vars[0];
		$varNames = $vars[1];
		$varValues = $vars[3];
		$count = count($found);
		for ($i = 0; $i < $count; $i++) {
			$varName = trim($varNames[$i]);
			$varValue = trim($varValues[$i]);
			if ($varValue) {
				self::$globalValues[$varName] = self::parseFunction(self::findAndParseCalc(self::parseGlobalVariableLine($varValue)));
			} else if (isset(self::$globalValues[$varName])) {
				$line = preg_replace('/\\$' . $varName . '(\W|\z)/', self::$globalValues[$varName] . '\\1', $line);
			}
		}
		$line = str_replace($found, '', $line);
		return $line;
	}

	private function increaseDepth() {
		$this->currDepth++;
	}

	private function decreaseDepth() {
		$newLines = array();
		$this->depthRules[$this->currDepth] = '';
		$this->currDepth--;
		if ($this->isInMedia() && $this->currDepth === $this->mediaStartDepth) {
			$this->inMedia = false;
			$content = '';
			if (self::$debug) {
				foreach ($this->mediaArr as $media => $rules) {
					$content .= PHP_EOL . $media . '{' . PHP_EOL;
					foreach ($rules as $rule => $style) {
						$tempRule = $this->nestedMediaString . ' ' . $rule;
						if(strpos($rule, '&') !== false){
							$tempRule = str_replace(array(' &', '&'), '', $tempRule);
						}
						$content .= '    ' . $tempRule . '{' . PHP_EOL . '    ' . '    ' . $style . PHP_EOL . '    ' . '}' . PHP_EOL;
					}
					$content .= '}' . PHP_EOL;
				}
			} else {
				foreach ($this->mediaArr as $media => $rules) {
					$content .= $media . '{';
					foreach ($rules as $rule => $style) {
						$tempRule = $this->nestedMediaString . ' ' . $rule;
						if(strpos($rule, '&') !== false){
							$tempRule = str_replace(array(' &', '&'), '', $tempRule);
						}
						$content .= ' ' . $tempRule . '{' . $style . '}';
					}
					$content .= '}';
				}
			}
			//$this->finalContent .= $content;
			$this->mediaTemp .= $content;
			$this->mediaArr = array();
			$this->nestedMediaString = '';
		} else if ($this->isInKeyFrame() && $this->currDepth === 0) {
			$this->inKeyframe = false;
			if (self::$debug) {
				$this->keyframeLine .= PHP_EOL;
			}
		} else if ($this->isInMixin() && $this->currDepth === 0) {
			$mix = new Mixin($this->mixinLine . '}');
			$this->mixins[$mix->getName()] = $mix;
			$this->inMixin = false;
			$this->mixinLine = '';
		} else if ($this->isInFor() && $this->currDepth === $this->forStartDepth) {
			$newLines = $this->parseFor();
			$this->inFor = false;
		} else {
			$content = '';
			if (self::$debug) {
				foreach ($this->styleArr as $rule => $style) {
					$content .= $rule . '{' . PHP_EOL . '    ' . $style . PHP_EOL . '}' . PHP_EOL;
				}
			} else {
				foreach ($this->styleArr as $rule => $style) {
					$content .= $rule . '{' . $style . '}';
				}
			}
			$this->finalContent .= $content;
			$this->styleArr = array();
		}
		if($this->currDepth === 0 && $this->mediaTemp){
			$this->finalContent .= $this->mediaTemp;
			$this->mediaTemp = '';
		}
		return $newLines;
	}

	private function saveRule($rule) {
		$this->depthRules[$this->currDepth] = $rule;
	}

	private function getRule() {
		return $this->depthRules[$this->currDepth];
	}

	private function getPrevRule() {
		if ($this->isInMedia()) {
			if (($this->currDepth - $this->mediaStartDepth) > 1) {
				return $this->depthRules[$this->currDepth - 1];
			}
			return '';
		}
		return $this->depthRules[$this->currDepth - 1];
	}

	private function saveStyle($style) {
		if ($this->isInMedia()) {
			if (!$this->mediaArr[$this->mediaLine]) {
				$this->mediaArr[$this->mediaLine] = array();
			}
			if (!$this->mediaArr[$this->mediaLine][$this->getPrevRule()]) {
				$this->mediaArr[$this->mediaLine][$this->getPrevRule()] = '';
			}
			$this->mediaArr[$this->mediaLine][$this->getPrevRule()] = $style;
		} else {
			if (!$this->styleArr[$this->getPrevRule()]) {
				$this->styleArr[$this->getPrevRule()] = '';
			}
			$this->styleArr[$this->getPrevRule()] .= $style;
		}
	}

	private function mediaTrigger($mediaLine) {
		$this->mediaStartDepth = $this->currDepth;
		if($this->mediaStartDepth > 0){
			$this->nestedMediaString = $this->getPrevRule();
		}
		$this->mediaLine = $mediaLine;
		$this->inMedia = true;
	}

	private function isInMedia() {
		return $this->inMedia;
	}

	private function mixinTrigger($mixinLine) {
		$this->inMixin = true;
		$this->mixinLine = $mixinLine . '{';
	}

	private function isInMixin() {
		return $this->inMixin;
	}

	private function keyframeTrigger($keyframeLine) {
		$this->inKeyframe = true;
		$this->keyframeLine .= $keyframeLine;
	}

	private function isInKeyFrame() {
		return $this->inKeyframe;
	}
	private function isInFor() {
		return $this->inFor;
	}

	public function findAndReplaceVars($line) {
		preg_match_all('/\s*\\$([A-Za-z1-9_\-]+)(\s*:\s*(.*?);)?\s*/', $line, $vars);
		$found = $vars[0];
		$varNames = $vars[1];
		$varValues = $vars[3];
		$count = count($found);
		for ($i = 0; $i < $count; $i++) {
			$varName = trim($varNames[$i]);
			$varValue = trim($varValues[$i]);
			if ($varValue) {
				$this->values[$varName] = self::parseFunction(self::findAndParseCalc($this->findAndReplaceVars($varValue)));
			} else if (isset($this->values[$varName])) {
				$line = preg_replace('/\\$' . $varName . '(\W|\z)/', $this->values[$varName] . '\\1', $line);
			} else if (isset(self::$globalValues[$varName])) {
				$line = preg_replace('/\\$' . $varName . '(\W|\z)/', self::$globalValues[$varName] . '\\1', $line);
			}
		}
		$line = str_replace($found, '', $line);
		return $line;
	}

	private function parseFor(){
		$re1='(\\d+)';	# Integer Number 1
		$re2='( )';	# White Space 1
		$re3='(to)';	# Word 1
		$re4='( )';	# White Space 2
		$re5='(\\d+)';	# Integer Number 2
		$re6='( )';	# White Space 3
		$re7='(by)';	# Word 2
		$re8='( )';	# White Space 4
		$re9='(\\d+)';	# Integer Number 3

		preg_match_all ("/".$re1.$re2.$re3.$re4.$re5.$re6.$re7.$re8.$re9."/is", $this->forConditions, $matches);
		$from=$matches[1][0];
		$to=$matches[5][0];
		$step=$matches[9][0];
		$lines = array();
		for($i = $from; $i<=$to; $i+=$step){
			$content = str_replace(array('{', '}'), array(PHP_EOL . '{' . PHP_EOL, PHP_EOL . '}' . PHP_EOL), substr(str_replace('$i', $i, $this->forLine), 1, -1));
			$content = explode(PHP_EOL, $content);
			$lines = array_merge($lines, $content);
		}
		return $lines;
	}

	private static function parseCalc($calc) {
		$re1 = '[\(]';    # Non-greedy match on filler
		$re2 = '(\d+(\.\d+)?)';    # Float or Integer Number 1
		$re3 = '((?:[a-z%]?[a-z%]?[a-z%]?+))';    # Word 1
		$re4 = '[ ]';    # Non-greedy match on filler
		$re5 = '([-+*\/])';    # Any Single Character 1
		$re6 = '[ ]';    # Non-greedy match on filler
		$re7 = '(\d+(\.\d+)?)';    # Float or Integer Number 2
		$re8 = '((?:[a-z%]?[a-z%]?[a-z%]?+))';    # Word 2
		$re9 = '[\)]';    # Non-greedy match on filler
		$re10 = '((?:[a-z%]?[a-z%]?[a-z%]?+))';    # Word 3

		preg_match_all("/" . $re1 . $re2 . $re3 . $re4 . $re5 . $re6 . $re7 . $re8 . $re9 . $re10 . "/is", $calc, $vars);

		$value1 = $vars[1][0];
		$value2 = $vars[5][0];
		$unit1 = $vars[3][0];
		$unit2 = $vars[7][0];

		$operator = $vars[4][0];

		$extraUnit = $vars[8][0];

		$unitError = false;

		if ($unit1 && $unit2 && !$extraUnit) {
			if ($unit1 != $unit2 && $unit2 != '%') {
				$unitError = true;
			}
		}

		if (is_numeric($value1) && is_numeric($value2) && $operator && !$unitError) {
			$finalValue = 0;

			$finalUnit = '';
			if ($extraUnit) {
				$finalUnit = $extraUnit;
			} else if ($unit1) {
				$finalUnit = $unit1;
			} else if ($unit2 && $unit2 != '%') {
				$finalUnit = $unit2;
			}

			if ($unit1 != '%' && $unit2 == '%') {
				$value2 = $value1 * ($value2 / 100);
			}

			switch ($operator) {
				case '-':
					$finalValue = $value1 - $value2;
					break;
				case '+':
					$finalValue = $value1 + $value2;
					break;
				case '*':
					$finalValue = $value1 * $value2;
					break;
				case '/':
					if ($value2) {
						$finalValue = $value1 / $value2;
					}
					break;
			}
			return $finalValue . $finalUnit;
		}

		return $calc;
	}

	public static function findAndParseCalc($line){
		if(strpos($line, 'calc') !== false){
			return $line;
		}
		if(strpos($line, '(') === false){
			return $line;
		}
		if(strpos($line, ' - ') === false && strpos($line, ' + ') === false && strpos($line, ' * ') === false && strpos($line, ' / ') === false){
			return $line;
		}

		//TODO optimeerida regex Peab leidma ainult terve tehte koos sulgudega, mitte kõiki elemente.
		$re1 = '[\(]';    # Non-greedy match on filler
		$re2 = '(\d+(\.\d+)?)';    # Float or Integer Number 1
		$re3 = '((?:[a-z%]?[a-z%]?[a-z%]?+))';    # Word 1
		$re4 = '[ ]';    # Non-greedy match on filler
		$re5 = '([-+*\/])';    # Any Single Character 1
		$re6 = '[ ]';    # Non-greedy match on filler
		$re7 = '(\d+(\.\d+)?)';    # Float or Integer Number 2
		$re8 = '((?:[a-z%]?[a-z%]?[a-z%]?+))';    # Word 2
		$re9 = '[\)]';    # Non-greedy match on filler
		$re10 = '((?:[a-z%]?[a-z%]?[a-z%]?+))';    # Word 3

		//$regex = '([\(][][\)])';

		//preg_match_all("/" . $regex . "/is", $line, $vars);
		preg_match_all("/" . $re1 . $re2 . $re3 . $re4 . $re5 . $re6 . $re7 . $re8 . $re9 . $re10 . "/is", $line, $vars);

		$search = array();
		$replace = array();

		foreach($vars[0] as $calc){
			array_push($search, $calc);
			array_push($replace, self::parseCalc($calc));
		}
		$line = str_replace($search, $replace, $line);
		return $line;
	}

	public static function parseFunction($line){
		if(strpos($line, '@exec') !== false) {
			$re2 = '(@exec)';    # Word 1
			$re3 = '[ ]';    # White Space 1
			$re4 = '(.*?)';    # Non-greedy match on filler
			$re5 = '[\\(]';    # Any Single Character 2
			$re6 = '(.*?)';    # Non-greedy match on filler
			$re7 = '[\\)]';    # Any Single Character 3

			preg_match_all("/" . $re2 . $re3 . $re4 . $re5 . $re6 . $re7 . "/is", $line, $vars);
			$found = $vars[0];
			$funcNames = $vars[2];
			$funcParams = $vars[3];
			if (count($found)) {
				for ($i = 0; $i < count($found); $i++) {
					$params = explode(',', trim($funcParams[$i]));
					foreach ((array)$params as $key => $value) {
						$params[$key] = trim($value);
					}
					$result = CommandExecutor::exec(trim($funcNames[$i]), $params);
					$line = preg_replace('/' . preg_quote($found[$i], '/') . '/', $result, $line, 1);
				}
			}
		}
		return $line;
	}

	public static function findSet($line) {
		preg_match_all('/\s*\\@set(\s*[A-Za-z1-9_\-]+)(\s*:\s*(.*?);)?\s*/', $line, $vars);
		$found = $vars[0];
		$varNames = $vars[1];
		$varValues = $vars[3];
		$count = count($found);

		if ($count) {
			$replacedArr = array();
			for ($i = 0; $i < $count; $i++) {
				$varName = trim($varNames[$i]);
				$varValue = trim($varValues[$i]);
				$replaced = '-webkit-' . $varName . ':' . $varValue . ';';
				$replaced .= '-moz-' . $varName . ':' . $varValue . ';';
				$replaced .= '-ms-' . $varName . ':' . $varValue . ';';
				$replaced .= '-o-' . $varName . ':' . $varValue . ';';
				$replaced .= $varName . ':' . $varValue . ';';
				array_push($replacedArr, $replaced);
			}
			$line = str_replace($found, $replacedArr, $line);
		}
		return $line;
	}

	public function findFor($line){
		$this->inFor = true;
		$this->forConditions = trim(str_replace('@for', '', $line));
		$this->forLine = '';
		$this->forStartDepth = $this->currDepth;
	}

	public function findMixin($line) {
		preg_match_all('/\s*\\@include(\s*[A-Za-z1-9_\-]+)(\s*\(\s*(.*?)\);)?\s*/', $line, $vars);
		$found = $vars[0];
		$mixinNames = $vars[1];
		$mixinParams = $vars[3];

		$newLine = array();

		if (count($found)) {
			for ($i = 0; $i < count($found); $i++) {
				if ($mix = self::$globalMixins[trim($mixinNames[$i])]) {
					$params = array();
					if ($mixinParams[$i]) {
						$params = array_map('trim', explode(',', $mixinParams[$i]));
					}
					array_push($newLine, $mix->getResult($params));
				} else if ($this->mixins && $mix = $this->mixins[trim($mixinNames[$i])]) {
					$params = array();
					if ($mixinParams[$i]) {
						$params = array_map('trim', explode(',', $mixinParams[$i]));
					}
					array_push($newLine, $mix->getResult($params));
				} else {
					array_push($newLine, '');
				}
			}
			if ($newLine) {
				$line = str_replace($found, $newLine, $line);
			}

		}
		return self::parseFunction(self::findAndParseCalc($this->findAndReplaceVars($line)));
	}

	public static function getGlobalVariables(){
		return self::$globalValues;
	}
}

?>