<?php
    /**
     * Geoloc class
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    class Geoloc
    {
        public static function request($address, $locale = 'fr')
        {
            $ch      = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://nominatim.openstreetmap.org/search?q=' . urlencode($address) . '&format=json&addressdetails=1&accept-language=' . urlencode($locale));
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/6.0 (X11; U; Linux i686; pl-PL; rv:1.9.0.2) Gecko/20130123 Ubuntu/12.10 (precise) Firefox/3.8');
            $response = curl_exec($ch);
            curl_close($ch);

            $jsonOutput = json_decode($response, true);
            if(!$jsonOutput) {
                throw new Exception("the address has not been recognized.");
            }
            else if(count($jsonOutput) <= 0) {
                return null;
            }
            else if(count($jsonOutput) > 1) {
                $results = array();
                foreach($jsonOutput as $result) {
                    $results[] = static::convertToLocationObject($result);
                }
                return $results;
            }
            else {
                return static::convertToLocationObject($jsonOutput[0]);
            }
        }

        public static function convertToLocationObject($result)
        {
            $ret = new Location;

            $ret->setRoad(static::getElement($result['address'], 'road'));
            $ret->setHouseNumber(static::getElement($result['address'],'house_number'));
            $ret->setSubUrb(static::getElement($result['address'], 'suburb'));
            $ret->setCity(static::getElement($result['address'], 'city'));
            $ret->setPostCode(static::getElement($result['address'], 'postcode'));

            $ret->setStateDistrict(static::getElement($result['address'], 'state_district'));
            $ret->setState(static::getElement($result['address'], 'state'));

            $ret->setCountry(static::getElement($result['address'], 'country'));
            $ret->setCountryCode(static::getElement($result['address'], 'country_code'));

            $ret->setLatitude($result['lat']);
            $ret->setLongitude($result['lon']);

            return $ret;
        }

        public static function getElement(array $array, $key, $default = '')
        {
            if(array_key_exists($key, $array)) {
                return $array[$key];
            }

            return $default;
        }

        public static function getCoords($address, $region = 'FR')
        {
            $address    = urlencode($address);
            $json       = fgc("http://maps.google.com/maps/api/geocode/json?address=$address&sensor=false&region=$region");
            $json       = json_decode($json);
            $lat        = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lat'};
            $long       = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lng'};

            $coords     = new Coords;
            return $coords->setLatitude($lat)->setLongitude($long);
        }

        public static function distance($address1, $address2)
        {
            $coords1 = static::getCoords($address1);
            $coords2 = static::getCoords($address2);

            $lat1 = $coords1['latitude'];
            $lat2 = $coords2['latitude'];

            $lng1 = $coords1['longitude'];
            $lng2 = $coords2['longitude'];

            $pi80 = M_PI / 180;
            $lat1 *= $pi80;
            $lng1 *= $pi80;
            $lat2 *= $pi80;
            $lng2 *= $pi80;

            $earthRadius = 6372.797;
            $dlat = $lat2 - $lat1;
            $dlng = $lng2 - $lng1;
            $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $km = $earthRadius * $c;

            return round($km, 2);
        }

    }

