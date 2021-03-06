<?php
    /**
     * @author Gerald Plusquellec
     */
    namespace Thin;
    class Ftp
    {

        // singleton instance
        private static $instance;

        /**
         * FTP host
         *
         * @var string $_host
         */
        private static $_host;

        /**
         * FTP port
         *
         * @var int $_port
         */
        private static $_port = 21;

        /**
         * FTP password
         *
         * @var string $_pwd
         */
        private static $_pwd;

        /**
         * FTP stream
         *
         * @var resource $_id
         */
        private static $_stream;

        /**
         * FTP timeout
         *
         * @var int $_timeout
         */
        private static $_timeout = 90;

        /**
         * FTP user
         *
         * @var string $_user
         */
        private static $_user;

        /**
         * Last error
         *
         * @var string $error
         */
        public static $error;

        /**
         * FTP passive mode flag
         *
         * @var bool $passive
         */
        public static $passive = false;

        /**
         * SSL-FTP connection flag
         *
         * @var bool $ssl
         */
        public static $ssl = false;

        /**
         * System type of FTP server
         *
         * @var string $systemType
         */
        public static $systemType;

        /**
         * Initialize connection params
         *
         * @param string $host
         * @param string $user
         * @param string $password
         * @param int $port
         * @param int $timeout (seconds)
         */
        public static function forge($host = null, $user = null, $password = null, $port = 21, $timeout = 90)
        {
            static::$_host = $host;
            static::$_user = $user;
            static::$_pwd = $password;
            static::$_port = (int)$port;
            static::$_timeout = (int)$timeout;

            if(!static::$instance) {
                static::$instance = new self();
            }

            return static::$instance;
        }

        /**
         * Auto close connection
         */
        public function  __destruct()
        {
            $this->close();
        }

        /**
         * Change currect directory on FTP server
         *
         * @param string $directory
         * @return bool
         */
        public function cd($directory = null)
        {
            // attempt to change directory
            if(ftp_chdir(static::$_stream, $directory)) {
                // success
                return true;
            // fail
            } else {
                static::$error = "Failed to change directory to \"{$directory}\"";
                return false;
            }
        }

        /**
         * Set file permissions
         *
         * @param int $permissions (ex: 0644)
         * @param string $remoteFile
         * @return false
         */
        public function chmod($permissions = 0, $remoteFile = null)
        {
            // attempt chmod
            if(ftp_chmod(static::$_stream, $permissions, $remoteFile)) {
                // success
                return true;
            // failed
            } else {
                static::$error = "Failed to set file permissions for \"{$remoteFile}\"";
                return false;
            }
        }

        /**
         * Close FTP connection
         */
        public function close()
        {
            // check for valid FTP stream
            if(static::$_stream) {
                // close FTP connection
                ftp_close(static::$_stream);

                // reset stream
                static::$_stream = false;
            }
        }

        /**
         * Connect to FTP server
         *
         * @return bool
         */
        public function connect()
        {
            // check if non-SSL connection
            if(!static::$ssl) {
                // attempt connection
                if(!static::$_stream = ftp_connect(static::$_host, static::$_port, static::$_timeout)) {
                    // set last error
                    static::$error = "Failed to connect to {static::$_host}";
                    return false;
                }
            // SSL connection
            } elseif(function_exists("ftp_ssl_connect")) {
                // attempt SSL connection
                if(!static::$_stream = ftp_ssl_connect(static::$_host, static::$_port, static::$_timeout)) {
                    // set last error
                    static::$error = "Failed to connect to {static::$_host} (SSL connection)";
                    return false;
                }
            // invalid connection type
            } else {
                static::$error = "Failed to connect to {static::$_host} (invalid connection type)";
                return false;
            }

            // attempt login
            if(ftp_login(static::$_stream, static::$_user, static::$_pwd)) {
                // set passive mode
                ftp_pasv(static::$_stream, (bool)static::$passive);

                // set system type
                static::$systemType = ftp_systype(static::$_stream);

                // connection successful
                return true;
            // login failed
            } else {
                static::$error = "Failed to connect to {static::$_host} (login failed)";
                return false;
            }
        }

        /**
         * Delete file on FTP server
         *
         * @param string $remoteFile
         * @return bool
         */
        public function delete($remoteFile = null)
        {
            // attempt to delete file
            if(ftp_delete(static::$_stream, $remoteFile)) {
                // success
                return true;
            // fail
            } else {
                static::$error = "Failed to delete file \"{$remoteFile}\"";
                return false;
            }
        }

        /**
         * Download file from server
         *
         * @param string $remoteFile
         * @param string $localFile
         * @param int $mode
         * @return bool
         */
        public function get($remoteFile = null, $localFile = null, $mode = FTP_ASCII)
        {
            // attempt download
            if(ftp_get(static::$_stream, $localFile, $remoteFile, $mode)) {
                // success
                return true;
            // download failed
            } else {
                static::$error = "Failed to download file \"{$remoteFile}\"";
                return false;
            }
        }

        /**
         * Get list of files/directories in directory
         *
         * @param string $directory
         * @return array
         */
        public function ls($directory = null)
        {
            $list = array();

            // attempt to get list
            if($list = ftp_nlist(static::$_stream, $directory)) {
                // success
                return $list;
            // fail
            } else {
                static::$error = "Failed to get directory list";
                return array();
            }
        }

        /**
         * Create directory on FTP server
         *
         * @param string $directory
         * @return bool
         */
        public function mkdir($directory = null)
        {
            // attempt to create dir
            if(ftp_mkdir(static::$_stream, $directory)) {
                // success
                return true;
            // fail
            } else {
                static::$error = "Failed to create directory \"{$directory}\"";
                return false;
            }
        }

        /**
         * Upload file to server
         *
         * @param string $local_path
         * @param string $remoteFile_path
         * @param int $mode
         * @return bool
         */
        public function put($localFile = null, $remoteFile = null, $mode = FTP_ASCII)
        {
            // attempt to upload file
            if(ftp_put(static::$_stream, $remoteFile, $localFile, $mode)) {
                // success
                return true;
            // upload failed
            } else {
                static::$error = "Failed to upload file \"{$localFile}\"";
                return false;
            }
        }

        /**
         * Get current directory
         *
         * @return string
         */
        public function pwd()
        {
            return ftp_pwd(static::$_stream);
        }

        /**
         * Rename file on FTP server
         *
         * @param string $oldName
         * @param string $newName
         * @return bool
         */
        public function rename($oldName = null, $newName = null)
        {
            // attempt rename
            if(ftp_rename(static::$_stream, $oldName, $newName)) {
                // success
                return true;
            // fail
            } else {
                static::$error = "Failed to rename file \"{$oldName}\"";
                return false;
            }
        }

        /**
         * Remove directory on FTP server
         *
         * @param string $directory
         * @return bool
         */
        public function rmdir($directory = null)
        {
            // attempt remove dir
            if(ftp_rmdir(static::$_stream, $directory)) {
                // success
                return true;
            // fail
            } else {
                static::$error = "Failed to remove directory \"{$directory}\"";
                return false;
            }
        }
    }
