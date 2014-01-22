<?php
    /**
     * File class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class File
    {
        public static function create($file, $content = null)
        {
            static::delete($file);
            @touch($file);
            if (null !== $content) {
                $fp = fopen($file, 'a');
                fwrite($fp, $content);
                fclose($fp);
            }
        }

        public static function append($file, $data)
        {
            return file_put_contents($file, $data, LOCK_EX | FILE_APPEND);
        }

        public static function exists($file)
        {
            return file_exists($file);
        }

        public static function get($file, $default = null)
        {
            return (static::exists($file)) ? file_get_contents($file) : Utils::value($default);
        }

        public static function put($file, $data, $chmod = 0777)
        {
            $file = file_put_contents($file, $data, LOCK_EX);
        }

        public static function delete($file)
        {
            if (true === static::exists($file)) {
                return @unlink($file);
            }
        }

        public static function move($file, $target)
        {
            return rename($file, $target);
        }

        public static function copy($file, $target)
        {
            return copy($file, $target);
        }

        public static function extension($file)
        {
            return pathinfo($file, PATHINFO_EXTENSION);
        }

        public static function basename($path)
        {
            return pathinfo($path, PATHINFO_BASENAME);
        }

        public static function type($file)
        {
            return filetype($file);
        }

        public static function size($path)
        {
            return filesize($path);
        }

        public static function date($file, $format = "YmDHis")
        {
            return date($format, filemtime($file));
        }

        public static function modified($file)
        {
            return filemtime($file);
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
            $mimes = (null !== config::get('mimes')) ? config::get('mimes') : array();

            if (!Arrays::exists($extension, $mimes)) {
                return $default;
            }

            return (Arrays::isArray($mimes[$extension])) ? Arrays::first($mimes[$extension]) : $mimes[$extension];
        }
    }
