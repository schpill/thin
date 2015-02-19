<?php
    /**
     * Blog class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Blog
    {
        public static function getAll()
        {
            $articles = glob(STORAGE_PATH . DS . 'articles' . DS . '*.message', GLOB_NOSORT);

            return $articles;
        }

        public static function getCategories()
        {
            $collection = array();
            $articles = static::getAll();

            foreach ($articles as $tmpArticle) {
                $article = static::getArticle($tmpArticle);
                $cat = $article->getCategory();

                if (!Arrays::exists($cat, $collection)) {
                    $collection[$cat] = 1;
                } else {
                    $collection[$cat]++;
                }
            }

            ksort($collection);

            return $collection;
        }

        public static function getById($id)
        {
            $articles = static::getAll();

            foreach ($articles as $tmpArticle) {
                $tab = explode(DS, $tmpArticle);
                $keyComp = repl('.message', '', end($tab));

                if ($keyComp == $id) {
                    return static::getArticle($tmpArticle);
                }
            }

            return null;
        }

        public static function getNewest($limit = 10)
        {
            $articles = static::getAll();
            $collection = array();

            foreach ($articles as $tmpArticle) {
                $article = static::getArticle($tmpArticle);
                $age = static::fixAge($collection, $article->dateCreate);
                $collection[$age] = $article;
            }

            krsort($collection);

            return array_slice($collection, 0, $limit);
        }

        public static function getByAuthor($author)
        {
            $collection = array();
            $articles = static::getAll();

            foreach ($articles as $tmpArticle) {
                $article        = static::getArticle($tmpArticle);
                $articleAuthor  = $article->getAuthor();

                if ($articleAuthor == $author) {
                    array_push($collection, $article);
                }
            }

            return static::order($collection);
        }

        public static function getByCategory($category)
        {
            $collection = array();
            $articles = static::getAll();

            foreach ($articles as $tmpArticle) {
                $article            = static::getArticle($tmpArticle);
                $articleCategory    = $article->getCategory();

                if ($articleCategory == $category) {
                    array_push($collection, $article);
                }
            }

            return static::order($collection);
        }

        public static function getByMonth($month = null, $year = null)
        {
            if (null === $year) {
                $year = date('Y');
            }

            if (null === $month) {
                $year = date('n');
            }

            $collection = array();
            $articles = static::getAll();

            foreach ($articles as $tmpArticle) {
                $article        = static::getArticle($tmpArticle);
                $articleDate    = $article->dateCreate;
                $articleYear    = date('Y', $articleDate);
                $articleMonth   = date('n', $articleDate);

                if ($articleYear == $year && $articleMonth == $month) {
                    array_push($collection, $article);
                }
            }

            return static::order($collection);
        }

        public static function order($articles)
        {
            $collection = array();

            foreach ($articles as $article) {
                $age = static::fixAge($collection, $article->dateCreate);
                $collection[$age] = $article;
            }

            krsort($collection);

            return $collection;
        }

        private static function fixAge($collection, $age)
        {
            if (isset($collection[$age])) {
                return static::fixAge($collection, $age + 1);
            }

            return $age;
        }

        public static function getByYear($year = null)
        {
            if (null === $year) {
                $year = date('Y');
            }

            $collection = array();
            $articles = static::getAll();

            foreach ($articles as $tmpArticle) {
                $article        = static::getArticle($tmpArticle);
                $articleDate    = $article->dateCreate;
                $articleYear    = date('Y', $articleDate);

                if ($articleYear == $year) {
                    array_push($collection, $article);
                }
            }

            return $collection;
        }

        public static function getByTag($tag)
        {
            $collection = array();
            $articles = static::getAll();

            foreach ($articles as $tmpArticle) {
                $article        = static::getArticle($tmpArticle);
                $articleTags    = $article->getTags();

                if (count($articleTags)) {
                    foreach ($articleTags as $articleTag) {
                        if ($articleTag == $tag) {
                            array_push($collection, $article);
                        }
                    }
                }
            }

            return static::order($collection);
        }

        public static function getArticle($article)
        {
            return unserialize(fgc($article));
        }

        public static function store($flatArticle, $key = null)
        {
            $article = new Container;
            $article->populate($flatArticle);
            $serialize = serialize($article);
            if (is_null($key)) {
                $key = sha1($serialize);
            }

            $file = STORAGE_PATH . DS . 'articles' . DS . $key . '.message';
            File::delete($file);
            File::put($file, $serialize);
            return $key;
        }

        public static function delete($id)
        {
            $articles = static::getAll();
            foreach ($articles as $tmpArticle) {
                $tab = explode(DS, $tmpArticle);
                $keyComp = repl('.message', '', Arrays::last($tab));
                if ($keyComp == $id) {
                    return File::delete($tmpArticle);
                }
            }
            return false;
        }

        public static function disqus($account = 'flogg')
        {
            $js = '<script type="text/javascript">
        var disqus_shortname = \'' . $account . '\';
        (function() {
            var dsq = document.createElement(\'script\'); dsq.type = \'text/javascript\'; dsq.async = true;
            dsq.src = \'//\' + disqus_shortname + \'.disqus.com/embed.js\';
            (document.getElementsByTagName(\'head\')[0] || document.getElementsByTagName(\'body\')[0]).appendChild(dsq);
            var dsq2 = document.createElement(\'script\'); dsq.type = \'text/javascript\'; dsq.async = true;
            dsq2.src = \'//\' + disqus_shortname + \'.disqus.com/count.js\';
            (document.getElementsByTagName(\'head\')[0] || document.getElementsByTagName(\'body\')[0]).appendChild(dsq2);
        })();
    </script>';
            echo $js;
        }

        public static function getMonthName($month)
        {
            switch ($month) {
                case 1:
                    return 'Janvier';
                case 2:
                    return 'Février';
                case 3:
                    return 'Mars';
                case 4:
                    return 'Avril';
                case 5:
                    return 'Mai';
                case 6:
                    return 'Juin';
                case 7:
                    return 'Juillet';
                case 8:
                    return 'Août';
                case 9:
                    return 'Septembre';
                case 10:
                    return 'Octobre';
                case 11:
                    return 'Novembre';
                case 12:
                    return 'Décembre';
            }
        }

        public static function getUrl($article)
        {
            return URLSITE . 'post/'. $article->getCategory() . '/' . sha1(serialize($article)) . '/' . Inflector::slugify($article->getTitle());
        }

        public static function parse($data)
        {
            // Replace [b]...[/b] with <strong>...</strong>
            $matches["/\[b\](.*?)\[\/b\]/is"] = function($match) {
                return Html::b($match[1]);
            };

            // Replace [sup]...[/sup] with <sup>...</sup>
            $matches["/\[sup\](.*?)\[\/sup\]/is"] = function($match) {
                return Html::sup($match[1]);
            };

            // Replace [sub]...[/sub] with <sub>...</sub>
            $matches["/\[sub\](.*?)\[\/sub\]/is"] = function($match) {
                return Html::sub($match[1]);
            };

            // Replace [i]...[/i] with <em>...</em>
            $matches["/\[i\](.*?)\[\/i\]/is"] = function($match) {
                return Html::em($match[1]);
            };

            // Replace [code]...[/code] with <pre><code>...</code></pre>
            $matches["/\[code\](.*?)\[\/code\]/is"] = function($match) {
                return Html::code($match[1]);
            };

            // Replace [quote]...[/quote] with <blockquote><p>...</p></blockquote>
            $matches["/\[quote\](.*?)\[\/quote\]/is"] = function($match) {
                return Html::quote($match[1]);
            };

            // Replace [quote="person"]...[/quote] with <blockquote><p>...</p></blockquote>
            $matches["/\[quote=\"([^\"]+)\"\](.*?)\[\/quote\]/is"] = function($match) {
                return $match[1] . ' wrote: ' . Html::quote($match[2]);
            };

            // Replace [size=30]...[/size] with <span style="font-size:30%">...</span>
            $matches["/\[size=(\d+)\](.*?)\[\/size\]/is"] = function($match) {
                return Html::span($match[2], array('style' => 'font-size: ' . $match[1] . '%;'));
            };

            // Replace [s] with <del>
            $matches["/\[s\](.*?)\[\/s\]/is"] = function($match) {
                return Html::del($match[1]);
            };

            // Replace [u]...[/u] with <span style="text-decoration:underline;">...</span>
            $matches["/\[u\](.*?)\[\/u\]/is"] = function($match) {
                return Html::span($match[1], array('style' => 'text-decoration: underline;'));
            };

            // Replace [center]...[/center] with <div style="text-align:center;">...</div>
            $matches["/\[center\](.*?)\[\/center\]/is"] = function($match) {
                return Html::span($match[1], array('style' => 'display: block; text-align: center;'));
            };

            // Replace [color=somecolor]...[/color] with <span style="color:somecolor">...</span>
            $matches["/\[color=([#a-z0-9]+)\](.*?)\[\/color\]/is"] = function($match) {
                return Html::span($match[2], array('style' => 'color: ' . $match[1] . ';'));
            };

            // Replace [email]...[/email] with <a href="mailto:...">...</a>
            $matches["/\[email\](.*?)\[\/email\]/is"] = function($match) {
                return Html::mailto($match[1], $match[1]);
            };

            // Replace [email=someone@somewhere.com]An e-mail link[/email] with <a href="mailto:someone@somewhere.com">An e-mail link</a>
            $matches["/\[email=(.*?)\](.*?)\[\/email\]/is"] = function($match) {
                return Html::mailto($match[1], $match[2]);
            };

            // Replace [url]...[/url] with <a href="...">...</a>
            $matches["/\[url\](.*?)\[\/url\]/is"] = function($match) {
                return Html::link($match[1], $match[1], array('target' => '_blank'));
            };

            // Replace [url=http://www.google.com/]A link to google[/url] with <a href="http://www.google.com/">A link to google</a>
            $matches["/\[url=(.*?)\](.*?)\[\/url\]/is"] = function($match) {
                return Html::link($match[1], $match[2], array('target' => '_blank'));
            };

            // Replace [img]...[/img] with <img src="..."/>
            $matches["/\[img\](.*?)\[\/img\]/is"] = function($match) {
                return Html::image($match[1]);
            };

            // Replace [list]...[/list] with <ul><li>...</li></ul>
            $matches["/\[list\](.*?)\[\/list\]/is"] = function($match) {
                preg_match_all("/\[\*\]([^\[\*\]]*)/is", $match[1], $items);
                return Html::ul(preg_replace("/[\n\r?]$/", null, $items[1]));
            };

            // Replace [list=1|a]...[/list] with <ul|ol><li>...</li></ul|ol>
            $matches["/\[list=(1|a)\](.*?)\[\/list\]/is"] = function($match) {
                if($match[1] === 'a') {
                    $attr = array('style' => 'list-style-type: lower-alpha');
                }
                preg_match_all("/\[\*\]([^\[\*\]]*)/is", $match[2], $items);
                return Html::ol(preg_replace("/[\n\r?]$/", null, $items[1]), $attr);
            };

            // Replace [youtube]...[/youtube] with <iframe src="..."></iframe>
            $matches["/\[youtube\](?:http?:\/\/)?(?:www\.)?youtu(?:\.be\/|be\.com\/watch\?v=)([A-Z0-9\-_]+)(?:&(.*?))?\[\/youtube\]/i"] = function($match) {
                return Html::iframe('http://www.youtube.com/embed/' . $match[1], array(
                    'class' => 'youtube-player',
                    'type'  => 'text/html',
                    'width' => 640,
                    'height'    => 385,
                    'frameborder'   => 0
                ));
            };
            // Replace everything that has been found
            foreach($matches as $key => $val) {
                $data = preg_replace_callback($key, $val, $data);
            }

            return nl2br(repl('&lt;', '<' , repl('&gt;', '>', repl('&quot;="', '', repl('&quot;', "'", $data)))));
        }

        public static function makeKey()
        {
            $key    = Inflector::quickRandom(9);
            $check  = STORAGE_PATH . DS . 'articles' . DS . $key . '.message';
            if (File::exists($check)) {
                return static::makeKey();
            }
            return $key;
        }

        public static function share()
        {
            $html = '<script type="text/javascript" src="http://apis.google.com/js/plusone.js"></script><div class="span5 alert-info">
            <div style="float:left; width:85px;padding-right:10px; margin:4px 4px 4px 4px;height:30px;">
            <iframe src="http://www.facebook.com/plugins/like.php?locale=fr_FR&amp;href='. urlencode(getUrl()) .'&amp;layout=button_count&amp;show_faces=false&amp;width=85&amp;action=like&amp;font=verdana&amp;colorscheme=light&amp;height=21" allowtransparency="true" style="border:none; overflow:hidden; width:85px; height:21px;" frameborder="0" scrolling="no"></iframe></div>
            <div style="float:left; width:80px;padding-right:10px; margin:4px 4px 4px 4px;height:30px;">
            <g:plusone></g:plusone>
            </div>
            <div style="float:left; width:95px;padding-right:10px; margin:4px 4px 4px 4px;height:30px;">
            <iframe data-twttr-rendered="true" title="Twitter Tweet Button" style="width: 106px; height: 20px;" class="twitter-share-button twitter-count-horizontal" src="http://platform.twitter.com/widgets/tweet_button.1371247185.html#_=1372410415388&amp;count=horizontal&amp;id=twitter-widget-0&amp;lang=en&amp;original_referer='. urlencode(getUrl()) .'&amp;size=m&amp;text=##title##&amp;url='. urlencode(getUrl()) .'&amp;via=florentetgeraldpointca" allowtransparency="true" frameborder="0" scrolling="no"></iframe>
            </div>
            </div><div style="clear:both"></div>';
            return $html;
        }
    }
