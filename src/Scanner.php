<?php
	namespace Thin;

	use ReflectionClass;
	use Symfony\Component\Finder\Finder;
	use Doctrine\Common\Annotations\AnnotationRegistry;
	use Doctrine\Common\Annotations\SimpleAnnotationReader;

	class Scanner
	{
		/**
		 * The classes to scan for annotations.
		 *
		 * @var string
		 */
		protected $scan;

		/**
		 * Create a new scanner instance.
		 *
		 * @param  array  $scan
		 * @return void
		 */
		public function __construct(array $scan)
		{
			$this->scan = $scan;

			foreach (Finder::create()->files()->in(__DIR__ . DS . 'Annotations') as $file) {
				AnnotationRegistry::registerFile($file->getRealPath());
			}
		}

		/**
		 * Create a new scanner instance.
		 *
		 * @param  array  $scan
		 * @return static
		 */
		public static function create(array $scan)
		{
		 	return new static($scan);
		}

		/**
		 * Convert the scanned annotations into ticks definitions.
		 *
		 * @return string
		 */
		public function getTicks()
		{
			$collection = [];

			$reader = $this->getReader();

			foreach ($this->getClassesToScan() as $class) {
				foreach ($class->getMethods() as $method) {
					foreach ($reader->getMethodAnnotations($method) as $annotation) {
						array_push($collection, $annotation->ticks);
					}
				}
			}

			return $collection;
		}

		/**
		 * Build the event listener for the class and method.
		 *
		 * @param  string  $class
		 * @param  string  $method
		 * @param  array  $events
		 * @return string
		 */
		protected function buildListener($class, $method, $events)
		{
			return sprintf(
				'$events->listen(%s, \'' . $class . '::' . $method . '\');',
				var_export($events, true)
			) . PHP_EOL;
		}

		/**
		 * Get all of the ReflectionClass instances in the scan path.
		 *
		 * @return array
		 */
		protected function getClassesToScan()
		{
			$classes = [];

			foreach ($this->scan as $class) {
				try {
					$classes[] = new ReflectionClass($class);
				} catch (\Exception $e) {
					//
				}
			}

			return $classes;
		}

		/**
		 * Get an annotation reader instance.
		 *
		 * @return \Doctrine\Common\Annotations\SimpleAnnotationReader
		 */
		protected function getReader()
		{
			with($reader = new SimpleAnnotationReader)->addNamespace('Thin\Annotations');

			return $reader;
		}

	}
