<?php
/* Copyright 2017-2019 Ino Detelić

 - Licensed under the EUPL, Version 1.2 only (the "Licence");
 - You may not use this work except in compliance with the Licence.

 - You may obtain a copy of the Licence at: https://joinup.ec.europa.eu/collection/eupl/eupl-text-11-12

 - Unless required by applicable law or agreed to in writing, software distributed
   under the Licence is distributed on an "AS IS" basis,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 - See the Licence for the specific language governing permissions and limitations under the Licence.
*/

namespace Galaxia;

class App {

    public $version   = '2019';
    public $debug     = false;
    public $minStatus = 2;

    public $dir       = '';
    public $dirLayout = '';
    public $dirLogic  = '';
    public $dirModel  = '';
    public $dirView   = '';
    public $dirImages = '';
    public $dirLog    = '';
    public $dirCache  = '';

    public $requestUri = '';
    public $requestUriNormalized = '';
    public $routes = [];

    public $pageId     = 0;
    public $pageIsRoot = false;
    public $pagesById  = null;
    private $slugsAndRedirectsByType = null;

    public $logic  = '';
    public $view   = '';
    public $layout = 'layout-default';

    public $locale = ['url' => '/', 'long' => 'en_US', 'full' => 'English'];
    public $locales = [
        'en' => ['url' => '/', 'long' => 'en_US', 'full' => 'English'],
    ];
    public $localesInactive = [];

    public $langs    = ['en'];
    public $lang     = 'en';
    public $timeZone = 'Europe/Lisbon';

    public $imageCompressionQuality = 90;


    public function __construct(string $dir) {
        if (!$dir) errorPage(500, 'app initialization 1');
        if (!is_dir($dir)) errorPage(500, 'app initialization 2');
        if (!file_exists($dir . '/config/app.php')) errorPage(500, 'app configuration');

        $this->dir = rtrim($dir, '/') . '/';
        $this->dirLayout = $this->dir . 'src/layouts/';
        $this->dirLogic  = $this->dir . 'src/templates/';
        $this->dirModel  = $this->dir . 'src/models/';
        $this->dirView   = $this->dir . 'src/templates/';
        $this->dirCache  = $this->dir . 'var/cache/';
        $this->dirLog    = $this->dir . 'var/logs/';
        $this->dirImages = $this->dir . 'var/media/images/';

        if (isset($_SERVER['REQUEST_URI'])) {
            $this->requestUri = urldecode($_SERVER['REQUEST_URI'] ?? '');
            $this->requestUriNormalized = urldecode($_SERVER['REQUEST_URI'] ?? '');
            $this->requestUriNormalized = gTranslit($this->requestUriNormalized);
        }
    }




    // locale

    public function addLangPrefix(string $url, string $lang = null) {
        $url = trim($url, '/');
        if ($lang == null) $lang = key($this->locales);
        if ($url == '') return $this->locales[$lang]['url'];
        return h(rtrim($this->locales[$lang]['url'], '/') . '/' . $url);
    }

    public function localeSetupFromUrl(): void {
        if (isset($_SERVER['REQUEST_URI'])) {
            foreach ($this->locales as $lang => $locale) {
                if ($_SERVER['REQUEST_URI'] == $locale['url']) {
                    $this->lang = $lang;
                    break;
                }
                if (substr($_SERVER['REQUEST_URI'], 0, 4) == $locale['url'] . '/') {
                    $this->lang = $lang;
                    break;
                }
            }
        }
        $this->setLang($this->lang);
    }

    public function setLang(string $lang = null): void {
        if ($lang == null || !isset($this->locales[$lang])) $lang = $this->lang;
        $this->lang = $lang;
        $this->locale = $this->locales[$this->lang];
        $this->langs = array_keys($this->locales);
        $key = array_search($this->lang, $this->langs);
        if ($key > 0) {
            unset($this->langs[$key]);
            array_unshift($this->langs, $this->lang);
        }

        setlocale(LC_TIME, $this->locale['long'] . '.UTF-8');
        date_default_timezone_set($this->timeZone);
    }




    // default page, slug, route loading

