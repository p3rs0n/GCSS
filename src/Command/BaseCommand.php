<?php
/**
 * Created by PhpStorm.
 * User: siim
 * Date: 14/02/2018
 * Time: 17:15
 */

namespace Greativ\GCSS\Command;

/**
 * Class GcssCmd Abstract baseclass for all GCSS commands
 * @package App\Greativ\GCSS\Command
 */
abstract class BaseCommand{
	/**
	 * @var array Guide to remap parameter array. Create array as oldkey=>newkey.
	 */
	protected $paramMap = [];
	/**
	 * @var array Holds parameters that may be remapped
	 */
	protected $params = [];
	/**
	 * @var array Holds original unmapped parameters
	 */
	protected $unmapedParams = [];

	/**
	 * @return string Abstract function for main execution of the command
	 */
	public abstract function exec();

	/**
	 * Sets the parameter array for this command
	 *
	 * @param array $params Array of parameters
	 * @return GcssCmd $this
	 */
	public function setParams(array $params){
		$this->params = $this->unmapedParams = $params;
		if($params && $this->paramMap){
			$this->mapParams();
		}
		return $this;
	}

	/**
	 * Remaps parameter array
	 */
	private function mapParams(){
		$temp = [];
		foreach($this->paramMap as $index => $key){
			$temp[$key] = $this->params[$index];
		}
		$this->params = $temp;
	}
}