<?php
    namespace Thin\Html;
    class Gravatar
    {
        /**
         * The name of the option to set the Gravatar image rating
         * @var string
         */
        const OPTION_RATING        = 'rating';

        /**
         * The name of the option to set the default Gravatar image
         * @var string
         */
        const OPTION_DEFAULT_IMAGE = 'default';

        /**
         * The name of the option holding the email address
         * @var string
         */
        const OPTION_EMAIL         = 'email';

        /**
         * The name of the option controlling whether the HTTP or the HTTPS URL is used
         * @var string
         */
        const OPTION_HTTPS         = 'https';

        /**
         * The name of the option to set the Gravatar image size
         * @var string
         */
        const OPTION_IMAGE_SIZE    = 'size';

        /**
         * Avatar rating: G
         * @var string
         */
        const RATING_G  = 'g';

        /**
         * Avatar rating: PG
         * @var string
         */
        const RATING_PG = 'pg';

        /**
         * Avatar rating: R
         * @var string
         */
        const RATING_R  = 'r';

        /**
         * Avatar rating: X
         * @var string
         */
        const RATING_X  = 'x';

        /**
         * Default Gravatar image: 404 response(no image)
         * @var string
         */
        const DEFAULT_404       = '404';

        /**
         * Default gravatar image: Mystery Man
         * @var string
         */
        const DEFAULT_MM        = 'mm';

        /**
         * Default Gravatar Image: Identicon
         * @var string
         */
        const DEFAULT_IDENTICON = 'identicon';

        /**
         * Default Gravatar image: MonsterID
         * @var string
         */
        const DEFAULT_MONSTERID = 'monsterid';

        /**
         * Default Gravatar image: Wavatar face
         * @var string
         */
        const DEFAULT_WAVATAR   = 'wavatar';

        /**
         * Default Gravatar image: Retro
         * @var string
         */
        const DEFAULT_RETRO     = 'retro';

        /**
         * The regular URL to Gravatar images
         * @var string
         */
        const URL_HTTP  = 'http://www.gravatar.com/avatar/';

        /**
         * The secure URL to Gravatar images
         * @var string
         */
        const URL_HTTPS = 'https://secure.gravatar.com/avatar/';

        /**
         * Gravatar display options
         * @var array
         */
        private $options;

        /**
         * Gravatar image tag attributes
         * @var \Thin\Html\Attributes
         */
        private $attributes;

        /**
         * Initialise properties
         *
         *
         */
        public function __construct()
        {
            $this->attributes = new \Thin\Html\Attributes();
            $this->options = array(
                self::OPTION_EMAIL         => 'example@example.com',
                self::OPTION_DEFAULT_IMAGE => self::DEFAULT_MM,
                self::OPTION_RATING        => self::RATING_G,
                self::OPTION_HTTPS         => (ake('HTTPS', $_SERVER) && $_SERVER['HTTPS'] !== 'off'),
                self::OPTION_IMAGE_SIZE    => 100
            );
        }

        /**
         * Set an option
         *
         * @param string $option Option to set
         * @param mixed $value Option's value
         *
         * @return \Thin\Html\Gravatar Provides a fluent interface
         *
         * @throws Thin\Exception if an invalid option value is provided
         *
         *
         */
        public function setOption($option, $value)
        {
            $option = strval($option);
            switch($option) {
                case self::OPTION_DEFAULT_IMAGE:
                    switch($value) {
                        case self::DEFAULT_404:
                        case self::DEFAULT_IDENTICON:
                        case self::DEFAULT_MM:
                        case self::DEFAULT_MONSTERID:
                        case self::DEFAULT_WAVATAR:
                        case self::DEFAULT_RETRO:
                            $this->options[$option] = $value;
                            break;
                        default:
                            throw new \Thin\Exception(get_class() . ": " . __METHOD__ . ": unknown default image type '{$value}'.");
                    }
                    break;
                case self::OPTION_EMAIL:
                    $this->options[$option] = strval($value);
                    break;
                case self::OPTION_RATING:
                    switch($value) {
                        case self::RATING_G:
                        case self::RATING_PG:
                        case self::RATING_R:
                        case self::RATING_X:
                            $this->options[$option] = $value;
                            break;
                        default:
                            throw new \Thin\Exception(get_class() . ": " . __METHOD__ . ": unknown rating '{$value}'.");
                    }
                    break;
                case self::OPTION_IMAGE_SIZE:
                    $this->options[$option] = intval($value);
                    break;
                case self::OPTION_HTTPS:
                    $this->options[$option] =(boolean) $value;
                    break;
                default:
                    throw new \Thin\Exception(get_class() . ": " . __METHOD__ . ": unknown option '{$option}'.");
            }

            return $this;
        }

        /**
         * Set multiple options
         *
         * @param array $options A keyed array with option=>value pairs
         *
         * @return \Thin\Html\Gravatar Provides a fluent interface
         *
         * @throws Thin\Exception if an invalid parametre is passed
         *
         *
         */
        public function setOptions($options)
        {
            if(!is_array($options)) {
                throw new \Thin\Exception(get_class() . ": " . __METHOD__ . " expects an array as parametre, " . gettype($options) . " given.");
            }

            foreach($options as $option => $value) {
                $this->setOption($option, $value);
            }

            return $this;
        }

        /**
         * Fetch the URL of the gravatar image
         *
         * @param array $options [optional] The email to be used to generate the URL
         * or a keyed array of option=>value pairs, for example:
         * <code>
         * echo $gravatar->getUrl('john@johndoe.com');
         * </code>
         * or
         * <code>
         * echo $gravatar->getUrl([
         *     $gravatar::OPTION_EMAIL      => 'john@johndoe.com',
         *     $gravatar::OPTION_IMAGE_SIZE => 120
         * ]);
         * </code>
         *
         * @return string The URL string
         *
         *
         */
        public function getUrl($options = array())
        {
            if(!is_array($options)) {
                $options = array(self::OPTION_EMAIL => strval($options));
            }
            $options = array_merge($options, $this->options);

            $url = $options[self::OPTION_HTTPS] ? self::URL_HTTPS : self::URL_HTTP;
            $url .= md5(\Thin\Inflector::lower(trim($options[self::OPTION_EMAIL])));
            $url .= "?s={$options[self::OPTION_IMAGE_SIZE]}";
            $url .= "&d={$options[self::OPTION_DEFAULT_IMAGE]}";
            $url .= "&r={$options[self::OPTION_RATING]}";

            return $url;
        }

        /**
         * Render the gravatar image HTML
         *
         * @param array $options [optional] The email to be used to generate the URL
         * or a keyed array of option=>value pairs, for example:
         * <code>
         * echo $gravatar->render('john@johndoe.com');
         * </code>
         * or
         * <code>
         * echo $gravatar->render([
         *     $gravatar::OPTION_EMAIL      => 'john@johndoe.com',
         *     $gravatar::OPTION_IMAGE_SIZE => 120
         * ]);
         * </code>
         *
         * @return string The img HTML tag containing the gravatar image
         *
         *
         */
        public function render($options = array())
        {
            // check if the input is an array, and if not, assume it's an email address
            if(!\Thin\Arrays::isArray($options)) {
                $options = [self::OPTION_EMAIL => strval($options)];
            }
            $options = $options + $this->options;

            // set HTML attributes
            $attributes = new \Thin\Html\Attributes();
            $attributes
                ->set($this->attributes)
                ->set('src', $this->getUrl($options))
                ->set('width', $options[self::OPTION_IMAGE_SIZE])
                ->set('height', $options[self::OPTION_IMAGE_SIZE]);

            // build the HTML output and return it
            return "<img {$attributes} />";
        }

        /**
         * Convert the object to string
         *
         * @return string
         *
         *
         */
        public function __toString()
        {
            return $this->render();
        }

        /**
         * Fetch the helper's HTML attributes as an attributes object
         *
         * @return \Thin\Html\Attributes
         *
         *
         */
        public function attr()
        {
            return $this->attributes;
        }

        /**
         * Sets a single HTML attribute
         *
         * @param string       $name  The attribute's name
         * @param array|string $value The attribute's value
         *
         * @return \Thin\Html\Gravatar Provides a fluent interface
         *
         *
         */
        public function setAttr($name, $value)
        {
            $this->attributes->set($name, $value);
            return $this;
        }

        /**
         * Adds an additional value to an HTML attribute
         *
         * @param string $name  The attribute's name
         * @param string $value The attribute's value
         *
         * @return \Thin\Html\Gravatar Provides a fluent interface
         *
         *
         */
        public function addAttr($name, $value)
        {
            $this->attributes->add($name, $value);
            return $this;
        }

        /**
         * Remove an HTML attribute
         *
         * @param string $name The attribute's name
         *
         * @return \Thin\Html\Gravatar Provides a fluent interface
         *
         *
         */
        public function removeAttr($name)
        {
            $this->attributes->remove($name);
            return $this;
        }
}
