<?php
    /**
     * File class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class File
    {
        public static function create($path, $content = null)
        {
            @unlink($path);
            @touch($path);
            if (null !== $content) {
                $fp = fopen($path, 'a');
                fwrite($fp, $content);
                fclose($fp);
            }
        }

        public static function append($path, $data)
        {
            return file_put_contents($path, $data, LOCK_EX | FILE_APPEND);
        }

        public static function exists($path)
        {
            return file_exists($path);
        }

        public static function get($path, $default = null)
        {
            return (file_exists($path)) ? file_get_contents($path) : Utils::value($default);
        }

        public static function put($path, $data)
        {
            return file_put_contents($path, $data, LOCK_EX);
        }

        public static function delete($path)
        {
            if (static::exists($path)) {
                return @unlink($path);
            }
        }

        public static function move($path, $target)
        {
            return rename($path, $target);
        }

        public static function copy($path, $target)
        {
            return copy($path, $target);
        }

        public static function extension($path)
        {
            return pathinfo($path, PATHINFO_EXTENSION);
        }

        public static function basename($path)
        {
            return pathinfo($path, PATHINFO_BASENAME);
        }

        public static function type($path)
        {
            return filetype($path);
        }

        public static function size($path)
        {
            return filesize($path);
        }

        public static function modified($path)
        {
            return filemtime($path);
        }

        public static function mkdir($path, $chmod = 0777)
        {
            return (!is_dir($path)) ? mkdir($path, $chmod, true) : true;
        }

        public static function mvdir($source, $destination, $options = \FilesystemIterator::SKIP_DOTS)
        {
            return static::cpdir($source, $destination, true, $options);
        }

        public static function cpdir($source, $destination, $delete = false, $options = \FilesystemIterator::SKIP_DOTS)
        {
            if (!is_dir($source)) {
                return false;
            }

            if (!is_dir($destination)) {
                mkdir($destination, 0777, true);
            }

            $items = new \FilesystemIterator($source, $options);

            foreach ($items as $item) {
                $location = $destination . DS . $item->getBasename();

                if ($item->isDir()) {
                    $path = $item->getRealPath();

                    if (!static::cpdir($path, $location, $delete, $options)) {
                        return false;
                    }

                    if ($delete) {
                        @rmdir($item->getRealPath());
                    }
                } else  {
                    if(!copy($item->getRealPath(), $location)) {
                        return false;
                    }

                    if ($delete) {
                        @unlink($item->getRealPath());
                    }
                }
            }

            unset($items);
            if ($delete) {
                @rmdir($source);
            }

            return true;
        }

        public static function rmdir($directory, $preserve = false)
        {
            if (!is_dir($directory)) {
                return;
            }

            $items = new \FilesystemIterator($directory);

            foreach ($items as $item) {
                if ($item->isDir()) {
                    static::rmdir($item->getRealPath());
                } else {
                    @unlink($item->getRealPath());
                }
            }

            unset($items);
            if (!$preserve) {
                @rmdir($directory);
            }
        }

        public static function cleandir($directory)
        {
            return static::rmdir($directory, true);
        }

        public static function latest($directory, $options = \FilesystemIterator::SKIP_DOTS)
        {
            $latest = null;

            $time = 0;

            $items = new \FilesystemIterator($directory, $options);

            foreach ($items as $item) {
                if ($item->getMTime() > $time) {
                    $latest = $item;
                    $time = $item->getMTime();
                }
            }

            return $latest;
        }


        public static function isFileComplete($path, $waitTime)
        {

            // récupération de la taille du fichier
            $sizeBefore = static::size($path);

            // pause
            sleep($waitTime);

            // purge du cache mémoire PHP (car sinon filesize retourne la même valeur qu'à l'appel précédent)
            clearstatcache();

            // récupération de la taille du fichier après
            $size = static::size($path);

            return ($sizeBefore === $size);

        }

        /**
         * Permet de lire le contenu d'un répertoire lorsqu'on a pac accès à la SPL FileSystemIterator (version PHP < 5.3)
         *
         * @param string $path le chemin du répertoire
         * @return array tableau contenant tout le contenu du répertoire
         */
        public static function readdir($path)
        {
            // initialisation variable de retour
            $ret = array();

            // on gère par sécurité la fin du path pour ajouter ou pas le /
            if('/' != substr($path, -1)) {
                $path .= '/';
            }

            // on vérifie que $path est bien un répertoire
            if(is_dir($path)){
                // ouverture du répertoire
                if($dir = opendir($path)) {
                    // on parcours le répertoire
                    while(false !== ($dirElt = readdir($dir))) {
                        $ret[] = $path.$dirElt;
                    }

                    // fermeture du répertoire
                    closedir($dir);
                } else {
                    throw new Exception('error while opening ' . $path);
                }
            }else{
                throw new Exception($path . ' is not a directory');
            }


            return $ret;
        }

        public static function mime($extension, $default = 'application/octet-stream')
        {
            Config::load('mimes');
            $mimes = config::get('mimes');

            if (!ake($extension, $mimes)) {
                return $default;
            }

            return (is_array($mimes[$extension])) ? $mimes[$extension][0] : $mimes[$extension];
        }
    }
