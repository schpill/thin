<?php
    namespace Thin\Injection\Service;

    interface Interface extends Locator
    {
        /**
         * Register a service with the locator
         *
         * @abstract
         * @param  string                  $name
         * @param  mixed                   $service
         * @return Locator
         */
        public function set($name, $service);
    }
