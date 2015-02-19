<?php
	namespace Thin;
	class Thumbnail
	{
		const
			JPEG_QUALITY = 100,
			PNG_COMPRESSION = 0,
			PNG_FILTER = PNG_FILTER_NONE;

		private
			$sourceFile, // full path to the source file
			$savePath, // full save path, including filename
			$thumbnailWidth,
			$thumbnailHeight,
			$condition, // 'always' - resizes even if source image is smaller than a needed thumbnail size; 'ifbigger' - resiszes only if source image is bigger than a needed thumbnail size
			$method,    // 'margin' - if source dimensions ratio is different than thumbnail's, $fillColored margins will be added; 'cut' - 'margin' - if source dimensions ratio is different than thumbnail's, resulting image will be cut
			$fillColor; // array (R, G, B) in decimals

		/**
		 *
		 * Thumbnailer can be configured just with the constructor
		 * @param strung $sourceFile Path to the source file
		 * @param string $savePath Path to a file where to save the thumbnail
		 * @param int $thumbnailWidth Thumbnail width
		 * @param int $thumbnailHeight Thumbnail height
		 * @param array $fillColor
		 */
		public function __construct($sourceFile = null, $savePath = null, array $fillColor = array(255, 255, 255))
		{
			$this->sourceFile = $sourceFile;
			$this->fillColor = $fillColor;
			$this->condition = 'always';
			$this->method = 'margin';
			$this->setSavePath($savePath);
		}

		public function setSourceFile($sourceFilePath)
		{
			$this->sourceFile = $sourceFilePath;
			return $this;
		}

		public function setCondition($condition)
		{
			$this->condition = $condition;
			return $this;
		}

		public function setMethod($method)
		{
			$this->method = $method;
			return $this;
		}

		public function setSavePath($savePath)
		{
			$this->savePath = $savePath;
			return $this;
		}

		public function setFillColor(array $fillColor)
		{
			$this->fillColor = $fillColor;
			return $this;
		}

		/**
		 *
		 * Makes a thumbnail from configured parameters. If no $savePath where specified, the image will be sent straight into the output
		 * @throws \UnexpectedValueException
		 * @throws \RuntimeException
		 */
		public function makeThumbnail($width, $height)
		{
			if (empty($this->sourceFile)) {
				throw new \UnexpectedValueException('Thumbnail: no source file has been provided');
			}

			$imageinfo = getimagesize($this->sourceFile);

			if (preg_match('/jpeg/i', $imageinfo['mime']) || preg_match('/jpg/i', $imageinfo['mime'])) {
				$sourceImage = imagecreatefromjpeg($this->sourceFile);
				$outputFunctionName = 'imagejpeg';

			} else if (preg_match('/png/i', $imageinfo['mime'])) {
				$sourceImage = imagecreatefrompng($this->sourceFile);
				$outputFunctionName = 'imagepng';

			} else if (preg_match('/gif/i', $imageinfo['mime'])) {
				$sourceImage = imagecreatefromgif($this->sourceFile);
				$outputFunctionName = 'imagegif';

			} else {
				throw new \UnexpectedValueException('Thumbnailer: provided file type ('. $imageinfo['mime'] .') is not supported.');
			}

			if (!$sourceImage) {
				throw new \RuntimeException('Thumbnailer: failed loading source image');
			}

			$this->thumbnailWidth = $width;
			$this->thumbnailHeight = $height;

			if ($this->thumbnailWidth < 1 || $this->thumbnailHeight < 1) {
				throw new \UnexpectedValueException('Thumbnailer: width and height could not be less than 1');
			}

			// Если выполняются условия ресайза - то ресайзим
			if (((imagesx($sourceImage) > $this->thumbnailWidth) || (imagesy($sourceImage) > $this->thumbnailHeight)) && ($this->condition == 'ifbigger') || $this->condition == 'always') {
				$destinationImage = imagecreatetruecolor($this->thumbnailWidth, $this->thumbnailHeight);

				imagefill($destinationImage, 0, 0, imagecolorallocate($destinationImage, $this->fillColor[0], $this->fillColor[1], $this->fillColor[2]));

				$sourceRatio = imagesx($sourceImage) / imagesy($sourceImage);
				$destinationRatio = $this->thumbnailWidth / $this->thumbnailHeight;
				$widthToResizeTo = imagesx($sourceImage); // инициализация
				$heightToResizeTo = imagesy($sourceImage);// инициализация
				$xOffset = floor(($this->thumbnailWidth - imagesx($sourceImage)) / 2);
				$yOffset = floor(($this->thumbnailHeight - imagesy($sourceImage)) / 2);

				if ($xOffset < 0) {
					$xOffset = 0;
				}

				if ($yOffset < 0) {
					$yOffset = 0;
				}

				if ($this->method == 'margin') {
					if ((imagesy($sourceImage) >= $this->thumbnailHeight && $sourceRatio <= $destinationRatio) || (imagesx($sourceImage) >= $this->thumbnailWidth && $sourceRatio < $destinationRatio)) {
						$widthToResizeTo = ceil($this->thumbnailHeight * $sourceRatio);
						$heightToResizeTo = $this->thumbnailHeight;
						$xOffset = floor(($this->thumbnailWidth - $widthToResizeTo) / 2);
					}

					else if (imagesx($sourceImage) >= $this->thumbnailWidth && $sourceRatio >= $destinationRatio || (imagesy($sourceImage) >= $this->thumbnailHeight && $sourceRatio > $destinationRatio)) {
						$heightToResizeTo = ceil($this->thumbnailWidth / $sourceRatio);
						$widthToResizeTo = $this->thumbnailWidth;
						$yOffset = floor(($this->thumbnailHeight - $heightToResizeTo) / 2);
					}
				} else if ($this->method == 'cut') {
					if ((imagesy($sourceImage) >= $this->thumbnailHeight && $sourceRatio <= $destinationRatio) || (imagesx($sourceImage) >= $this->thumbnailWidth && $sourceRatio < $destinationRatio)) {
						$heightToResizeTo = ceil($this->thumbnailWidth / $sourceRatio);
						$widthToResizeTo = $this->thumbnailWidth;
						$xOffset = floor(($this->thumbnailWidth - $widthToResizeTo) / 2);
					}

					else if (imagesx($sourceImage) >= $this->thumbnailWidth && $sourceRatio >= $destinationRatio || (imagesy($sourceImage) >= $this->thumbnailHeight && $sourceRatio > $destinationRatio)) {
						$widthToResizeTo = ceil($this->thumbnailHeight * $sourceRatio);
						$heightToResizeTo = $this->thumbnailHeight;
						$yOffset = floor(($this->thumbnailHeight - $heightToResizeTo) / 2);
					}
				}

				imagecopyresampled($destinationImage, $sourceImage, $xOffset, $yOffset, 0, 0, $widthToResizeTo, $heightToResizeTo, imagesx($sourceImage), imagesy($sourceImage));
			} else { // Если условия ресайза не выполняются - ничего не делаем
				$destinationImage = $sourceImage;
			}

			switch ($outputFunctionName) {
				case 'imagejpeg':
					$outputFunctionName($destinationImage, $this->savePath, self::JPEG_QUALITY);
					break;
				case 'imagepng':
					$outputFunctionName($destinationImage, $this->savePath, self::PNG_COMPRESSION, self::PNG_FILTER);
					break;
				case 'imagegif':
					$outputFunctionName($destinationImage, $this->savePath);
					break;
				default:
					throw new \RuntimeException('Thumbnailer: can\'t figure out how to output this kind of file');
					break;
			}
			return $this;
		}
	}
