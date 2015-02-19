<?php
    namespace Thin;

    class Duproprio
    {
        private $seg;
        private $id;
        public $db;
        private $property;

        public function __construct()
        {
            /* MODEL */
            $fields = array(
                'partner'           => array('cantBeNull'   => true),
                'partner_id'        => array('cantBeNull'   => true),
                'address'           => array('canBeNull'    => true),
                'bedroom'           => array('canBeNull'    => true),
                'type'              => array('canBeNull'    => true),
                'price'             => array('canBeNull'    => true),
                'city'              => array('canBeNull'    => true),
                'thumb'             => array('canBeNull'    => true),
            );

            $conf = array(
                'db'            => 'lite',
                'instanceModel' => $this,
                'checkId'       => 'partner_id',
                'checkTuple'    => 'partner_id'
            );
            data('propriete', $fields, $conf);
            $this->db = Data::lite('propriete');
            Data::prepareDbLite('propriete');
        }

        public function addDb($row)
        {
            if ($row instanceof Container) {
                $data = $row->toArray();
            } else {
                $data = $row;
            }
            $q = "SELECT * FROM proprietes WHERE partner_id = '" . $data['partner_id'] . "'";
            $res = $this->db->query($q);
            if(false === $res->fetchArray()) {
                $insert = "INSERT INTO proprietes
                (id, date_create, partner, partner_id, price, address, city, type, bedroom, thumb)
                VALUES (
                    '" . $data['id'] . "',
                    '" . $data['date_create'] . "',
                    'duproprio',
                    '" . \SQLite3::escapeString($data["partner_id"]) . "',
                    '" . \SQLite3::escapeString($data["price"]) . "',
                    '" . \SQLite3::escapeString($data["address"]) . "',
                    '" . \SQLite3::escapeString($data["city"]) . "',
                    '" . \SQLite3::escapeString($data["type"]) . "',
                    '" . \SQLite3::escapeString($data["bedroom"]) . "',
                    '" . \SQLite3::escapeString($data["thumb"]) . "'
                )";
                $this->db->exec($insert);
            } else {
                $update = "UPDATE proprietes
                    SET price = '" . \SQLite3::escapeString($data["price"]) . "',
                    address = '" . \SQLite3::escapeString($data["address"]) . "',
                    city = '" . \SQLite3::escapeString($data["city"]) . "',
                    type = '" . \SQLite3::escapeString($data["type"]) . "',
                    bedroom = '" . \SQLite3::escapeString($data["bedroom"]) . "',
                    thumb = '" . \SQLite3::escapeString($data["thumb"]) . "'
                    WHERE partner_id = '" . \SQLite3::escapeString($data["partner_id"]) . "'";
                $this->db->exec($update);
            }
            return $row;
        }

        public function getDb()
        {
            return $this->db;
        }

        private function clean($str)
        {
            $str = trim($str);

            while ($str[0] == ' ') {
                $str = substr($str, 1, count($str));
            }
            while ($str[strlen($str) - 1] == ' ') {
                $str = substr($str, 0, -1);
            }
            return $str;
        }

        public function getAds($maxPages = 0)
        {
            set_time_limit(0);
            $first = dwn('http://m.duproprio.com/resultats?hash=/s-pmin=0/s-pmax=99999999/p-ord=date/p-dir=DESC/pa-ge=1/s-filter=forsale/s-build=1/p-con=main/g-pr=1/s-bmin=0');
            $nbPages = 0 == $maxPages ? Utils::cut('Page 1 de ', ' ', $first) : $maxPages;
            $ids = array();
            $infos = array();
            $pageIds = array();
            $tab = explode('<a id=', $first);
            for ($i = 1 ; $i < count($tab); $i++) {
                $seg = trim($tab[$i]);
                $id = Utils::cut('"', '"', $seg);
                if (!Arrays::in($id, $ids)) {
                    array_push($ids, $id);
                    array_push($pageIds, $id);
                }
            }

            foreach ($pageIds as $id) {
                $seg = $this->tag('<div class="thumbnail" data-listing-code="' . $id . '">', '<a id="', $first);
                if (!empty($seg)) {
                    $tmp = $this->tag('<img src="', '"', $seg);
                    $picId = $this->tag('-big-', '.', $tmp);
                    $infos[$id]['thumb'] = $picId;
                    $type = $this->tag('<span class="listing-type">', '</span>', $seg);
                    $type = html_entity_decode(repl(array("\n", "\r", "\t"), '', strip_tags($type)));
                    if (contain(',', $type)) {
                        list($type, $bedroom) = explode(',', $type, 2);
                    } else {
                        $type = $type;
                        $bedroom = 0;
                    }
                    $infos[$id]['type'] = $this->clean($type);
                    $infos[$id]['bedroom'] = repl(' ch.', '', $this->clean($bedroom));
                    $address = $this->tag('<span class="listing-address">', '</span>', $seg);
                    $address = html_entity_decode(repl(array("\n", "\r", "\t"), '', strip_tags($address)));
                    $address = $this->clean($address);
                    $tabAddress = explode(', ', $address);
                    if (count($tabAddress)) {
                        $city = Arrays::last($tabAddress);
                        $address = repl(', ' . $city, '', $address);
                    } else {
                        $city = '';
                    }
                    $infos[$id]['address'] = $address;
                    $infos[$id]['city'] = $city;
                    $price = $this->tag('<span class="listing-price">', '</span>', $seg);
                    $price = html_entity_decode(repl(array("\n", "\r", "\t"), '', strip_tags($price)));
                    $infos[$id]['price'] = $this->clean(repl(array(' ', '$'), '', $price));
                    $infos[$id]['partner'] = 'duproprio';
                    $infos[$id]['partner_id'] = $id;
                }
            }
            foreach ($infos as $id => $property) {
                Data::getById('property', Data::add('propriete', $property));
            }

            for ($j = 2; $j <= (int) $nbPages; $j++) {
                $page = dwn('http://m.duproprio.com/resultats?page=' . $j . '&hash=%2Fs-pmin%3D0%2Fs-pmax%3D99999999%2Fp-ord%3Ddate%2Fp-dir%3DDESC%2Fpa-ge%3D2%2Fs-filter%3Dforsale%2Fs-build%3D1%2Fp-con%3Dmain%2Fg-pr%3D1%2Fs-bmin%3D0');

                $infos = array();
                $pageIds = array();
                $tab = explode('<a id=', $page);
                for ($i = 1 ; $i < count($tab); $i++) {
                    $seg = trim($tab[$i]);
                    $id = Utils::cut('"', '"', $seg);
                    if (!Arrays::in($id, $ids)) {
                        array_push($ids, $id);
                        array_push($pageIds, $id);
                    }
                }

                foreach ($pageIds as $id) {
                    $seg = $this->tag('<div class="thumbnail" data-listing-code="' . $id . '">', '<a id="', $page);
                    if (!empty($seg)) {
                        $tmp = $this->tag('<img src="', '"', $seg);
                        $picId = $this->tag('-big-', '.', $tmp);
                        $infos[$id]['thumb'] = $picId;
                        $type = $this->tag('<span class="listing-type">', '</span>', $seg);
                        $type = html_entity_decode(repl(array("\n", "\r", "\t"), '', strip_tags($type)));
                        if (contain(',', $type)) {
                            list($type, $bedroom) = explode(',', $type, 2);
                        } else {
                            $type = $type;
                            $bedroom = 0;
                        }
                        $infos[$id]['type'] = $this->clean($type);
                        $infos[$id]['bedroom'] = repl(' ch.', '', $this->clean($bedroom));
                        $address = $this->tag('<span class="listing-address">', '</span>', $seg);
                        $address = html_entity_decode(repl(array("\n", "\r", "\t"), '', strip_tags($address)));
                        $address = $this->clean($address);
                        $tabAddress = explode(', ', $address);
                        if (count($tabAddress)) {
                            $city = Arrays::last($tabAddress);
                            $address = repl(', ' . $city, '', $address);
                        } else {
                            $city = '';
                        }
                        $infos[$id]['address'] = $address;
                        $infos[$id]['city'] = $city;
                        $price = $this->tag('<span class="listing-price">', '</span>', $seg);
                        $price = html_entity_decode(repl(array("\n", "\r", "\t"), '', strip_tags($price)));
                        $infos[$id]['price'] = $this->clean(repl(array(' ', '$'), '', $price));
                        $infos[$id]['partner'] = 'duproprio';
                        $infos[$id]['partner_id'] = $id;
                    }
                }
                foreach ($infos as $id => $property) {
                    Data::getById('property', Data::add('propriete', $property));
                }
            }
            return $this;
        }

        public function extract($start = 0)
        {
            set_time_limit(0);
            $file = STORAGE_PATH . DS . 'duproprio.php';
            $data = fgc($file);
            $data = repl('[0', '0', $data);
            $data = repl('[', ',', $data);
            $data = repl(']', '', $data);
            File::delete($file);
            File::put($file, $data);
            $ids = include($file);
            $i = 0;
            foreach ($ids as $id) {
                if ($start > 0 && $i < $start) {
                    $i++;
                    continue;
                }
                $this->id = $id;
                $this->getAd()->save();
                $i++;
            }
            echo 'task finished';
            return $this;
        }

        private function getLatitude()
        {
            $this->property['latitude'] = $this->tag('data-listing-lat="', '"', $this->seg);
            return $this;
        }

        private function getLongitude()
        {
            $this->property['longitude'] = $this->tag('data-listing-lon="', '"', $this->seg);
            return $this;
        }

        private function getAddress()
        {
            $this->property['address'] = urldecode($this->tag('data-listing-address="', '"', $this->seg));
            return $this;
        }

        private function getCity()
        {
            $this->property['city'] = $this->tag("setTargeting('City', '", "'", $this->seg);
            return $this;
        }

        private function getProvince()
        {
            $this->property['province'] = $this->tag("setTargeting('Province', '", "'", $this->seg);
            return $this;
        }

        private function getNumBedroom()
        {
            $this->property['num_bedroom'] = $this->tag("setTargeting('Numbedroom', '", "'", $this->seg);
            return $this;
        }

        private function getType()
        {
            $this->property['type'] = $this->tag("setTargeting('Proptype', '", "'", $this->seg);
            return $this;
        }

        private function getPriceCategory()
        {
            $this->property['price_category'] = $this->tag("setTargeting('Price', '", "'", $this->seg);
            return $this;
        }

        private function getPrice()
        {
            $this->property['price'] = $this->tag('<span class="listing-price">', '</span>', $this->seg);
            return $this;
        }

        private function getPics()
        {
            $seg = $this->tag('<div id="links">', '</div>', $this->seg);
            $tab = explode('<a href=', $seg);
            $picIds = array();
            if (!empty($seg)) {
                for ($i = 1 ; $i < count($tab) ; $i++) {
                    $img = $this->tag('"', '"', trim($tab[$i]));
                    $picId = $this->tag('-big-', '.', $img);
                    if (!Arrays::in($picId, $picIds)) {
                        array_push($picIds, $picId);
                    }
                }
            }
            $this->property['pics'] = implode(',', $picIds);
            return $this;
        }

        private function getCaracteristics()
        {
            $caracteristics = array();
            $seg = $this->tag('<ul class="list-unstyled">', '</ul>', $this->seg);
            if (!empty($seg) && contain('<li><strong>', $seg)) {
                $tab = explode('<li><stron', $seg);
                for ($i = 1 ; $i < count($tab) ; $i++) {
                    $key = $this->tag('g>', ' :</strong>', trim($tab[$i]));
                    $value = $this->tag(' :</strong>', '</li>', trim($tab[$i]));
                    $caracteristics[$key] = $value;
                }
            }

            $this->property['caracteristics'] = $caracteristics;
            return $this;
        }

        private function getDescription()
        {
            $description = $this->tag(
                '<h4>Remarques du proprio</h4>',
                '<h4>Caract&eacute;ristiques de la propri&eacute;t&eacute;</h4>',
                $this->seg
            );
            if (!empty($description)) {
                $description = strip_tags($description);
                $description = html_entity_decode(repl(array("\n", "\r", "\t"), '', $description));
                $this->property['description'] = $description;
            }
            return $this;
        }

        private function getHomePhone()
        {
            $seg = $this->tag('<p>T&eacute;l&eacute;phone</p>', '<div class="row"', $this->seg);
            if (!empty($seg)) {
                $homePhone = $this->tag('<a href="tel:', '"', $seg);
                $this->property['home_phone'] = $homePhone;
            }
            return $this;
        }

        private function getWorkPhone()
        {
            $seg = $this->tag('<p>Travail</p>', '<div class="row"', $this->seg);
            if (!empty($seg)) {
                $workPhone = $this->tag('<a href="tel:', '"', $seg);
                $this->property['work_phone'] = $workPhone;
            }
            return $this;
        }

        private function getFax()
        {
            if (contain('T&eacute;l&eacute;copieur', $this->seg)) {
                $fax = $this->tag('<p class="no-padding"><strong>', '</strong></p>', $this->seg);
                $this->property['fax'] = '+1' . $fax;
            }
            return $this;
        }

        private function getCellPhone()
        {
            $seg = $this->tag('<p>Cellulaire</p>', '<div class="row"', $this->seg);
            if (!empty($seg)) {
                $cellPhone = $this->tag('<a href="tel:', '"', $seg);
                $this->property['cell_phone'] = $cellPhone;
            }
            return $this;
        }

        public function getTaxes()
        {
            $seg = $this->tag('<td>Taxes municipales</td>', '</tr>', $this->seg);
            if (!empty($seg)) {
                $tab = implode('##', explode('</td>', $seg));
                $tmp = html_entity_decode(repl("\n", '', strip_tags($tab)));
                $tmp = repl("\r", "", $tmp);
                $tmp = repl("\t", "", trim($tmp));
                list($month, $year) = explode('##', $tmp, 2);
                $month = repl(array(' ', '##'), '', $month);
                $year = repl(array(' ', '##'), '', $year);
                $this->property['city_tax_month'] = $month;
                $this->property['city_tax_year'] = $year;
            }
            $seg = $this->tag('<td>Taxes scolaires</td>', '</tr>', $this->seg);
            if (!empty($seg)) {
                $tab = implode('##', explode('</td>', $seg));
                $tmp = html_entity_decode(repl("\n", '', strip_tags($tab)));
                $tmp = repl("\r", "", $tmp);
                $tmp = repl("\t", "", trim($tmp));
                list($month, $year) = explode('##', $tmp, 2);
                $month = repl(array(' ', '##'), '', $month);
                $year = repl(array(' ', '##'), '', $year);
                $this->property['school_tax_month'] = $month;
                $this->property['school_tax_year'] = $year;
            }
            return $this;
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

        public function save($row)
        {
            return $this->addDb($row);
        }

        public function getImg($id, $size = 'large' /* small medium big large */)
        {
            return 'http://photos.duproprio.com/img-' . Inflector::lower($size) . '-' . $id . '.jpg';
        }

        private function tag($start, $end, $segment)
        {
            if (contain($start, $segment) && contain($end, $segment)) {
                return Utils::cut($start, $end, $segment);
            }
            return null;
        }

        public function getProperty()
        {
            return $this->property;
        }
    }
