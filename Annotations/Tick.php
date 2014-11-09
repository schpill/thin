<?php
	namespace Thin\Annotations;

	/**
	 * @Annotation
	 */
	class Tick
	{
		/**
		 * The ticks the annotation finds.
		 *
		 * @var array
		 */
		public $ticks = [];

		/**
		 * Create a new annotation instance.
		 *
		 * @return void
		 */
		public function __construct(array $values = array())
		{
			$this->ticks = (array) $values['value'];
		}
	}
