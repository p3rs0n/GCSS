<?php
/**
 * Created by PhpStorm.
 * User: siim
 * Date: 14/02/2018
 * Time: 17:15
 */

namespace Greativ\GCSS\Command;

class Invert extends BaseCommand {

	protected $paramMap = [
		'color',
		'amount'
	];

	public function exec() {
		if ($this->params['color'] == 'transparent') {
			return 'transparent';
		} else {
			return $this->inverseHex($this->params['color'], -($this->params['amount']));
		}
	}

	private function inverseHex($color) {
		$color = trim($color);
		$prependHash = false;
		IF (strpos($color, '#') !== false) {
			$prependHash = true;
			$color = str_replace('#', null, $color);
		}
		switch ($len = strlen($color)) {
			case 3:
				$color = preg_replace("/(.)(.)(.)/", "\\1\\1\\2\\2\\3\\3", $color);
			case 6:
				break;
			default:
				trigger_error("Invalid hex length ($len). Must be (3) or (6)", E_USER_ERROR);
		}
		if (!preg_match('/[a-f0-9]{6}/i', $color)) {
			$color = htmlentities($color);
			trigger_error("Invalid hex string #$color", E_USER_ERROR);
		}
		$r = dechex(255 - hexdec(substr($color, 0, 2)));
		$r = (strlen($r) > 1) ? $r : '0' . $r;
		$g = dechex(255 - hexdec(substr($color, 2, 2)));
		$g = (strlen($g) > 1) ? $g : '0' . $g;
		$b = dechex(255 - hexdec(substr($color, 4, 2)));
		$b = (strlen($b) > 1) ? $b : '0' . $b;
		return ($prependHash ? '#' : null) . $r . $g . $b;
	}

}