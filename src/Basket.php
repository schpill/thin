<?php
    namespace Thin;
    class Basket
    {
        private static $sessions    = array();
        private static $sessionKey;
        private static $session;
        private static $items       = "ITEMS";
        private static $counter     = "COUNT";

        public static function instance($ns)
        {
            static::$sessionKey     = $ns;
            static::$sessions[$ns]  = session('Basket_' . $ns);
            static::$session        = static::$sessions[$ns];
        }

        private static function setLastModify()
        {
            static::$session->setLastMod(time());
        }

        private static function changeCount($quantity, $mode = MODE_SUB)
        {
            $count = null === static::$session->getCount() ? 0 : static::$session->getCount();
            switch ($mode) {
                case MODE_SUB:
                    $count -= $quantity;
                    break;
                case MODE_ADD:
                    $count += $quantity;
                    break;
            }
            static::$session->setCount($count);
            static::setLastModify();
        }

        public static function emptyBasket()
        {
            static::$session->erase();
        }

        public static function getItemtypeCount()
        {
            $items = null === static::$session->getItems() ? array() : static::$session->getItems();
            return count($items);
        }

        public static function getItemCount()
        {
            $count = null === static::$session->getCount() ? 0 : static::$session->getCount();
            return $count;
        }

        public function getItemList()
        {
            $items = null === static::$session->getItems() ? array() : static::$session->getItems();
            return $items;
        }

        public static function getLastModify($ts = true)
        {
            $lastMod = null === static::$session->getLastMod() ? time() : static::$session->getLastMod();
            if (true === $ts) {
              return $lastMod;
            }
            else {
                return date("Y-m-d H:i:s", $lastMod);
            }
        }

        public static function removeItem($id, $quantity = 0)
        {
            $ret = false;
            $items = null === static::$session->getItems() ? array() : static::$session->getItems();
            $item = isAke($items, $id);
            if (!empty($item)) {
                if ($quantity == 0) {
                    static::changeCount($item['quantity']);
                    unset($items[$id]);
                    static::$session->setItems($items);
                    $ret = true;
                }
                else {
                    if (self::changeItem($id, $quantity, null, 0, MODE_SUB)) {
                        $ret = true;
                    }
                }
            }
            return $ret;
        }

        public static function changeItem($id, $quantity, $name = null, $price = 0, $mode = MODE_OVERWRITE)
        {
            $ret = false;
            $items = null === static::$session->getItems() ? array() : static::$session->getItems();
            $item = isAke($items, $id);
            if (!empty($item)) {
                switch ($mode) {
                    case MODE_ADD:
                        $items[$id]['quantity'] += $quantity;
                        static::changeCount($quantity, MODE_ADD);
                        $ret = true;
                        break;
                    case MODE_OVERWRITE:
                        $items[$id] = array(
                            'name'      => $name,
                            'quantity'  => $quantity,
                            'price'      => $price,
                        );
                        static::changeCount($quantity, MODE_ADD);
                        $ret = true;
                        break;
                    case MODE_SUB:
                        $items[$id]['quantity'] -= $quantity;
                        static::changeCount($quantity);
                        $ret = true;
                        break;
                }
                static::$session->setItems($items);
            }
            return $ret;
        }

        public static function addItemFromArray($data, $mode = MODE_OVERWRITE)
        {
            $ret = 0;
            if (count($data) > 0) {
                foreach ($data as $d) {
                    if (Arrays::isAssoc($d)) {
                        $id         = isAke($d, 'id');
                        $quantity   = isAke($d, 'quantity');
                        $name       = isAke($d, 'name');
                        $price      = isAke($d, 'price');
                        if (!empty($id) && !empty($quantity) && !empty($name) && !empty($price)) {
                            static::changeItem(
                                $d['id'],
                                $d['quantity'],
                                $d['name'],
                                $d['price'],
                                $mode
                            );
                            $ret++;
                        }
                    }
                }
            }
            return $ret;
        }

        public static function getFullPrice()
        {
            $ret = 0;
            $items = null === static::$session->getItems() ? array() : static::$session->getItems();
            if(count($items)) {
                foreach ($items as $item) {
                    if (Arrays::isAssoc($item)) {
                        $quantity   = isAke($item, 'quantity');
                        $price      = isAke($itemd, 'price');
                        if (!empty($quantity) && !empty($price)) {
                            $ret += $item['price'] * $item['quantity'];
                        }
                    }
                }
            }
            return $ret;
        }
    }
