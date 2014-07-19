<?php
    /**
     * Macroable Trait
     * @author      Gerald Plusquellec
     */

	namespace Thin\Trait;

	trait Macroable
	{
		/**
		 * The registered string macros.
		 *
		 * @var array
		 */
		protected static $macros = array();

		/**
		 * Register a custom macro.
		 *
		 * @param  string    $name
		 * @param  callable  $macro
		 * @return void
		 */
		public static function macro($name, $macro)
		{
			static::$macros[$name] = $macro;
		}

		/**
		 * Checks if macro is registered
		 *
		 * @param  string    $name
		 * @return boolean
		 */
		public static function hasMacro($name)
		{
			$macro = isAke(static::$macros, $name, 'noMacro');
			return $macro != 'noMacro';
		}

		/**
		 * Dynamically handle calls to the class.
		 *
		 * @param  string  $method
		 * @param  array   $parameters
		 * @return mixed
		 *
		 * @throws \BadMethodCallException
		 */
		public static function __callStatic($method, $parameters)
		{
			if (static::hasMacro($method)) {
				$macro = isAke(static::$macros, $method, 'noMacro');
				if (is_callable($macro)) {
					return call_user_func_array($macro, $parameters);
				} else {
					throw new \BadMethodCallException("Method {$method} is not callable.");
				}
			}

			throw new \BadMethodCallException("Method {$method} does not exist.");
		}

		/**
		 * Dynamically handle calls to the form builder.
		 *
		 * @param  string  $method
		 * @param  array   $parameters
		 * @return mixed
		 *
		 * @throws \BadMethodCallException
		 */
		public function __call($method, $parameters)
		{
			return static::__callStatic($method, $parameters);
		}
	}
