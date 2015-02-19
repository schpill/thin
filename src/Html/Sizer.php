<?php
    /**
     * Sizer class
     * @author          Gerald Plusquellec
     */
    namespace Thin\Html;
    use Thin\File as File;
    use Thin\Html as Html;
    use Thin\Config as Config;
    use Thin\Inflector as Inflector;
    class Sizer
    {
        /**
         * Store the image resource which we'll modify.
         * @var Resource
         */
        private $image;

        /**
         * Original width of the image we're modifying.
         * @var int
         */
        private $width;

        /**
         * Original height of the image we're modifying.
         * @var int
         */
        private $height;

        /**
         * Store the resource of the resized image.
         * @var Resource
         */
        private $imageResized;

        /**
         * Loads config from the main application, falls back to the bundle config.
         * @var array
         */
        private $config = array();

        /**
         * Instantiates the Resizer and receives the path to an image we're working with.
         * @param mixed $file The file array provided by a path to a file
         */
        function __construct($file)
        {
            // Load the config from the main application first, if it's not available
            Config::load('sizer');
            $this->config = Config::get('sizer');

            // Open up the file.
            $this->image = $this->openImage($file);

            if (!$this->image) {
                throw new Exception('File not recognised. Possibly because the path is wrong. Keep in mind, paths are relative to the main index.php file.');
            }

            // Get width and height of our image.
            $this->width  = imagesx($this->image);
            $this->height = imagesy($this->image);
        }

        /**
         * Returns a new Resizer object, allowing for chainable calls.
         * @param  mixed $file The file array provided by a path to a file
         * @return Sizer
         */
        public static function open($file)
        {
            return new Sizer($file);
        }

        /**
         * Resizes and/or crops an image.
         * @param  int    $newWidth  The width of the image
         * @param  int    $newHeight The height of the image
         * @param  string $option     Either exact, portrait, landscape, auto or crop.
         * @return [type]
         */
        public function resize($newWidth, $newHeight, $option = 'auto')
        {
            // Get optimal width and height - based on $option.
            $option_array = $this->get_dimensions($newWidth, $newHeight, $option);

            $optimalWidth  = $option_array['optimalWidth'];
            $optimalHeight = $option_array['optimalHeight'];

            // Resample - create image canvas of x, y size.
            $this->imageResized = imagecreatetruecolor($optimalWidth, $optimalHeight);
            $imageBackground = imagecreatetruecolor($this->width, $this->height);

            // Retain transparency for PNG and GIF files.
            $background_colour = imagecolorallocate(
                $imageBackground,
                arrayGet($this->config, 'background_color.r'),
                arrayGet($this->config, 'background_color.g'),
                arrayGet($this->config, 'background_color.b')
           );

            imagefilledrectangle($imageBackground, 0, 0, $this->width, $this->height, $background_colour);
            imagecopy($imageBackground, $this->image, 0, 0, 0, 0, $this->width, $this->height);
            // imagecolortransparent($this->imageResized, imagecolorallocatealpha($this->imageResized, 255, 255, 255, 127));
            // imagealphablending($this->imageResized, false);
            // imagesavealpha($this->imageResized, true);

            // convert transparency to white when converting from PNG to JPG.
            // PNG to PNG should retain transparency as per normal.
            // imagefill($this->imageResized, 0, 0, IMG_COLOR_TRANSPARENT);

            // Create the new image.
            imagecopyresampled($this->imageResized, $imageBackground, 0, 0, 0, 0, $optimalWidth, $optimalHeight, $this->width, $this->height);

            // if option is 'crop' or 'fit', then crop too.
            if ($option == 'crop' || $option == 'fit') {
                $this->crop($optimalWidth, $optimalHeight, $newWidth, $newHeight);
            }

            // Return $this to allow calls to be chained.
            return $this;
        }

        /**
         * Save the image based on its file type.
         * @param  string $savePath     Where to save the image
         * @param  int    $imageQuality The output quality of the image
         * @return boolean
         */
        public function save($savePath, $imageQuality = 95)
        {
            // If the image wasn't resized, fetch original image.
            if (!$this->imageResized) {
                $this->imageResized = $this->image;
            }

            // Get extension of the output file.
            $extension = Inflector::lower(File::extension($savePath));

            // Create and save an image based on it's extension.
            switch($extension)
            {
                case 'jpg':
                case 'jpeg':
                    if (imagetypes() & IMG_JPG) {
                        imagejpeg($this->imageResized, $savePath, $imageQuality);
                    }
                    break;

                case 'gif':
                    if (imagetypes() & IMG_GIF) {
                        imagegif($this->imageResized, $savePath);
                    }
                    break;

                case 'png':
                    // Scale quality from 0-100 to 0-9.
                    $scaleQuality = round(($imageQuality / 100) * 9);

                    // Invert quality setting as 0 is best, not 9.
                    $invertScaleQuality = 9 - $scaleQuality;

                    if (imagetypes() & IMG_PNG) {
                        imagepng($this->imageResized, $savePath, $invertScaleQuality);
                    }
                    break;

                default:
                    return false;
                    break;
            }

            // Remove the resource for the resized image.
            imagedestroy($this->imageResized);

            return true;
        }

        /**
         * Open a file, detect its mime-type and create an image resrource from it.
         * @param  array $file Attributes of file from the $_FILES array
         * @return mixed
         */
        private function openImage($file)
        {

            // If $file isn't an array, we'll turn it into one.
            if (!is_array($file)) {
                $file_dimensions = getimagesize($file);
                $file = array(
                    'type'      => Inflector::lower($file_dimensions['mime']),
                    'tmp_name'  => $file
               );
            }

            $mime = $file['type'];
            $file_path = $file['tmp_name'];

            // Confirm that the file actually exists.
            if (!file_exists($file_path)) {
                throw new Exception('Could not find file: ' . $file_path . '. It doesn\'t seem to exist.');
            }

            switch ($mime) {
                case 'image/pjpeg': // IE6
                case File::mime('jpg'): $img = @imagecreatefromjpeg($file_path);  break;
                case File::mime('gif'): $img = @imagecreatefromgif($file_path);   break;
                case File::mime('png'): $img = @imagecreatefrompng($file_path);   break;
                default:                $img = false;                            break;
            }

            return $img;
        }

        /**
         * Return the image dimensions based on the option that was chosen.
         * @param  int    $newWidth  The width of the image
         * @param  int    $newHeight The height of the image
         * @param  string $option     Either exact, portrait, landscape, auto or crop.
         * @return array
         */
        private function get_dimensions($newWidth, $newHeight, $option)
        {
            switch ($option) {
                case 'exact':
                    $optimalWidth  = $newWidth;
                    $optimalHeight = $newHeight;
                    break;
                case 'portrait':
                    $optimalWidth  = $this->getSizeByFixedHeight($newHeight);
                    $optimalHeight = $newHeight;
                    break;
                case 'landscape':
                    $optimalWidth  = $newWidth;
                    $optimalHeight = $this->getSizeByFixedWidth($newWidth);
                    break;
                case 'auto':
                    $option_array   = $this->getSizeByAuto($newWidth, $newHeight);
                    $optimalWidth   = $option_array['optimalWidth'];
                    $optimalHeight  = $option_array['optimalHeight'];
                    break;
                case 'fit':
                    $option_array   = $this->getSizeByFit($newWidth, $newHeight);
                    $optimalWidth   = $option_array['optimalWidth'];
                    $optimalHeight  = $option_array['optimalHeight'];
                    break;
                case 'crop':
                    $option_array   = $this->getOptimalCrop($newWidth, $newHeight);
                    $optimalWidth   = $option_array['optimalWidth'];
                    $optimalHeight  = $option_array['optimalHeight'];
                    break;
            }

            return array(
                'optimalWidth'     => $optimalWidth,
                'optimalHeight'    => $optimalHeight
           );
        }

        /**
         * Returns the width based on the image height.
         * @param  int    $newHeight The height of the image
         * @return int
         */
        private function getSizeByFixedHeight($newHeight)
        {
            $ratio      = $this->width / $this->height;
            $newWidth   = $newHeight * $ratio;

            return $newWidth;
        }

        /**
         * Returns the height based on the image width.
         * @param  int    $newWidth The width of the image
         * @return int
         */
        private function getSizeByFixedWidth($newWidth)
        {
            $ratio      = $this->height / $this->width;
            $newHeight  = $newWidth * $ratio;

            return $newHeight;
        }

        /**
         * Checks to see if an image is portrait or landscape and resizes accordingly.
         * @param  int    $newWidth  The width of the image
         * @param  int    $newHeight The height of the image
         * @return array
         */
        private function getSizeByAuto($newWidth, $newHeight)
        {
            // Image to be resized is wider (landscape).
            if ($this->height < $this->width) {
                $optimalWidth  = $newWidth;
                $optimalHeight = $this->getSizeByFixedWidth($newWidth);
            } else if ($this->height > $this->width) {
                $optimalWidth  = $this->getSizeByFixedHeight($newHeight);
                $optimalHeight = $newHeight;
            }  else {
                if ($newHeight < $newWidth)
                {
                    $optimalWidth  = $newWidth;
                    $optimalHeight = $this->getSizeByFixedWidth($newWidth);
                } else if ($newHeight > $newWidth) {
                    $optimalWidth  = $this->getSizeByFixedHeight($newHeight);
                    $optimalHeight = $newHeight;
                } else {
                    // Sqaure being resized to a square.
                    $optimalWidth  = $newWidth;
                    $optimalHeight = $newHeight;
                }
            }

            return array(
                'optimalWidth'     => $optimalWidth,
                'optimalHeight'    => $optimalHeight
           );
        }

        /**
         * Resizes an image so it fits entirely inside the given dimensions.
         * @param  int    $newWidth  The width of the image
         * @param  int    $newHeight The height of the image
         * @return array
         */
        private function getSizeByFit($newWidth, $newHeight)
        {

            $heightRatio   = $this->height / $newHeight;
            $widthRatio    = $this->width /  $newWidth;

            $max = max($heightRatio, $widthRatio);

            return array(
                'optimalWidth'     => $this->width / $max,
                'optimalHeight'    => $this->height / $max,
           );
        }

        /**
         * Attempts to find the best way to crop. Whether crop is based on the
         * image being portrait or landscape.
         * @param  int    $newWidth  The width of the image
         * @param  int    $newHeight The height of the image
         * @return array
         */
        private function getOptimalCrop($newWidth, $newHeight)
        {
            $heightRatio   = $this->height / $newHeight;
            $widthRatio    = $this->width /  $newWidth;

            if ($heightRatio < $widthRatio) {
                $optimalRatio = $heightRatio;
            } else {
                $optimalRatio = $widthRatio;
            }

            $optimalHeight = $this->height / $optimalRatio;
            $optimalWidth  = $this->width  / $optimalRatio;

            return array(
                'optimalWidth'     => $optimalWidth,
                'optimalHeight'    => $optimalHeight
           );
        }

        /**
         * Crops an image from its center.
         * @param  int    $optimalWidth  The width of the image
         * @param  int    $optimalHeight The height of the image
         * @param  int    $newWidth      The new width
         * @param  int    $newHeight     The new height
         * @return true
         */
        private function crop($optimalWidth, $optimalHeight, $newWidth, $newHeight)
        {
            $cropPoints = $this->getCropPoints($optimalWidth, $optimalHeight, $newWidth, $newHeight);

            // Find center - this will be used for the crop.
            $cropStartX = $cropPoints['x'];
            $cropStartY = $cropPoints['y'];

            $crop = $this->imageResized;

            $destOffsetX        = max(0, -$cropStartX);
            $destOffsetY        = max(0, -$cropStartY);
            $cropStartX         = max(0, $cropStartX);
            $cropStartY         = max(0, $cropStartY);
            $destWidth          = min($optimalWidth, $newWidth);
            $destHeight         = min($optimalHeight, $newHeight);

            // Now crop from center to exact requested size.
            $this->imageResized = imagecreatetruecolor($newWidth, $newHeight);

            imagealphablending($crop, true);
            imagealphablending($this->imageResized, false);
            imagesavealpha($this->imageResized, true);

            imagefilledrectangle(
                $this->imageResized,
                0,
                0,
                $newWidth,
                $newHeight,
                imagecolorallocatealpha(
                    $this->imageResized,
                    255,
                    255,
                    255,
                    127
               )
           );

            imagecopyresampled($this->imageResized, $crop, $destOffsetX, $destOffsetY, $cropStartX, $cropStartY, $destWidth, $destHeight, $destWidth, $destHeight);

            return true;
        }

        /**
         * Gets the crop points based on the configuration either set in the file
         * or overridden by user in their own config file, or on the fly.
         * @param  int    $optimalWidth  The width of the image
         * @param  int    $optimalHeight The height of the image
         * @param  int    $newWidth      The new width
         * @param  int    $newHeight     The new height
         * @return array                  Array containing the crop x and y points.
         */
        private function getCropPoints($optimalWidth, $optimalHeight, $newWidth, $newHeight)
        {
            $cropPoints = array();

            $vertical_start = arrayGet($this->config, 'crop_vertical_start_point');
            $horizontal_start = arrayGet($this->config, 'crop_horizontal_start_point');

            // Where is our vertical starting crop point?
            switch ($vertical_start) {
                case 'top':
                    $cropPoints['y'] = 0;
                    break;
                case 'center':
                    $cropPoints['y'] = ($optimalHeight / 2) - ($newHeight / 2);
                    break;
                case 'bottom':
                    $cropPoints['y'] = $optimalHeight - $newHeight;
                    break;

                default:
                    throw new Exception('Unknown value for crop_vertical_start_point: '. $vertical_start .'. Please check config file in the Resizer bundle.');
                    break;
            }

            // Where is our horizontal starting crop point?
            switch ($horizontal_start) {
                case 'left':
                    $cropPoints['x'] = 0;
                    break;
                case 'center':
                    $cropPoints['x'] = ($optimalWidth / 2) - ($newWidth / 2);
                    break;
                case 'right':
                    $cropPoints['x'] = $optimalWidth - $newWidth;
                    break;

                default:
                    throw new Exception('Unknown value for crop_horizontal_start_point: '. $horizontal_start .'. Please check config file in the Resizer bundle.');
                    break;
            }

            return $cropPoints;
        }

    }
