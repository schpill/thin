<?php/**
     * Service Facebook class
     *
     * @needs       Facebook API
     * @author      Gerald Plusquellec
     */
    namespace Thin\Service;
    class Facebook
    {
        protected $_adapter = null;

        protected $_apiKey;

        protected $_appApiID;

        protected $_secretKey;

        protected $_cookie;

        public function __construct($apiKey, $appApiID, $secretKey, $cookie = true)
        {
            $this->setAdapter($apiKey, $appApiID, $secretKey, $cookie);
        }

        public function setUserId($userId)
        {
            $this->_userId = $userId;

            return $this;
        }

        public function getUserInfo($fields = null)
        {
            if (null === $fields) {
                $fields = array('first_name','last_name','profile_update_time','current_location', 'sex', 'birthday', 'pic_square');
            }

            $details = $this->getAdapter()->api(array('method' => 'users.getInfo', 'uids' => $this->getUserId(), 'fields' => $fields));

            return $details;
        }

        public function getAdapter()
        {
            if (null === $this->_adapter) {
                $this->_adapter  = new \Facebook(array(
                  'appId'  => $this->_appApiID,
                  'secret' => $this->_secretKey,
                  'cookie' => $this->_cookie,
                ));
            }

            return $this->_adapter;
        }

        public function setAdapter($apiKey, $appApiID, $secretKey, $cookie = true)
        {
            $this->_apiKey = $apiKey;
            $this->_secretKey = $secretKey;
            $this->_appApiID = $appApiID;
            $this->_cookie = $cookie;

            return $this;
        }

        public function getStatus($limit = 100)
        {
            return $this->getAdapter()->api(array('method' => 'facebook.status.get', 'uid' => $this->getUserId(), 'limit'=> $limit));
        }

        public function getPhotos()
        {
            return $this->getAdapter()->api(array('method' => 'Photos.get', 'subj_id' => $this->getUserId()));
        }

        public function getUserId()
        {
            return $this->getAdapter()->getUser();
        }

        public function uploadPhoto($path, $name)
        {
            $session = $this->getAdapter()->getSession();

            if (!$album = $this->hasAlbum($name)) {
                $params = array(
                    'access_token' => $session['access_token'],
                    'name'         => $name,
                    'method'       => 'photos.createAlbum'
                );

                $album = $this->getAdapter()->api($params);
            }

            $aid = $album['aid'];

            if (null === $aid) {
                throw new \thin\Exception('Album ID is not defined');
            }

            $client = new \Zend_Http_Client();
            $client->setFileUpload($path, basename($path))
                   ->setParameterPost(array(
                       'format'         => 'json',
                       'aid'            => $aid,
                       'method'         => 'photos.upload',
                       'access_token'   =>  $session['access_token']
                   ))->setUri('https://api.facebook.com/restserver.php');

            $response = $client->request(\Zend_Http_Client::POST);

            $data = json_decode($response->getBody());

            return $data->pid;
        }

        public function getAlbums($limit = 100)
        {
            $session = $this->getAdapter()->getSession();

            $message =  array(
                'access_token' => $session['access_token'],
                'limit'        => $limit,
                'method'       => 'photos.getAlbums',
                'format'       => 'json'
            );

            return (array) $this->getAdapter()->api($message);
        }

        public function hasAlbum($name)
        {
            foreach ($this->getAlbums() as $album) {
                if ($album['name'] == $name) {
                    return $album;
                }
            }

            return false;
        }
    }
