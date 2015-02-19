<?php
    namespace Yhin\Store\Api;

    use Thin\Store;
    use Thin\Utils;
    use Thin\Arrays;
    use Thin\Inflector;

    class Server
    {
        private $user;
        private $tables = array();

        public function __construct($publicKey, $privateKey)
        {
            $this->models();
            $db = new Store('apistore_user');
            $user = $db->findOneByPublicKey($publicKey);
            if (!empty($user)) {
                if ($user->getPrivateKey() != $privateKey) {
                    $this->render('You cannot access to this API.', 500);
                }
            } else {
                $this->render('You cannot access to this API.', 500);
            }
            $this->user = $user;
        }

        public function table($table)
        {
            $instance = isAke($this->tables, $table);
            if (null === $onstance) {
                $instance = new Store($table);
            }
        }

        private function models()
        {
            $fields = array(
                'name'          => array(),
                'firstname'     => array(),
                'email'         => array(),
                'public_key'    => array(),
                'private_key'   => array(),
                'token'         => array('canBeNull' => true),
                'expire'        => array('canBeNull' => true)
            );
            $conf = array(
                'checkTuple'        => 'email',
                'functions'         => array(
                    'findRights'    => function ($obj) {
                        $userId = $obj->getId();
                        $db = new Store('apistore_right');
                        return $db->where("user = $userId")->fetch();
                    }
                )
            );
            data('apistore_user', $fields, $conf);

            $fields = array(
                'user'          => array(),
                'table'         => array(),
                'can_add'       => array('default' => false),
                'can_edit'      => array('default' => false),
                'can_duplicate' => array('default' => false),
                'can_delete'    => array('default' => false),
                'can_search'    => array('default' => false)
            );
            $conf = array(
                'checkTuple'        => array('user', 'table'),
                'functions'         => array(
                    'findUser'      => function ($obj) {
                        $userId = $obj->getUser();
                        $db = new Store('apistore_user');
                        return $db->find($userId);
                    },
                    'findTable'     => function ($obj) {
                        $tableId = $obj->getYable();
                        $db = new Store('apistore_table');
                        return $db->find($tableId);
                    }
                )
            );
            data('apistore_right', $fields, $conf);

            $fields = array(
                'name'          => array(),
                'model'         => array()
            );
            $conf = array(
                'checkTuple'        => 'name',
                'functions'         => array(
                    'findRights'    => function ($obj) {
                        $tableId = $obj->getId();
                        $db = new Store('apistore_right');
                        return $db->where("table = $tableId")->fetch();
                    }
                )
            );
            data('apistore_table', $fields, $conf);
        }
    }
