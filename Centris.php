<?php
    namespace Thin;

    class Centris
    {
        private $seg;
        private $id;
        private $property;

        public function __construct()
        {
            /* MODEL */
            $fields = array(
                'longitude'         => array('canBeNull'    => true),
                'latitude'          => array('canBeNull'    => true),
                'partner'           => array('cantBeNull'   => true),
                'partner_id'        => array('cantBeNull'   => true),
                'address'           => array('canBeNull'    => true),
                'description'       => array('canBeNull'    => true),
                'type'              => array('canBeNull'    => true),
                'price'             => array('canBeNull'    => true),
                'caracteristics'    => array('canBeNull'    => true),

                'city'              => array('canBeNull'    => true),
                'province'          => array('canBeNull'    => true),
                'num_bedroom'       => array('canBeNull'    => true),
                'price_category'    => array('canBeNull'    => true),
                'pics'              => array('canBeNull'    => true),
                'caracteristics'    => array('canBeNull'    => true),
                'name'              => array('canBeNull'    => true),
                'firstname'         => array('canBeNull'    => true),
                'email'             => array('canBeNull'    => true),
                'home_phone'        => array('canBeNull'    => true),
                'cell_phone'        => array('canBeNull'    => true),
                'work_phone'        => array('canBeNull'    => true),
                'fax'               => array('canBeNull'    => true),
            );
            $conf = array(
                'checkTuple' => array('longitude', 'latitude')
            );
            $conf = array(
                'checkTuple' => 'partner_id'
            );
            data('property', $fields, array());
            $this->dwn();
        }

        public function getAds($maxPages = 0)
        {
            set_time_limit(0);
        }

        public function getAd($id = null)
        {
            $id             = empty($id) ? $this->id : $id;
            $this->property = array();
            $this->seg      = dwn('http://m.duproprio.com/habitation/' . $id);
            $methods        = array(
                'getDescription',
                'getLongitude',
                'getLatitude',
                'getAddress',
                'getCity',
                'getProvince',
                'getNumBedroom',
                'getType',
                'getPriceCategory',
                'getPrice',
                'getPics',
                'getCaracteristics',
                'getHomePhone',
                'getWorkPhone',
                'getFax',
                'getCellPhone',
                'getTaxes',
                'getPics'
            );
            $this->property['partner_id'] = $id;
            $this->property['partner'] = 'duproprio';
            foreach ($methods as $method) {
                $this->$method();
            }
            return $this;
        }

        private function dwn($page = 1)
        {
            $data           = array("pageIndex" => $page, "track" => true);
            $dataString     = json_encode($data);

            $referer        = 'http://www.centris.ca/fr/';
            $ip             = '65.39.160.111';
            $ch             = curl_init();

            $headers        = array();

            $url = 'http://www.centris.ca/Services/PropertyService.asmx/GetPropertyViews';

            $userAgent = 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.6 (KHTML, like Gecko) Chrome/16.0.897.0 Safari/535.6';

            curl_setopt($ch, CURLOPT_URL,       $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
            // curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_AUTOREFERER, false);
            curl_setopt($ch, CURLOPT_REFERER, 'http://www.centris.ca/fr');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_HEADER, true);

            $headers[] = "REMOTE_ADDR: $ip";
            $headers[] = "HTTP_X_FORWARDED_FOR: $ip";
            $headers[] = 'Cookie: ASP.NET_SessionId=gtxmbdtwbalnpvmcecg5mojf; path=/; HttpOnly';
            $headers[] = 'Cookie: Centris=Token=bc5b5528-9766-4291-b72a-c8c9c445d01c&Lang=fr&Reg=PROVPROVQC&PropertySearchView=List&PromotionVersion=0&PromotionPopupShownCount=2; domain=centris.ca; expires=Sun, 15-Jun-2014 13:33:24 GMT; path=/';
            $headers[] = 'Cookie: Centris.PropertySearchFavorites=; domain=centris.ca; expires=Sun, 15-Jun-2014 13:33:24 GMT; path=/';
            $headers[] = 'Cookie: Centris.PropertySearchRemoved=; domain=centris.ca; expires=Sun, 15-Jun-2014 13:33:24 GMT; path=/';
            $headers[] = 'Cookie: Centris.BrokerSearchFavorites=; domain=centris.ca; expires=Sun, 15-Jun-2014 13:33:24 GMT; path=/';
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($dataString);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            dieDump($result);
        }

    }
