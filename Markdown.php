<?php
	namespace Thin;

	class Markdown
	{
		/**
		 * @var  MarkdownExtra  The Markdown Extra parser instance
		 */
		protected static $parser = null;

		/**
		 * Load Markdown and get it setup.
		 *
		 * @return  void
		 */
		public static function _init()
		{
			if (!class_exists('Michelf\MarkdownExtra')) {
				throw new Exception('The Markdown composer library isn\'t installed. Make sure it\'s in your "composer.json", then run "composer update" to install it!');
			}

			static::$parser = new \Michelf\MarkdownExtra();
		}

		/**
		 * Runs the Markdown parser instance, so you can pass custom configuration
		 *
		 * @return  MarkdownExtra
		 */
		public static function instance()
		{
			return static::$parser;
		}

		/**
		 * Runs the given text through the Markdown parser.
		 *
		 * @param   string  Text to parse
		 * @return  string
		 */
		public static function parse($text)
		{
			return static::$parser->transform($text);
		}
	}
