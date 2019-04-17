<?php
/**
 * Created by PhpStorm.
 * User: siim
 * Date: 14/02/2018
 * Time: 17:15
 */

namespace Greativ\GCSS\Command;

class Random extends BaseCommand {

	protected $paramMap = [
		'min',
		'max',
		'unit'
	];

	public function exec() {
		return rand($this->params['min'], $this->params['max']) . $this->params['unit'];
	}

}