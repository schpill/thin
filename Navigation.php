<?php
    namespace Thin;

    use Thin\Navigation\Container;

    class Navigation extends Container
    {
        /**
         * Creates a new navigation container
         *
         * @param array $pages  [optional] pages to add
         * @throws Exception    if $pages is invalid
         */
        public function __construct($pages = null)
        {
            if (Arrays::is($pages)) {
                $this->addPages($pages);
            } elseif (null !== $pages) {
                throw new Exception('Invalid argument: $pages must be an array or null');
            }
        }
    }
