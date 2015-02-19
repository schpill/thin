<?php
    namespace Thin\Html\Qwerly;
    class Batch
    {
        private $_found = array();
        private $_notFound = array();
        private $_tryAgainLater = array();

        /**
        * Creates a new batch response.
        *
        * @param array $data The batch response data.
        */
        public function __construct(array $data)
        {
            // Don't even bother looking at this.
            // Will sort out users later.
            unset($data['status']);

            foreach ($data as $identifier => $item) {

                switch ($item['status']) {
                    case \Thin\Html\Qwerly::NOT_FOUND_CODE:
                        $this->_notFound[] = $identifier;
                        break;

                    case \Thin\Html\Qwerly::TRY_AGAIN_LATER_CODE:
                        $this->_tryAgainLater[] = $identifier;
                        break;
                    default:
                        // The user was found!
                        $this->_found[] = new User($item);
                }

            }
        }

        /**
        * Retrieves the list of found users in the batch lookup.
        *
        * @return array
        */
        public function getFoundUsers()
        {
            return $this->_found;
        }

        /**
        * Retrieves the list of not found users in the batch lookup.
        *
        * @return array
        */
        public function getNotFoundUsers()
        {
            return $this->_notFound;
        }

        /**
        * Retrieves the list of users that should be looked up later.
        *
        * @return array
        */
        public function getTryAgainLaterUsers()
        {
            return $this->_tryAgainLater;
        }

    }