    function loadPagesById() {
        if ($this->pagesById == null) {
            $this->pagesById = $this->cacheGet('app', 1, 'routing', 'pages', 'byId', function() {
                $app = \Galaxia\Director::app();
                $db = \Galaxia\Director::mysql();
                $pagesById = [];
                $query = querySelect(['page' => ['pageId', 'pageStatus', 'pageSlug_', 'pageTitle_', 'pageType']], $app->langs);
                $query .= 'WHERE pageStatus > 1' . PHP_EOL;
                // ddp($query);
                $stmt = $db->prepare($query);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($data = $result->fetch_assoc()) {
                    if (!isset($app->routes[$data['pageType']])) continue;

                    $pagesById[$data['pageId']]['id']     = $data['pageId'];
                    $pagesById[$data['pageId']]['status'] = $data['pageStatus'];
                    $pagesById[$data['pageId']]['type']   = $data['pageType'];

                    foreach ($app->langs as $lang) {
                        $pagesById[$data['pageId']]['slug'][$lang]  = $data['pageSlug_' . $lang];
                        $pagesById[$data['pageId']]['title'][$lang] = $data['pageTitle_' . $lang];
                        $pagesById[$data['pageId']]['url'][$lang]   = $app->addLangPrefix($data['pageSlug_' . $lang], $lang);
                    }
                }
                $stmt->close();

                return $pagesById;
            });
        }
    }

    function loadPagesByIdDraft() {
        $pagesByIdDraft = $this->cacheGet('app', 1, 'routing', 'draft', 'pagesByIdDraft', function() {
            $app = \Galaxia\Director::app();
            $db = \Galaxia\Director::mysql();

            $pagesByIdDraft = [];
            $query = querySelect(['page' => ['pageId', 'pageStatus', 'pageSlug_', 'pageTitle_', 'pageType']], $app->langs);
            $query .= 'WHERE pageStatus = 1' . PHP_EOL;
            // ddp($query);
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($data = $result->fetch_assoc()) {
                if (!isset($app->routes[$data['pageType']])) continue;

                $pagesByIdDraft[$data['pageId']]['id']     = $data['pageId'];
                $pagesByIdDraft[$data['pageId']]['status'] = $data['pageStatus'];
                $pagesByIdDraft[$data['pageId']]['type']   = $data['pageType'];

                foreach ($app->langs as $lang) {
                    $pagesByIdDraft[$data['pageId']]['slug'][$lang]  = $data['pageSlug_' . $lang];
                    $pagesByIdDraft[$data['pageId']]['title'][$lang] = $data['pageTitle_' . $lang];
                    $pagesByIdDraft[$data['pageId']]['url'][$lang]   = $app->addLangPrefix($data['pageSlug_' . $lang], $lang);
                }
            }
            $stmt->close();

            return $pagesByIdDraft;
        });
        $this->pagesById += $pagesByIdDraft;
    }

