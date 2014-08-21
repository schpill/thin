<?php
    /**
     * Cart class
     * @author      Gerald Plusquellec
     */
    namespace Thin;

    class Cart
    {
        /**
         * Session class instance
         *
         * @var Session
         */
        protected $session;

        /**
         * Current cart instance
         *
         * @var string
         */
        protected $instance;

        /**
         * Constructor
         *
         * @param Cart's name $name
         */
        public function __construct($name)
        {
            $this->session = Session::instance('cart_' . $name);
            $this->instance = $name;
        }

        /**
         * Set the current cart instance
         *
         * @param  string $instance Cart instance name
         * @return Cart
         */
        public function instance($name = null)
        {
            if(empty($instance)) {
                throw new Exception("A cart name is mandatory.");
            }

            $this->instance = $name;

            // Return self so the method is chainable
            return $this;
        }

        /**
         * Add a row to the cart
         *
         * @param string|Array $id      Unique ID of the item|Item formated as array|Array of items
         * @param string       $name    Name of the item
         * @param int          $qty     Item qty to add to the cart
         * @param float        $price   Price of one item
         * @param Array        $options Array of additional options, such as 'size' or 'color'
         */
        public function add($id, $name = null, $qty = null, $price = null, Array $options = array())
        {
            // If the first parameter is an array we need to call the add() function again
            if(Arrays::is($id)) {
                // And if it's not only an array, but a multidimensional array, we need to
                // recursively call the add function
                if($this->isMulti($id)) {
                    foreach($id as $item) {
                        $options = Arrays::exists('options', $item) ? $item['options'] : array();
                        $this->addRow($item['id'], $item['name'], $item['qty'], $item['price'], $options);
                    }

                    return;
                }

                $options = Arrays::exists('options', $id) ? $id['options'] : array();
                return $this->addRow($id['id'], $id['name'], $id['qty'], $id['price'], $options);
            }

            return $this->addRow($id, $name, $qty, $price, $options);
        }

        /**
         * Add multiple rows to the cart
         * Maps to add() function
         * Will probably be removed in future versions
         *
         * @param Array $items An array of items to add, use array keys corresponding to the 'add' method's parameters
         */
        public function addBatch(Array $items)
        {
            return $this->add($items);
        }

        /**
         * Update the quantity of one row of the cart
         *
         * @param  string        $rowId       The rowid of the item you want to update
         * @param  integer|Array $attribute   New quantity of the item|Array of attributes to update
         * @return boolean
         */
        public function update($rowId, $attribute)
        {
            if(!$this->hasRowId($rowId)) {
                throw new Exception('This cart does not contain this row.');
            }

            if(Arrays::isArray($attribute)) {
                return $this->updateAttribute($rowId, $attribute);
            }

            return $this->updateQty($rowId, $attribute);
        }

        /**
         * Remove a row from the cart
         *
         * @param  string  $rowId The rowid of the item
         * @return boolean
         */
        public function remove($rowId)
        {
            if(!$this->hasRowId($rowId)) {
                throw new Exception('This cart does not contain this row.');
            }

            $cart = $this->getContent();

            $cart->forget($rowId);

            return $this->updateCart($cart);
        }

        /**
         * Get a row of the cart by its ID
         *
         * @param  string $rowId The ID of the row to fetch
         * @return CartCollection
         */
        public function get($rowId)
        {
            $cart = $this->getContent();
            $rows = $cart->_fields;
            return (Arrays::inArray($rowId, $rows)) ? $cart->$rowId : null;
        }

        /**
         * Get the cart content
         *
         * @return CartRowCollection
         */
        public function content()
        {
            $cart = $this->getContent();

            $rows = $cart->_fields;

            if (!count($rows)) {
                return null;
            }

            $content = array();

            foreach ($rows as $rowId) {
                $content[$rowId] = $cart->$rowId;
            }

            return $content;
        }

        /**
         * Empty the cart
         *
         * @return boolean
         */
        public function destroy()
        {
            return $this->updateCart(null);
        }

        /**
         * Get the price total
         *
         * @return float
         */
        public function total()
        {
            $total = 0;
            $cart = $this->getContent();

            if(empty($cart->_fields)) {
                return $total;
            }

            foreach($cart->_fields as $rowId) {
                $row = $cart->$rowId;
                $total += $row->subtotal;
            }

            return $total;
        }

        /**
         * Get the number of items in the cart
         *
         * @param  boolean $totalItems Get all the items (when false, will return the number of rows)
         * @return int
         */
        public function count($totalItems = true)
        {
            $cart = $this->getContent();

            if(false === $totalItems) {
                return count($cart->_fields);
            }

            $count = 0;

            foreach($cart->_fields as $rowId) {
                $row = $cart->$rowId;
                $count += $row->qty;
            }

            return $count;
        }

        /**
         * Search if the cart has a item
         *
         * @param  Array  $search An array with the item ID and optional options
         * @return Array|boolean
         */
        public function search(array $search)
        {
            $results    = array();
            $cart       = $this->getContent();
            $rows       = $cart->_fields;
            foreach($rows as $rowId) {
                $item  = $cart->$rowId;
                $found = $item->search($search);

                if(true === $found) {
                    $results[] = $item;
                }
            }

            return (empty($results)) ? false : $results;
        }

        /**
         * Add row to the cart
         *
         * @param string $id      Unique ID of the item
         * @param string $name    Name of the item
         * @param int    $qty     Item qty to add to the cart
         * @param float  $price   Price of one item
         * @param Array  $options Array of additional options, such as 'size' or 'color'
         */
        protected function addRow($id, $name, $qty, $price, array $options = array())
        {
            if(empty($id) || empty($name) || empty($qty) || empty($price)) {
                throw new Exception('Some mandatory info are missing');
            }

            if(!is_numeric($qty))  {
                throw new Exception('Quantity must be numeric.');
            }

            if( ! is_numeric($price)) {
                throw new Exception('Price must be numeric.');
            }

            $cart = $this->getContent();

            $rowId = $this->generateRowId($id, $options);

            if(Arrays::in($rowId, $cart->_fields)) {
                $row = $cart->$rowId;
                $cart = $this->updateRow($rowId, array('qty' => $row->qty + $qty));
            } else {
                $cart = $this->createRow($rowId, $id, $name, $qty, $price, $options);
            }

            return $this->updateCart($cart);
        }

        /**
         * Generate a unique id for the new row
         *
         * @param  string  $id      Unique ID of the item
         * @param  Array   $options Array of additional options, such as 'size' or 'color'
         * @return string
         */
        protected function generateRowId($id, $options)
        {
            return static::_makeKey();
        }

        private static $keys = array();

        private static function _makeKey($keyLength = 9)
        {
            $key    = Inflector::quickRandom($keyLength);
            if (!Arrays::in($key, static::$keys)) {
                static::$keys[] = $key;
                return $key;
            } else {
                return static::_makeKey($keyLength);
            }
        }

        /**
         * Check if a rowid exists in the current cart instance
         *
         * @param  string  $id  Unique ID of the item
         * @return boolean
         */
        protected function hasRowId($rowId)
        {
            $cart = $this->getContent();
            $rows = $cart->_fields;
            return Arrays::in($rowId, $rows);
        }

        /**
         * Update the cart
         *
         * @param  CartCollection  $cart The new cart content
         * @return void
         */
        protected function updateCart($cart)
        {
            return $this->session->setContent($cart);
        }

        /**
         * Get the carts content, if there is no cart content set yet, return a new empty Collection
         *
         */
        protected function getContent()
        {
            $content = $this->session->getContent();

            if (null === $content) {
                return new Container;
            }

            return $content;
        }

        /**
         * Update a row if the rowId already exists
         *
         * @param  string  $rowId The ID of the row to update
         * @param  integer $qty   The quantity to add to the row
         * @return Collection
         */
        protected function updateRow($rowId, $attributes)
        {
            $cart = $this->getContent();

            $row = $cart->$rowId;

            $row->populate($attributes);

            if(!is_null(array_keys($attributes, array('qty', 'price')))) {
                $row->put('subtotal', $row->qty * $row->price);
            }

            $cart->put($rowId, $row);

            return $cart;
        }

        /**
         * Create a new row Object
         *
         * @param  string $rowId   The ID of the new row
         * @param  string $id      Unique ID of the item
         * @param  string $name    Name of the item
         * @param  int    $qty     Item qty to add to the cart
         * @param  float  $price   Price of one item
         * @param  Array  $options Array of additional options, such as 'size' or 'color'
         * @return Collection
         */
        protected function createRow($rowId, $id, $name, $qty, $price, $options)
        {
            $cart = $this->getContent();

            $newRow = new Container();

            $values = array(
                'rowid'     => $rowId,
                'id'        => $id,
                'name'      => $name,
                'qty'       => $qty,
                'price'     => $price,
                'options'   => new Container($options),
                'subtotal'  => $qty * $price
            );

            $newRow->populate($values);

            $cart->put($rowId, $newRow);

            return $cart;
        }

        /**
         * Update the quantity of a row
         *
         * @param  string $rowId The ID of the row
         * @param  int    $qty   The qty to add
         * @return CartCollection
         */
        protected function updateQty($rowId, $qty)
        {
            if(1 > $qty) {
                return $this->remove($rowId);
            }

            return $this->updateRow($rowId, array('qty' => $qty));
        }

        /**
         * Update an attribute of the row
         *
         * @param  string $rowId      The ID of the row
         * @param  Array  $attributes An array of attributes to update
         * @return CartCollection
         */
        protected function updateAttribute($rowId, $attributes)
        {
            return $this->updateRow($rowId, $attributes);
        }

        /**
         * Check if the array is a multidimensional array
         *
         * @param  Array   $array The array to check
         * @return boolean
         */
        protected function isMulti(array $array)
        {
            $first = array_shift($array);
            return Arrays::is($first);
        }

    }
