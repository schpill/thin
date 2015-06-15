<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2015 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */
	namespace Thin;

	class hulu
	{

		//*********************************************************
		// Settings
		//*********************************************************

		/*
		hulu's public access key, you should only need to change this if they change the key
		*/
		public $huluPublicAccessKey = 'uMhXhCmeuaimqMYu4nVsgqQqRyDdI4vIFf4jdRa9vjwkd7h5U7MG9ZUtkc267xB';

		/*
		folder where hulu's sitemap files are stored
		*/
		public $dataFolder = 'video_data'; //no trailing slash

		/*
		format when displaying dates
		*/
		public $dateFormat = 'd-m-Y';

		public $videoID;
		public $error = '';
		public $siteMaps;
		public $embedData;
		public $videoData;
		public $dataFile;
		public $huluResult;

		public $processString;

		/* hulu class initialization
		usage: 	hulu([mixed videoID]);
		params: videoID = video ID for hulu content

		This method is automatically called when the class is initialized.

		returns: void
		*/
		public function __construct($videoID = '')
		{
			$this->videoID = (empty($videoID)) ? $this->videoID : $videoID;
		}

		/* method:	setData
		usage:	setData([mixed videoID][, bool includePage=false][, bool includeHulu=false]);
		params:	videoID = video ID for hulu content
				includePage = include video data from page scraping
				includeHulu = include video data from hulu public sitemaps

		This is the main method to retrieve data from hulu for a specific video. Embed data is
		automatically included. To get additional data, you should set includePage to true. If you
		have downloaded hulu's public sitemaps, you can also get additional data from them by setting
		includeHulu to true. If an error occurs, a description will be set in the error property.

		returns: true on success, false on failure.
		*/
		public function setData($videoID = '', $includePage = false, $includeHulu = false)
		{
			$this->videoID = (empty($videoID)) ? $this->videoID : $videoID;

			if (empty($this->videoID)) {
				$this->error = 'No video ID specified';

				return false;
			}

			$embedRaw = $this->getEmbed();

			if (empty($embedRaw)) {
				$this->error = 'Failed to read embed file - video id ' . $this->videoID . ' probably not valid';

				return false;
			}

			$this->embedData = $this->processEmbedRaw($embedRaw);

			if ($includePage) {
				$pageRaw = $this->getPage();

				if (empty($pageRaw)) {
					$this->error = 'Failed to read page file for video ' . $this->videoID . '<br>';
				}

				$this->processString = $this->getDataChunk($pageRaw);

				if (empty($this->processString)) {
					$this->error .= 'Failed to find data chunk in page for video' . $this->videoID;
				}

				$this->cleanString();

				$this->videoData = json_decode($this->processString,true);
			}

			if ($includeHulu) {
				$this->setHuluResult($this->videoID);
			}

			return true;
		}

		/* method:	cleanString
		usage:	cleanString();
		params:	null

		This method cleans up the internal process string after scraping the hulu page.

		returns: void
		*/
		public function cleanString()
		{
			//get rid of newline holders in values
			$this->processString = str_replace('\\\\\\\\n', ' ', $this->processString);

			//replace newline holders with newline
			$this->processString = str_replace('\n', "\n", $this->processString);

			//match returns extra curly bracket at EOL, get rid of it
			$this->processString = substr($this->processString, 0, -1);

			//convert unicode encoded characters to html entities hex
			$this->processString = preg_replace('/\\\\u([0-9a-fA-F]{4})/', '&#x$1;', $this->processString);

			//get rid of escapes added for javascript
			$this->processString = stripslashes($this->processString);
		}

		/* method:	getEmbed
		usage:	getEmbed();
		params:	null

		This method will get the embed data xml file provided by hulu.

		returns: utf-8 encoded file contents as a string
		*/
		public function getEmbed()
		{
			$return = file_get_contents('http://www.hulu.com/api/oembed.xml?url=http%3A//www.hulu.com/watch/' . $this->videoID);
			$return = utf8_encode($return);

			return $return;
		}

		/* method:	getPage
		usage:	getPage();
		params: null

		This method will get the page from hulu for scraping.

		returns: html page as a string
		*/
		public function getPage()
		{
			$return = file_get_contents('http://www.hulu.com/watch/'.$this->videoID);

			return $return;
		}

		/* method:	processEmbedRaw
		usage:	processEmbedRaw(string embedRaw);
		params:	embedRaw = raw embed xml data retrieved from hulu

		This method process the raw embed xml data.

		returns: array of data from emed xml data
		*/
		public function processEmbedRaw($embedRaw)
		{

			$doc = new DOMDocument('1.0','utf-8');
			$doc->loadXML($embedRaw);

			$return['type'] 					= $doc->getElementsByTagName('type')->item(0)->nodeValue;
			$return['title'] 					= $doc->getElementsByTagName('title')->item(0)->nodeValue;
			$return['air_date'] 				= $doc->getElementsByTagName('air_date')->item(0)->nodeValue;
			$return['duration'] 				= $doc->getElementsByTagName('duration')->item(0)->nodeValue;
			$return['author_name'] 				= $doc->getElementsByTagName('author_name')->item(0)->nodeValue;
			$return['provider_name'] 			= $doc->getElementsByTagName('provider_name')->item(0)->nodeValue;
			$return['provider_url'] 			= $doc->getElementsByTagName('provider_url')->item(0)->nodeValue;
			$return['embed_url'] 				= $doc->getElementsByTagName('embed_url')->item(0)->nodeValue;
			$return['html'] 					= $doc->getElementsByTagName('html')->item(0)->nodeValue;
			$return['width'] 					= $doc->getElementsByTagName('width')->item(0)->nodeValue;
			$return['height'] 					= $doc->getElementsByTagName('height')->item(0)->nodeValue;
			$return['thumbnail_url'] 			= $doc->getElementsByTagName('thumbnail_url')->item(0)->nodeValue;
			$return['thumbnail_width'] 			= $doc->getElementsByTagName('thumbnail_width')->item(0)->nodeValue;
			$return['thumbnail_height'] 		= $doc->getElementsByTagName('thumbnail_height')->item(0)->nodeValue;
			$return['large_thumbnail_url'] 		= $doc->getElementsByTagName('large_thumbnail_url')->item(0)->nodeValue;
			$return['large_thumbnail_width'] 	= $doc->getElementsByTagName('large_thumbnail_width')->item(0)->nodeValue;
			$return['large_thumbnail_height'] 	= $doc->getElementsByTagName('large_thumbnail_height')->item(0)->nodeValue;
			$return['cache_age'] 				= $doc->getElementsByTagName('cache_age')->item(0)->nodeValue;
			$return['version'] 					= $doc->getElementsByTagName('version')->item(0)->nodeValue;

			$doc = null;

			return $return;
		}

		/* method:	getDataChunk
		usage:	getDataChunk(string pageRaw);
		params:	pageRaw = raw html data retrieved from hulu by scraping page

		This method extracts the chunk of the page we want to process for video data.

		returns: extracted part of page
		*/
		public function getDataChunk($pageRaw)
		{

			$pageRaw = str_replace(');', ');' . "\n", $pageRaw);
			preg_match("/_preloadedFastStartVideo = (.+)\);/", $pageRaw, $matches);

			$return = $matches[1];

			return $return;
		}

		/* method:	buildEmbed
		usage:	buildEmbed(int width, int height[, int start][, int end][, int thumb][, embedURL]);
		params:	width = width of embeded video
				height = height of embeded video
				start = playback starting point
				end = playback ending point
				thumb = point in video for thumbnail image
				embedURL = hulu provided url for embed

		This method will let you build a custom embed.

		returns: html iframe for embed
		*/
		public function buildEmbed($width, $height, $start = 0, $end = 0, $thumb = '', $embedURL = '')
		{
			if (empty($embedURL)) {
				if (!empty($this->embedData['embed_url'])) {
					$embedURL = $this->embedData['embed_url'];
				} else {
					$this->error = 'Unable to create embed, missing embed url';
					return null;
				}
			}

			$startPair 	= (empty($start)) ? '' : '&st='.$start;
			$endPair 	= (empty($end)) ? '' : '&et='.$end;
			$thumbPair 	= (empty($thumb)) ? '' : '&it='.$thumb;
			$return		= '<iframe width="' . $width . '" height="' . $height . '" src="' . $embedURL . $startPair . $endPair . $thumbPair . '" frameborder="0" scrolling="no" webkitAllowFullScreen mozallowfullscreen allowfullscreen> </iframe>';

			return $return;
		}

		/* method:	showDate
		usage:	showDate(string dateString[, string format]);
		params:	dateString = string representing the date to convert
				format = format of date to return

		This method formats a date. If the format is not provided, the default
		class property will be used.

		returns: formatted date
		*/
		public function showDate($dateString, $format = '')
		{

			$format 	= empty($format) ? $this->dateFormat : $format;
			$timestamp 	= strtotime($dateString);
			$return 	= date($format, $timestamp);

			return $return;
		}

		/* method:	fetchSiteMaps
		usage:	fetchSiteMaps();
		params:	null

		This method will download and extract the archived public sitemaps available from hulu into
		the data folder. On completion, it will read the first video ID from each file for indexing
		and save the results into a file named lastid.json in the data folder.

		returns: true on success, false on failure
		*/
		public function fetchSiteMaps()
		{
			if (!extension_loaded('zlib')) {
				$this->error = 'zlib extension required to uncompress data files.';
				return false;
			}

			$siteMapsXML = file_get_contents('http://www.hulu.com/pub_sitemap.index.xml?' . $this->huluPublicAccessKey);

			if (empty($siteMapsXML)) {
				$this->error = 'Unable to read remote sitemaps file';
				return false;
			}

			$doc = new \DOMDocument('1.0', 'utf-8');

			if (!$doc->loadXML($siteMapsXML)) {
				$this->error = 'Unable to load sitemaps data';

				return false;
			}

			$siteMaps = $doc->getElementsByTagName('loc');

			foreach ($siteMaps as $node) {
				$filePath = $node->nodeValue;
				preg_match('/(\d*\.xml)/', $filePath, $match);

				$fileName 	= $match[1];
				$fg 		= fopen('compress.zlib://' . $filePath, "r");

				if ($fg) {
					$fp = fopen($this->dataFolder . '/' . $fileName, 'w');

					if ($fp) {
						while (($buffer = fgets($fg)) !== false) {
							fwrite($fp, $buffer);
						}
					} else {
						$this->error .= 'Unable to open local sitemap file ' . $this->dataFolder . '/' . $fileName . '<br>';
					}

					fclose($fg);
					fclose($fp);
				} else {
					$this->error .= 'Unable to open remote sitemap file ' . $filePath . '<br>';
				}
			}

			$this->createLastJSON();

			if (!empty($this->error)) {
				return false;
			} else {
				return true;
			}

		}

		/* method:	createLastJSON
		usage:	createLastJSON();
		params:	null

		This method will read the first video ID from each hulu public sitemap file
		for indexing and save the results into a file named lastid.json in the data folder.

		returns: void
		*/
		public function createLastJSON()
		{
			if (empty($this->dataFile)) {
				$this->datafile = $this->setDataFile();
			}

			foreach ($this->datafile as $fileName) {
				$dataChunk = file_get_contents($this->dataFolder . '/' . $fileName, false, null, -1, 1024);

				list($item, $extension) = explode('.', $fileName);
				preg_match('/watch\/(\d*)/', $dataChunk, $match);

				$lastid[$item] = $match[1];
			}

			$lastJSON = json_encode($lastid);

			file_put_contents($this->dataFolder . '/lastid.json', $lastJSON);

		}

		/* method:	setDataFile
		usage:	setDataFile();
		params:	null

		This method reads the data folder for hulu's public sitemap files.

		returns: array of file names or false on failure
		*/
		public function setDataFile()
		{
			if (is_dir($this->dataFolder)) {
				$handle = opendir($this->dataFolder);

				while (($file = readdir($handle)) !== false) {
					if ($file == '.' || $file == '..' || $file == 'lastid.json') {
						continue;
					}

					$return[] = $file;
				}
			} else {
				$this->error = 'Data folder is incorrect: '.$this->dataFolder;

				return false;
			}

			sort($return, SORT_NATURAL);

			return $return;
		}

		/* method:	getSearchFile
		usage:	getSearchFile(mixed videoID);
		params:	videoID = video ID for hulu content

		This method determines the sitemap file containing the specified video ID.

		returns: filename containing data for specified video
		*/
		public function getSearchFile($videoID)
		{
			$dataJSON = file_get_contents($this->dataFolder . '/lastid.json');
			$lastid = json_decode($dataJSON);

			krsort($lastid);

			foreach ($lastid as $key => $value) {
				if ($videoID > $value) {
					continue;
				} else {
					return $key . '.xml';
				}
			}
		}

		/* method:	setHuluResult
		usage:	setHuluResult(mixed videoID);
		params:	videoID = video ID for hulu content

		This method reads the video data from the sitemap into the huluResult property.

		returns: void
		*/
		public function setHuluResult($videoID)
		{
			global $urlData;

			$fileName = $this->getSearchFile($videoID);

			$doc = new \DOMDocument;
			$doc->preserveWhiteSpace = false;

			$file = realpath($this->dataFolder . '/' . $fileName);
			$doc->load($file);

			$xpath = new \DOMXpath($doc);

			$query = "/*/*/*[text() = 'http://www.hulu.com/watch/".$videoID."']/..";
			$results = $xpath->query($query);

			$node = $results->item(0);

			$this->processNode($node);

			$this->huluResult['loc'] 					= $urlData['url']['loc'];
			$this->huluResult['thumbnail_loc'] 			= $urlData['video:video']['video:thumbnail_loc'];
			$this->huluResult['title'] 					= $urlData['video:video']['video:title'];
			$this->huluResult['description'] 			= $urlData['video:video']['video:description'];
			$this->huluResult['family_friendly'] 		= $urlData['video:video']['video:family_friendly'];
			$this->huluResult['duration'] 				= $urlData['video:video']['video:duration'];
			$this->huluResult['rating'] 				= $urlData['video:video']['video:rating'];
			$this->huluResult['publication_date'] 		= $urlData['video:video']['video:publication_date'];
			$this->huluResult['season_number'] 			= $urlData['video:video']['video:season_number'];
			$this->huluResult['video_type'] 			= $urlData['video:video']['video:video_type'];
			$this->huluResult['air_date'] 				= $urlData['video:video']['video:air_date'];
			$this->huluResult['added_date'] 			= $urlData['video:video']['video:added_date'];
			$this->huluResult['company'] 				= $urlData['video:video']['video:company'];
			$this->huluResult['requires_subscription'] 	= $urlData['video:video']['video:requires_subscription'];
			$this->huluResult['content_id'] 			= $urlData['video:video']['hulu-video:content_id'];
			$this->huluResult['show_id'] 				= $urlData['video:video']['hulu-video:show_id'];
			$this->huluResult['seriesDescription'] 		= $urlData['video:video']['hulu:seriesDescription'];
			$this->huluResult['mediaType'] 				= $urlData['video:video']['hulu:mediaType'];
			$this->huluResult['channel'] 				= $urlData['video:video']['hulu:channel'];
			$this->huluResult['hulu']['season_number'] 	= $urlData['video:video']['hulu-video:season_number'];
			$this->huluResult['hulu']['air_date'] 		= $urlData['video:video']['hulu-video:air_date'];
			$this->huluResult['hulu']['company'] 		= $urlData['video:video']['hulu-video:company'];
			$this->huluResult['show']['show_title'] 	= $urlData['video:tvshow']['video:show_title'];
			$this->huluResult['show']['video_type'] 	= $urlData['video:tvshow']['video:video_type'];
			$this->huluResult['show']['episode_title'] 	= $urlData['video:tvshow']['video:episode_title'];
			$this->huluResult['show']['premier_date'] 	= $urlData['video:tvshow']['video:premier_date'];
		}

		/* method: searchHulu
		usage:	searchHulu(string searchText);
		params:	searchText = keyword to search

		This method searches all sitemap files for the specified keyword and reads the data for
		any matches into the huluResult property.

		returns: void
		*/
		public function searchHulu($searchText)
		{
			global $urlData;

			if (empty($this->dataFile)) {
				$this->dataFile = $this->setDataFile();
			}

			foreach ($this->dataFile as $value) {
				$fileName = $value;
				$doc = new \DOMDocument;
				$doc->preserveWhiteSpace = false;
				$file = realpath($this->dataFolder.'/'.$fileName);
				$doc->load($file);
				$xpath = new \DOMXpath($doc);
				$query = "/*/*[contains(.,' ".$searchText." ')]";
				$results = $xpath->query($query);
				$c = count($this->huluResult);

				foreach ($results as $result) {
					$this->processNode($result);
					$this->huluResult[$c]['loc'] 					= $urlData['url']['loc'];
					$this->huluResult[$c]['thumbnail_loc'] 			= $urlData['video:video']['video:thumbnail_loc'];
					$this->huluResult[$c]['title'] 					= $urlData['video:video']['video:title'];
					$this->huluResult[$c]['description'] 			= $urlData['video:video']['video:description'];
					$this->huluResult[$c]['family_friendly'] 		= $urlData['video:video']['video:family_friendly'];
					$this->huluResult[$c]['duration'] 				= $urlData['video:video']['video:duration'];
					$this->huluResult[$c]['rating'] 				= $urlData['video:video']['video:rating'];
					$this->huluResult[$c]['publication_date'] 		= $urlData['video:video']['video:publication_date'];
					$this->huluResult[$c]['season_number'] 			= $urlData['video:video']['video:season_number'];
					$this->huluResult[$c]['video_type'] 			= $urlData['video:video']['video:video_type'];
					$this->huluResult[$c]['air_date'] 				= $urlData['video:video']['video:air_date'];
					$this->huluResult[$c]['added_date'] 			= $urlData['video:video']['video:added_date'];
					$this->huluResult[$c]['company'] 				= $urlData['video:video']['video:company'];
					$this->huluResult[$c]['requires_subscription'] 	= $urlData['video:video']['video:requires_subscription'];
					$this->huluResult[$c]['content_id'] 			= $urlData['video:video']['hulu-video:content_id'];
					$this->huluResult[$c]['show_id'] 				= $urlData['video:video']['hulu-video:show_id'];
					$this->huluResult[$c]['seriesDescription']	 	= $urlData['video:video']['hulu:seriesDescription'];
					$this->huluResult[$c]['mediaType'] 				= $urlData['video:video']['hulu:mediaType'];
					$this->huluResult[$c]['channel'] 				= $urlData['video:video']['hulu:channel'];
					$this->huluResult[$c]['hulu']['season_number'] 	= $urlData['video:video']['hulu-video:season_number'];
					$this->huluResult[$c]['hulu']['air_date'] 		= $urlData['video:video']['hulu-video:air_date'];
					$this->huluResult[$c]['hulu']['company'] 		= $urlData['video:video']['hulu-video:company'];
					$this->huluResult[$c]['show']['show_title'] 	= $urlData['video:tvshow']['video:show_title'];
					$this->huluResult[$c]['show']['video_type'] 	= $urlData['video:tvshow']['video:video_type'];
					$this->huluResult[$c]['show']['episode_title'] 	= $urlData['video:tvshow']['video:episode_title'];
					$this->huluResult[$c]['show']['premier_date'] 	= $urlData['video:tvshow']['video:premier_date'];
					$c++;
				}

				$doc 	= null;
				$xpath 	= null;
			}
		}

		/* method:	processNode
		usage:	processNode(DOMNode node);
		params:	node = DOM node to process

		This method recursively get all data for chilcren of the specified node.

		returns: void
		*/
		public function processNode($node)
		{
			global $urlData;

			if ($node->hasChildNodes()) {
				$key = $node->nodeName;
				$subNodes = $node->childNodes;

				foreach ($subNodes as $subNode) {
					if ($subNode->nodeType == 1) {
						$urlData[$key][$subNode->nodeName] = $subNode->nodeValue;
					}

					$this->processNode($subNode);
				}
			}
		}
	}
