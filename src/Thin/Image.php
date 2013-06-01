<?php
    /**
     * Image class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Image
    {
        protected static $_adapterOptions = array(
            'preserveAlpha' => true,
            'alphaMaskColor' => array(
                255,
                255,
                255
            ),
            'preserveTransparency' => true,
            'transparencyMaskColor' => array(
                0,
                0,
                0
            ),
            'resizeUp' => true
        );

        /**
        * @param string $adapterClass
        * @param array $adapterOptions
        * @return FTV_Image_Adapter_Abstract
        */
        public static function factory($adapterClass = null, $adapterOptions = null)
        {
            if (null === $adapterClass) {
                if (extension_loaded('gd')) {
                    $adapterClass = 'Image\\Adapter\\Gd';
                } else {
                    /* TODO */
                    $adapterClass = 'Image\\Adapter\\ImageMagick';
                }
            }

            if (null === $adapterOptions) {
                $adapterOptions = static::$_adapterOptions;
            }

            return new $adapterClass($adapterOptions);
        }
    }
