<?php

namespace Greativ\GCSS;

use Greativ\GCSS\Command\BaseCommand;

/**
 * Class CommandExecutor
 *
 * Handles loading and executing GCSS Commands
 *
 * @package Greativ\GCSS
 */
class CommandExecutor{

	private static $loadedList = array();

	/**
	 * @param string $func Name of the function to excecute
	 * @param array $params function parameters
	 * @return string function result
	 */
	public static function exec($func, $params){
		if($instance = self::load(self::funcToClassName($func))){
			return $instance->setParams($params)->exec();
		}
		return '';
	}

	/**
	 * Loads the requested GCSS command class and returns an instance of it. (FALSE if command class file is not found)
	 *
	 * @param string $className Name of the command class to load
	 * @return BaseCommand|false Loaded GCSS command instance
	 */
	private static function load($className){
		if(!self::isLoaded($className)){
			if(is_file(__DIR__.'/Command/'.$className.'.php')) {
				array_push(self::$loadedList, $className);
			}else{
				return false;
			}
		}
		$className = '\Greativ\GCSS\Command\\'.$className;
		return new $className();
	}


	/**
	 * Checks if a command class is loaded
	 *
	 * @param string $className Name of the command class to check loaded status for
	 * @return bool Command loaded status
	 */
	private static function isLoaded($className){
		return in_array($className, self::$loadedList);
	}

	/**
	 * Turns GCSS function name into a respective command class name
	 *
	 * @param string $func Name on the GCSS function to transform into a respective command class name
	 * @return string Transformed class name
	 */
	private static function funcToClassName($func){
		return ucfirst($func);
	}

}