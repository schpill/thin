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
            $articles = glob(STORAGE_PATH . DS . 'articles' . DS . '*.message');
            return $articles;
        }

        public static function getCategories()
        {
            $collection = array();
            $articles = static::getAll();
            foreach ($articles as $tmpArticle) {
                $article = static::getArticle($tmpArticle);
                $cat = $article->getCategory();
                if (!ake($cat, $collection)) {
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
            return $collection;
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

        public static function store($flatArticle)
        {
            $article = new Article;
            $article->populate($flatArticle);
            $serialize = serialize($article);
            $key = sha1($serialize);

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
                $keyComp = repl('.message', '', end($tab));
                if ($keyComp == $id) {
                    return File::delete($tmpArticle);
                }
            }
            return false;
        }

        public static function disqus($account = 'flogg')
        {
            $js = '<div id="disqus_thread"></div>
    <script type="text/javascript">
        var disqus_shortname = \'' . $account . '\';
        (function() {
            var dsq = document.createElement(\'script\'); dsq.type = \'text/javascript\'; dsq.async = true;
            dsq.src = \'//\' + disqus_shortname + \'.disqus.com/embed.js\';
            (document.getElementsByTagName(\'head\')[0] || document.getElementsByTagName(\'body\')[0]).appendChild(dsq);
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
    }