    function defaultRoutes(int $pageMinStatus, $cachePostfix, $cacheBypass) {
        $routes = [];
        $routesVisited = [];
        $timerName = 'FastRoute: ' . $cachePostfix;
        Director::timerStart($timerName);

        $slugsAndRedirectsByType = $this->cacheGet('app', 1, 'routing', 'slugsAndRedirectsByType', $cachePostfix, function() use ($pageMinStatus) {
            $app = Director::app();
            $db = Director::mysql();

            $slugs = [];
            $redirects = [];
            $query = querySelect([
                'page'         => ['pageId', 'pageStatus', 'pageSlug_', 'pageType'],
                'pageRedirect' => ['pageRedirectId', 'pageRedirectSlug'],
            ], $app->langs);
            $query .= querySelectLeftJoinUsing(['pageRedirect' => ['pageId']]);
            $query .= 'WHERE pageStatus >= ' . $pageMinStatus . PHP_EOL;
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($data = $result->fetch_assoc()) {
                if (!isset($app->routes[$data['pageType']])) continue;

                foreach ($app->langs as $lang)
                    $slugs[$data['pageType']][$data['pageId']][$lang] = $data['pageSlug_' . $lang];

                if ($data['pageRedirectSlug'])
                    $redirects[$data['pageType']][$data['pageId']][$data['pageRedirectId']] = $data['pageRedirectSlug'];
            }
            $stmt->close();
            return ['slugs' => $slugs, 'redirects' => $redirects];
        }, $cacheBypass);


        // main lang
        foreach ($this->routes as $pageType => $patterns) {
            if (!isset($slugsAndRedirectsByType['slugs'][$pageType])) continue;
            foreach ($patterns as $pattern => $methods) {
                foreach ($slugsAndRedirectsByType['slugs'][$pageType] as $pageId => $page) {
                    foreach ($methods as $routeMethod => $route) {
                        foreach ($page as $lang => $slug) {
                            if ($slug == '') $routeFinal = $this->locales[$lang]['url'] . '{pgSlug:' . $slug . '}' . $pattern;
                            else $routeFinal = (($this->locales[$lang]['url'] == '/') ? '/' : $this->locales[$lang]['url'] . '/') . '{pgSlug:' . $slug . '}' . $pattern;
                            $routeMeta = [
                                'template' => $route['template'],
                                'pageId'   => $pageId,
                                'isRoot'   => empty($pattern),
                                'redirect' => false,
                            ];
                            if (!isset($routesVisited[$routeFinal][$routeMethod])) {
                                $routes[] = ['method' => $routeMethod, 'route' => $routeFinal, 'meta' => $routeMeta];
                                $routesVisited[$routeFinal][$routeMethod] = true;
                            }
                        }
                    }
                }
            }
        }

        // page redirects
        foreach ($this->routes as $pageType => $patterns) {
            if (!isset($slugsAndRedirectsByType['redirects'][$pageType])) continue;
            foreach ($patterns as $pattern => $methods) {
                foreach ($slugsAndRedirectsByType['redirects'][$pageType] as $pageId => $redirect) {
                    foreach ($methods as $routeMethod => $route) {
                        if ($routeMethod != 'GET') continue;
                        foreach ($redirect as $redirectId => $slug) {
                            if (!$slug) continue;
                            foreach ($this->langs as $lang) {
                                $routeFinal = (($this->locales[$lang]['url'] == '/') ? '/' : $this->locales[$lang]['url'] . '/') . '{pgSlug:' . $slug . '}' . $pattern;
                                $routeMeta = [
                                    'template' => $route['template'],
                                    'pageId'   => $pageId,
                                    'isRoot'   => empty($pattern),
                                    'redirect' => $redirectId,
                                ];
                                if (!isset($routesVisited[$routeFinal][$routeMethod])) {
                                    $routes[] = ['method' => $routeMethod, 'route' => $routeFinal, 'meta' => $routeMeta];
                                    $routesVisited[$routeFinal][$routeMethod] = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        // secondary langs
        foreach ($this->routes as $pageType => $patterns) {
            if (!isset($slugsAndRedirectsByType['slugs'][$pageType])) continue;
            foreach ($patterns as $pattern => $methods) {
                foreach ($slugsAndRedirectsByType['slugs'][$pageType] as $pageId => $page) {
                    foreach ($methods as $routeMethod => $route) {
                        if ($routeMethod != 'GET') continue;
                        foreach ($page as $lang => $slug) {
                            foreach ($this->langs as $lang2) {
                                if ($lang2 == $lang) continue;
                                if (!$slug) continue;
                                $routeFinal = (($this->locales[$lang2]['url'] == '/') ? '/' : $this->locales[$lang2]['url'] . '/') . '{pgSlug:' . $slug . '}' . $pattern;
                                $routeMeta = [
                                    'template' => $route['template'],
                                    'pageId'   => $pageId,
                                    'isRoot'   => empty($pattern),
                                    'redirect' => false,
                                ];
                                if (!isset($routesVisited[$routeFinal][$routeMethod])) {
                                    $routes[] = ['method' => $routeMethod, 'route' => $routeFinal, 'meta' => $routeMeta];
                                    $routesVisited[$routeFinal][$routeMethod] = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        Director::timerStop($timerName);
        return $routes;
    }

    public function getIdBySlug($table, $status, $tableSlug, $redirect, $matchSlug, $langs = null) {
        if ($langs === null) $langs = $this->langs;
        $id = null;
        $tableId = $table . 'Id';
        $tableStatus = $table . 'Status';
        $tableRedirect = $table . 'Redirect';
        $tableRedirectId = $table . 'RedirectId';
        $tableRedirectSlug = $table . 'RedirectSlug';

        $params = [];
        $statusGlue = 'WHERE';
        $arraySelect = [$table => [$tableId, $tableSlug]];
        $arraySelectWhereOr = [$table => [$tableSlug => '=']];

        $useLangs = (substr($tableSlug, -1) == '_');
        if (!$useLangs) $langs = ['nolang'];

        $otherLangs = $langs;
        $langCur = array_shift($otherLangs);
        $otherSlugs = [];
        foreach ($otherLangs as $lang) $otherSlugs[$lang] = null;


        // setup query and param

        if ($redirect) {
            $arraySelect[$tableRedirect] = [$tableRedirectSlug];
            $otherSlugs['redirect'] = null;
        }
        if ($status != null) {
            $params[] = ['d' => $status];
            $statusGlue = 'AND';
        }
        foreach ($langs as $lang) $params[] = ['s' => $matchSlug];
        if ($redirect) $params[] = ['s' => $matchSlug];


        // query

        $query = querySelect($arraySelect, $langs);

        if ($redirect)
            $query .= querySelectLeftJoinUsing([$tableRedirect => [$tableId]]);

        if ($status != null)
            $query .= querySelectWhere([$table => [$tableStatus => '>=']]);

        if ($redirect)
            $arraySelectWhereOr[$tableRedirect] = [$tableRedirectSlug => '='];

        $query .= querySelectWhereOr($arraySelectWhereOr, $statusGlue, $langs);


        $db = \Galaxia\Director::mysql();
        $stmt = $db->prepare($query);
        $stmt->bind_param(implode(array_map('key', $params)), ...array_map('reset', $params));
        $stmt->execute();
        $result = $stmt->get_result();

        while ($data = $result->fetch_assoc()) {
            // dp($data);
            if ($data[$tableSlug . $langCur] == $matchSlug) {
                $id = $data[$tableId];
                break;
            }

            foreach ($otherLangs as $lang)
                if ($data[$tableSlug . $lang] == $matchSlug) $otherSlugs[$lang] = $data[$tableId];

            if ($redirect && $data[$tableRedirectSlug] == $matchSlug) $otherSlugs['redirect'] = $data[$tableId];
        }
        $stmt->close();


        if ($id == null) {
            foreach ($otherSlugs as $slugProjectId) {
                if ($slugProjectId != null) {
                    $id = $slugProjectId;
                    break;
                }
            }
        }

        return $id;
    }




    // sitemap

    function generateSitemap($db) {
        $pages = [];
        $query = querySelect(['page' => ['pageSlug_', 'pageType', 'timestampModified']], $this->langs);
        $query .= 'WHERE pageStatus > 1' . PHP_EOL;
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($data = $result->fetch_assoc()) {
            if (!isset($this->routes[$data['pageType']])) continue;
            $pages[$data['pageType']][] = $data;
        }
        $stmt->close();

        if (empty($pages)) {
            devlog('Sitemap not generated.');
            return;
        }

        $n = 0;
        $r = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $r .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . PHP_EOL;

        $found = 0;
        foreach ($this->routes as $pageType => $patterns) {
            foreach ($patterns as $pattern => $methods) {
                if (!isset($pages[$pageType])) continue;
                foreach ($pages[$pageType] as $page) {
                    foreach ($methods as $method => $route) {
                        if ($method != 'GET') continue;
                        if (empty($route['sitemap'])) continue;
                        $sm = $route['sitemap'];
                        arrayLanguifyRemovePerms($sm, $this->langs);
                        if (!isset($sm['priority'])) continue;

                        if (isset($sm['gcSelect'])) {
                            $statusFound = false;
                            foreach ($sm['gcSelect'][key($sm['gcSelect'])] as $fieldName) {
                                if (is_string($fieldName) && substr($fieldName, -6) == 'Status') $statusFound = $fieldName;
                            }
                            $query = querySelect($sm['gcSelect'], $this->langs);
                            $query .= querySelectLeftJoinUsing($sm['gcSelectLJoin'], $this->langs);
                            if ($statusFound) $query .= 'WHERE ' . $statusFound . ' > 1' . PHP_EOL;
                            $query .= querySelectGroupBy($sm['gcSelectGroupBy'], $this->langs);

                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            while ($data = $result->fetch_assoc()) {

                                $subs = [];
                                foreach ($route['sitemap']['loc'] as $col) {
                                    if (substr($col, -5) == 'MONTH' || substr($col, -3) == 'DAY') {
                                        $subs[$col] = str_pad($data[$col], 2, '0', STR_PAD_LEFT);
                                    } else if (substr($col, -1) == '_') {
                                        foreach ($this->locales as $lang => $locale) {
                                            $subs[$col][$lang] = $data[$col . $lang];
                                        }
                                    } else {
                                        $subs[$col] = $data[$col];
                                    }
                                }

                                $subLang = [];
                                foreach ($this->locales as $lang => $locale) {
                                    $subLang[$lang] = '';
                                    foreach ($subs as $col => $data) {
                                        $subLang[$lang] .= '/' . hg($subs, $col, $lang);
                                    }
                                }

                                $r .= '<url>' . PHP_EOL;
                                $r .= '  <priority>' . $sm['priority'] . '</priority>' . PHP_EOL;
                                $r .= '  <loc>' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $this->addLangPrefix($page['pageSlug_' . key($this->locales)] . $subLang[key($this->locales)]) . '</loc>' . PHP_EOL;
                                if (count($this->locales) > 1) {
                                    foreach ($this->locales as $lang => $locale) {
                                        $r .= '  <xhtml:link hreflang="' . $lang . '" href="' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $this->addLangPrefix($page['pageSlug_' . $lang] . $subLang[$lang], $lang) . '" rel="alternate"/>' .PHP_EOL;
                                    }
                                }
                                $r .= '</url>' . PHP_EOL;
                                $found++;
                            }
                            $stmt->close();
                        }

                        if ($pattern == '') {
                            $r .= '<url>' . PHP_EOL;
                            $r .= '  <priority>' . $sm['priority'] . '</priority>' . PHP_EOL;
                            $r .= '  <loc>' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $this->addLangPrefix($page['pageSlug_' . key($this->locales)]) . '</loc>' . PHP_EOL;
                            if (count($this->locales) > 1) {
                                foreach ($this->locales as $lang => $locale) {
                                    $r .= '  <xhtml:link hreflang="' . $lang . '" href="' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $this->addLangPrefix($page['pageSlug_' . $lang], $lang) . '" rel="alternate"/>' .PHP_EOL;
                                }
                            }
                            $r .= '</url>' . PHP_EOL;
                            $found++;
                        }

                    }
                }
            }
        }

        $r .= '</urlset>' . PHP_EOL;

        if ($found > 0) {
            $result = file_put_contents($this->dir . 'public/sitemap.xml', $r);
            if ($result === false) {
                devlog('Sitemap could not be written to file.');
                return;
            }
            if ($result == 0) {
                devlog('Sitemap written with 0 bytes.');
                return;
            }
            devlog(sprintf('Sitemap generated: %d items', $found) . ' <a target="blank" href="/sitemap.xml">' . t('Open in new tab') . '</a>');
        } else {
            devlog('Sitemap not generated, no items found.');
        }
    }









    // images

    public function imageGet($imgSlug, $img = [], $resize = true) {
        $img = array_merge(PROTO_IMAGE, $img);

        if (!$img['ext'] = gImageValid($this->dirImages, $imgSlug)) return;
        $imgDir = $this->dirImages . $imgSlug . '/';
        $imgDirSlug = $imgDir . $imgSlug;
        $file = '';


        // modified time
        $img['mtime'] = filemtime($imgDir);
        if ($img['version'] == 'mtime') $img['version'] = $img['mtime'];


        // file size
        if ($img['fileSize']) $img['fileSize'] = filesize($imgDirSlug . $img['ext']);


        // alt
        foreach ($this->langs as $lang) {
            $file = $imgDirSlug . '_alt_' . $lang . '.txt';
            if (!file_exists($file)) continue;
            $img['alt'][$lang] = file_get_contents($file);
            if (!$img['lang']) $img['lang'] = $lang;
        }


        // extra info from filesystem (type, caption_, etc)
        $img['extra'] = array_flip($img['extra']);
        foreach ($img['extra'] as $extra => $i) {
            $found = false;
            if (substr($extra, -1) == '_') {
                foreach ($this->langs as $lang) {
                    $file = $imgDirSlug . '_' . $extra . $lang . '.txt';
                    if (!file_exists($file)) continue;
                    $img['extra'][$extra][$lang] = file_get_contents($file);
                    $found = true;
                    break;
                }
            } else {
                $file = $imgDirSlug . '_' . $extra . '.txt';
                if (file_exists($file)) {
                    $img['extra'][$extra] = file_get_contents($file);
                    $found = true;
                }
            }
            if (!$found) unset($img['extra'][$extra]);
        }


        // dimensions
        $file = $imgDirSlug . '_dim.txt';
        if (!file_exists($file)) return;
        $dim = explode('x', file_get_contents($file));
        $img['wOriginal'] = (int)$dim[0];
        $img['hOriginal'] = (int)$dim[1];

        $img = array_merge($img, gImageFit($img));
        // ddp($img);

        $ratio = $img['w'] / $img['h'];
        $img['name'] = '/media/images/' . $imgSlug . '/' . $imgSlug;



        // sizes
        if (!in_array(1, $img['sizes'])) $img['sizes'][] = 1;
        arsort($img['sizes']);
        $imagickLoaded = false;
        foreach ($img['sizes'] as $key => $factor) {
            if (!is_numeric($factor) || $factor <= 0) {
                unset($img['sizes'][$key]);
                continue;
            }

            $multiW = (int)round($img['w'] * $factor);
            $multiH = (int)round(($img['w'] / $ratio) * $factor);
            $size   = round($multiW / $img['sizeDivisor']);

            if ($factor != 1 && ($multiW < 128 || $multiW > $img['wOriginal'])) {
                unset($img['sizes'][$key]);
                continue;
            }

            if ($multiW == $img['hOriginal'] && $multiH == $img['wOriginal']) {
                $img['srcset'] .= $img['name'] . $img['ext'] . ' ' . $size . 'w, ';
                if ($factor == 1)
                    $img['src'] = $img['name'] . $img['ext'];
            } else {
                $file = $imgDirSlug . '_' . $multiW . '_' . $multiH . $img['ext'];
                if ($resize && !file_exists($file)) {

                    if (!$imagickLoaded) {
                        try {
                            $imagick = new \Imagick($imgDirSlug . $img['ext']);
                            if ($img['ext'] == '.jpg') {
                                $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                                $imagick->setImageCompressionQuality($this->imageCompressionQuality);
                            }
                            gImagePrepare($imagick);
                        } catch (\ImagickException $e) {
                            unset($img['sizes'][$key]);
                            continue;
                        }
                        $imagickLoaded = true;
                    }

                    $imagickNew = clone($imagick);


                    $lockFile = $this->dirCache . 'flocks/_img_' . $imgSlug . '_' . $multiW . '_' . $multiH . $img['ext'] . '.lock';
                    if (is_dir($this->dirCache . 'flocks/') && $fp = fopen($lockFile, 'w')) {
                        $lockStatus = flock($fp, LOCK_EX | LOCK_NB, $wouldblock);
                        if ($wouldblock) {
                            flock($fp, LOCK_SH);
                        } else {

                            $imagickNew->cropThumbnailImage($multiW, $multiH);
                            $imagickNew->writeImage($imgDirSlug . '_' . $multiW . '_' . $multiH . $img['ext']);
                            touch($imgDir, $img['mtime']);
                            flock($fp, LOCK_UN);
                        }
                        fclose($fp);
                    } else {
                        $imagickNew->cropThumbnailImage($multiW, $multiH);
                        $imagickNew->writeImage($imgDirSlug . '_' . $multiW . '_' . $multiH . $img['ext']);
                        touch($imgDir, $img['mtime']);
                    }



                }
                $img['srcset'] .= $img['name'] . '_' . $multiW . '_' . $multiH . $img['ext'] . ' ' . $size . 'w, ';
                if ($factor == 1) $img['src'] = $img['name'] . '_' . $multiW . '_' . $multiH . $img['ext'];

            }
        }
        // touch($imgDir, $img['mtime']);
        $img['srcset'] = rtrim($img['srcset'], ', ');
        if (count($img['sizes']) < 2) $img['srcset'] = '';
        return $img;
    }



    public function imageUpload(array $files, $replace = false, $toFit = 0) {
        // ddp($files);
        $uploaded = [];
        foreach ($files as $fileNameTemp => $fileNameProposed) {
            $mtime = false;
            $isReplaced = false;
            $isResized = false;
            $isOverwritten = false;
            $fileNameProposed = gNormalize($fileNameProposed, ' ', '.');


            // read image
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer(file_get_contents($fileNameTemp));
            if ($mimeType == 'image/jpeg') {
                $fileExt = '.jpg';
            } else if ($mimeType == 'image/png') {
                $fileExt = '.png';
            } else {
                error('Not uploaded: ' . h($fileNameProposed) . ' - Invalid file type. (accepts jpg and png): ');
                continue;
            }
            try {
                $imagick = new \Imagick($fileNameTemp);
            } catch (\ImagickException $e) {
                error('Error opening image: ' . h($fileNameTemp));
                devlog($e);
                continue;
            }


            // prepare directories
            $fileSlug = $fileSlugInitial = pathinfo($fileNameProposed, PATHINFO_FILENAME);
            $fileSlug = gFormatSlug($fileSlug);
            $fileDir = $this->dirImages . $fileSlug . '/';
            if (is_dir($this->dirImages . $fileSlug)) {
                if ($replace) {
                    $isReplaced = true;
                    $mtime = filemtime($this->dirImages . $fileSlug . '/');
                } else {
                    for ($j = 0; $j < 3; $j++) {
                        if (!is_dir($this->dirImages . $fileSlug)) break;
                        $fileSlug = gFormatSlug('temp' . uniqid() . '-' . $fileSlugInitial);
                        $fileDir = $this->dirImages . $fileSlug . '/';
                    }
                    if (!mkdir($fileDir)) {
                        error('Unable to create directory: ' . h($fileDir));
                        continue;
                    }
                }
            } else {
                if (!mkdir($fileDir)) {
                    error('Unable to create directory: ' . h($fileDir));
                    continue;
                }
            }


            // prepare and resize image
            gImagePrepare($imagick);
            $toFit = (int)$toFit;
            $w = $imagick->getImageWidth();
            $h = $imagick->getImageHeight();
            if ($toFit > 0 && ($w > $toFit || $h > $toFit)) {
                $isResized = true;
                if ($img = gImageFit(['wOriginal' => $w, 'hOriginal' => $h, 'w' => $toFit, 'h' => $toFit, 'fit' => 'cover']))
                    $imagick->cropThumbnailImage($img['w'], $img['h']);
            }


            // write image

            $fileName = $fileDir . $fileSlug . $fileExt;
            if ($fileName != $fileNameTemp) $isOverwritten = true;

            if ($replace) {
                if (file_exists($fileDir . $fileSlug . '.jpg')) unlink($fileDir . $fileSlug . '.jpg');
                if (file_exists($fileDir . $fileSlug . '.png')) unlink($fileDir . $fileSlug . '.png');
                gImageDeleteResizes($this->dirImages, $fileSlug);
            }

            try {
                $imagick->setImageCompressionQuality($this->imageCompressionQuality);
                $imagick->writeImage($fileName);
            } catch (\ImagickException $e) {
                error('Error writing image to file: ' . h($fileNameProposed));
                devlog(print_r($e));
                continue;
            }

            file_put_contents($fileDir . $fileSlug . '_dim.txt', $imagick->getImageWidth() . 'x' . $imagick->getImageHeight());
            $fileNameStripped = pathinfo($fileNameProposed, PATHINFO_FILENAME);


            // finish
            if ($isReplaced) {
                if ($isResized)
                    info('Resized image: ' . h($fileSlug . $fileExt));
                else
                    info('Replaced image: ' . h($fileSlug . $fileExt));

                if ($mtime) {
                    touch($fileName, $mtime);
                    touch($this->dirImages . $fileSlug . '/', $mtime);
                }
            } else {
                info('Uploaded image: ' . h($fileSlug . $fileExt));
            }
            $uploaded[] = [
                'slug'     => $fileSlug,
                'fileName' => $fileNameStripped,
                'ext'      => $fileExt,
                'replaced' => $isReplaced,
            ];
        }

        return $uploaded;
    }




    // caching

    function cacheGet(string $scope, int $level, string $type, string $section, string $key, $function, bool $bypass = false) : array {
        $dir = $this->dirCache . 'app/';
        if ($scope == 'editor') $dir = $this->dirCache . 'editor/';
        if (!is_dir($dir)) mkdir($dir);

        $cacheName = $scope . '-' . $level . '-' . $type . '-' . $section . '-' . $key;
        $cacheFile = $dir . $cacheName . '.cache';
        $timerName = '';


        if (!$bypass && file_exists($cacheFile)) {

            $timerName = 'Load cache: ' . $cacheName;
            \Galaxia\Director::timerStart($timerName);

            $result = include $cacheFile;

        } else {

            $result = null;
            $cacheType = $bypass ? 'BYPASS' : 'NO';
            $timerName = 'Load ' . $cacheType . ' cache: ' . $cacheName;
            \Galaxia\Director::timerStart($timerName);

            $lockFile = $this->dirCache . 'flocks/' . $cacheName . '.lock';
            if (is_dir($this->dirCache . 'flocks/') && !$bypass && $fp = fopen($lockFile, 'w')) {
                $lockStatus = flock($fp, LOCK_EX | LOCK_NB, $wouldblock);
                if ($wouldblock) {
                    flock($fp, LOCK_SH);
                    if (file_exists($cacheFile)) $result = include $cacheFile;
                } else {
                    $result = $function();
                    if (is_array($result)) {
                        file_put_contents($cacheFile, '<?php return ' . var_export($result, true) . ';' . PHP_EOL);
                    } else {
                        error('Cache: unable to load');
                        dp($result);
                    }
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
            } else {
                $result = $function();
            }

        }

        if (!is_array($result)) {
            error('Cache: invalid result');
            dp($result);
        }

        \Galaxia\Director::timerStop($timerName);
        return $result ?? [];
    }


    function cacheDelete($scopes, $type = '*', $section = '*', $key = '*') {
        $dirCacheStrlen = strlen($this->dirCache);
        if (!is_array($scopes)) $scopes = [$scopes];
        if (in_array('editor', $scopes) && !in_array('app', $scopes)) $scopes[] = 'app';
        $files = [];
        foreach ($scopes as $scope) {
            $dir = 'app/';
            if ($scope == 'editor') $dir = 'editor/';

            $cacheName = $scope . '-*-' . $type . '-' . $section . '-' . $key;
            $pattern = $this->dirCache . $dir . $cacheName . '.cache';
            $glob = glob($pattern, GLOB_NOSORT);
            foreach ($glob as $file) {
                if (isset($files[$file])) continue;
                preg_match('~^\w+-(\d+)-~', substr($file, $dirCacheStrlen), $matches);
                $files[$file] = $matches[1] ?? '999';
            }
        }
        asort($files, SORT_NUMERIC);

        $deleted = 0;
        $total = 0;
        foreach ($files as $fileName => $level) {
            if (unlink($fileName)) $deleted++;
            $total++;
        }

        devlog(implode(', ', $scopes) . ': caches deleted: ' . $deleted . '/' . $total);

        $pattern = $this->dirCache . 'editor/list-history-*.cache';
        $glob = glob($pattern, GLOB_NOSORT);
        foreach ($glob as $fileName) unlink($fileName);

        if (is_dir($this->dirCache . 'nginx/')) {
            $glob = glob($this->dirCache . 'nginx/*/*/*');
            $deleted = 0;
            $total = 0;
            foreach ($glob as $fileName) {
                if (unlink($fileName)) $deleted++;
                $total++;
            }
            if ($deleted) devlog('nginx caches deleted: ' . $deleted . '/' . $total);
        }
        if (is_dir($this->dirCache . 'nginxAjax/')) {
            $glob = glob($this->dirCache . 'nginxAjax/*/*/*');
            $deleted = 0;
            $total = 0;
            foreach ($glob as $fileName) {
                if (unlink($fileName)) $deleted++;
                $total++;
            }
            if ($deleted) devlog('nginxAjax caches deleted: ' . $deleted . '/' . $total);
        }
    }


    function cacheDeleteAll() {
        $dirCacheStrlen = strlen($this->dirCache);
        $files = [];

        $glob = glob($this->dirCache . 'app/*.cache', GLOB_NOSORT);
        foreach ($glob as $file) {
            preg_match('~^\w+-(\d+)-~', substr($file, $dirCacheStrlen), $matches);
            $files[$file] = $matches[1] ?? '999';
        }
        $glob = glob($this->dirCache . 'editor/*.cache', GLOB_NOSORT);
        foreach ($glob as $file) {
            preg_match('~^\w+-(\d+)-~', substr($file, $dirCacheStrlen), $matches);
            $files[$file] = $matches[1] ?? '999';
        }

        asort($files, SORT_NUMERIC);

        $deleted = 0;
        $total = 0;
        foreach ($files as $fileName => $level) {
            if (unlink($fileName)) $deleted++;
            $total++;
        }

        devlog('ALL caches deleted: ' . $deleted . '/' . $total);

        if (is_dir($this->dirCache . 'nginx/')) {
            $glob = glob($this->dirCache . 'nginx/*/*/*');
            $deleted = 0;
            $total = 0;
            foreach ($glob as $fileName) {
                if (unlink($fileName)) $deleted++;
                $total++;
            }
            if ($deleted) devlog('nginx caches deleted: ' . $deleted);
        }
        if (is_dir($this->dirCache . 'nginxAjax/')) {
            $glob = glob($this->dirCache . 'nginxAjax/*/*/*');
            $deleted = 0;
            $total = 0;
            foreach ($glob as $fileName) {
                if (unlink($fileName)) $deleted++;
                $total++;
            }
            if ($deleted) devlog('nginxAjax caches deleted: ' . $deleted);
        }
    }


    function cacheDeleteOld() {
        $pattern = $this->dirCache . '*.cache';
        $glob = glob($pattern, GLOB_NOSORT);

        $now = time();
        $old = 60 * 60 * 24 * 3; // 3 days
        $deleted = 0;

        foreach ($glob as $fileName)
            if (is_file($fileName))
                if ($now - filemtime($fileName) >= $old)
                    if (unlink($fileName)) $deleted++;

        devlog('App old caches deleted: ' . $deleted);
    }

}
