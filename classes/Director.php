<?php
/* Copyright 2019 Ino Detelić

 - Licensed under the EUPL, Version 1.2 only (the "Licence");
 - You may not use this work except in compliance with the Licence.

 - You may obtain a copy of the Licence at: https://joinup.ec.europa.eu/collection/eupl/eupl-text-11-12

 - Unless required by applicable law or agreed to in writing, software distributed
   under the Licence is distributed on an "AS IS" basis,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 - See the Licence for the specific language governing permissions and limitations under the Licence.
*/

namespace Galaxia;
use Galaxia\{App, Editor};


class Director {

    static $app    = null;
    static $editor = null;
    static $me     = null;
    static $mysql  = null;

    static $transliterator      = null;
    static $transliteratorLower = null;
    static $intlDateFormatters  = [];

    static $translations = [];
    static $mysqlConfig = [
        'host'   => '127.0.0.1',
        'db'     => '',
        'user'   => '',
        'pass'   => '',
        'tz'     => 'Europe/Lisbon',
        'locale' => 'en_US',
    ];
    static $pDefault = [
        'id'      => '', // current subpage or page id
        'type'    => 'default',
        'status'  => 1,
        'url'     => [],
        'slug'    => [],
        'title'   => [],
        'noindex' => false,
        'ogImage' => '',
    ];
    static $nofollowHosts = ['facebook', 'google', 'instagram', 'twitter', 'linkedin', 'youtube'];
    static $nginxCacheBypassCookie = 'galaxiaCacheBypass_j49c9e0merhvjd0';

    static $ajax = false;

    // debug
    static $debug = false;
    private static $timers      = [];
    private static $timerLevel  = 0;
    private static $timerMaxLen = 0;
    private static $timerMaxLev = 0;




    static function init() {
        libxml_use_internal_errors(true);
        if (self::$app) errorPage(500, 'director initialization ' . __LINE__);

        self::timerStart('app total', $_SERVER['REQUEST_TIME_FLOAT']);

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'))
            self::$ajax = true;

        ini_set('display_errors', '0');
        // if (php_sapi_name() == 'cli' || (isset($_COOKIE) && isset($_COOKIE['debug']) && $_COOKIE['debug'] == '3489jwmpr0j5s0g5gs984p')) {
            self::$debug = true;
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        // }
    }




    static function app() : App {
        if (!self::$app) errorPage(500, 'director initialization ' . __LINE__);
        return self::$app;
    }




    static function editor() : Editor {
        if (!self::$editor) errorPage(500, 'editor initialization ' . __LINE__);
        return self::$editor;
    }




    static function mysql() : \mysqli {
        if (!self::$mysql) {
            self::timerStart('DB Connection');

            if (!file_exists(self::$app->dir . 'config/mysql.php')) errorPage(500, 'director db configuration ' . __LINE__);
            self::$mysqlConfig = array_merge(self::$mysqlConfig, include self::$app->dir . 'config/mysql.php');

            self::$mysql = new \mysqli(self::$mysqlConfig['host'], self::$mysqlConfig['user'], self::$mysqlConfig['pass'], self::$mysqlConfig['db']);
            if (self::$mysql->connect_errno) {
                echo '<pre>'; var_dump(get_included_files(), self::$mysqlConfig);
                errorPage(500, 'Connection Failed: ' . self::$mysql->connect_errno);
            }
            if (self::$debug) mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            self::$mysql->set_charset('utf8mb4');
            self::$mysql->query('SET time_zone = ' . q(self::$mysqlConfig['tz']) . ';');
            self::$mysql->query('SET lc_time_names = ' . q(self::$mysqlConfig['locale']) . ';');

            self::timerStop('DB Connection');
        };
        return self::$mysql;
    }




    static function loadTranslations() {
        self::timerStart('Translations');

        if (self::$editor && file_exists(self::$editor->dir . 'resources/stringTranslations.php'))
            self::$translations = array_merge(self::$translations, include (self::$editor->dir . 'resources/stringTranslations.php'));

        if (self::$app && file_exists(self::$app->dir . 'resources/stringTranslations.php'))
            self::$translations = array_merge(self::$translations, include (self::$app->dir . 'resources/stringTranslations.php'));

        self::timerStop('Translations');
    }




    static function getTransliteratorLower() {
        if (self::$transliteratorLower == null) {
            self::$transliteratorLower = \Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;');
        }
        return self::$transliteratorLower;
    }




    static function getTransliterator() {
        if (self::$transliterator == null) {
            self::$transliterator = \Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
        }
        return self::$transliterator;
    }




    static function getIntlDateFormatter($pattern, $lang) {
        if (!isset(self::$intlDateFormatters[$pattern][$lang])) {
            self::$intlDateFormatters[$pattern][$lang] = new \IntlDateFormatter(
                $lang,                         // locale
                \IntlDateFormatter::FULL,      // datetype
                \IntlDateFormatter::NONE,      // timetype
                null,                          // timezone
                \IntlDateFormatter::GREGORIAN, // calendar
                $pattern                       // pattern
            );
        }
        return self::$intlDateFormatters[$pattern][$lang];
    }




    // timing

    static function timerStart($timerName, $timeFloat = null) {
        // if (!self::$debug) return;
        if (isset(self::$timers[$timerName])) {
            if (self::$timers[$timerName]['running']) return;
            self::$timers[$timerName]['lap'] = microtime(true);
            self::$timers[$timerName]['running'] = true;
        } else {
            self::$timerLevel++;
            self::$timers[$timerName] = [
                'start'   => $timeFloat ?? microtime(true),
                'end'     => 0,
                'level'   => self::$timerLevel,
                'running' => true,
                'total'   => 0,
                'lap'     => $timeFloat ?? 0,
                'count'   => 0,
            ];
            self::$timers[$timerName]['lap'] = self::$timers[$timerName]['start'];
            self::$timerMaxLen = max(self::$timerMaxLen, (self::$timerLevel * 2) + strlen($timerName));
            self::$timerMaxLev = max(self::$timerMaxLev, self::$timerLevel);
        }
    }

    static function timerStop($timerName) {
        // if (!self::$debug) return;
        if (!isset(self::$timers[$timerName])) return;
        if (!self::$timers[$timerName]['running']) return;

        if (self::$timerLevel > 0) self::$timerLevel--;
        self::$timers[$timerName]['end'] = microtime(true);
        self::$timers[$timerName]['total'] += self::$timers[$timerName]['end'] - self::$timers[$timerName]['lap'];
        self::$timers[$timerName]['running'] = false;
        self::$timers[$timerName]['lap'] = 0;
        self::$timers[$timerName]['count']++;
    }

    static function timerPrint($comments = false) {
        // if (!self::$debug) return;
        $timeEnd = microtime(true);
        self::$timers['app total']['end'] = microtime(true);
        self::$timers['app total']['total'] += self::$timers['app total']['end'] - self::$timers['app total']['lap'];
        self::$timers['app total']['running'] = false;
        self::$timers['app total']['lap'] = 0;
        self::$timers['app total']['count']++;

        $r = '';
        $prefix = '';
        $postfix = '' . PHP_EOL;
        if ($comments) {
            $prefix = '<!-- ';
            $postfix = ' -->' . PHP_EOL;
        }

        $timeTotal = self::$timers['app total']['total'];
        $levelPrev = 0;
        $levelTotals = [$timeTotal];
        $pad = '.';
        $r .= $prefix .
            '..... start' .
            ' .. #' .
            ' .... total' .
            ' . %tot' .
            ' ..' . str_repeat(' .%', self::$timerMaxLev - 1) .
            ' .' . $postfix;

        foreach (self::$timers as $timerName => $time) {

            $percentOfParent = '';
            $levelTotals[$time['level']] = $time['total'];

            if ($time['level'] > 0) {
                $divisor = $levelTotals[$time['level'] - 1];
                if ($divisor == 0) $divisor = 1;;
                $percentOfParent = (($time['total'] * 100) / $divisor);
                $percentOfParent = number_format($percentOfParent, 0, '.', ' ');
            }

            if ($percentOfParent > 99) $percentOfParent = 99;
            $levelPrev = $time['level'];

            $r .= $prefix . str_pad(' ' . number_format(($time['start'] - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 3, '.', ' '), 11, $pad, STR_PAD_LEFT) . ' ';
            $r .= str_pad(' ' . $time['count'], 4, $pad, STR_PAD_LEFT) . ' ';

            if ($time['running']) {
                $r .= str_pad(' ' . number_format(($timeEnd - $time['start']) * 1000, 2, '.', ' '), 10, $pad, STR_PAD_LEFT) . ' ';
            } else {
                $r .= str_pad(' ' . number_format($time['total'] * 1000, 2, '.', ' '), 10, $pad, STR_PAD_LEFT) . ' ';
            }

            $r .= str_pad(number_format((($time['total'] * 100) / $timeTotal), 2, '.', ' '), 6, $pad, STR_PAD_LEFT) . ' ';

            if ($time['level'] > 1) {
                $r .= str_repeat('.. ', $time['level'] - 1) . str_pad($percentOfParent, 2, '.', STR_PAD_LEFT) . ' ';
            } else {
                $r .= '.. ';
            }

            $r .= str_repeat('.. ', self::$timerMaxLev - $time['level']);

            if ($time['running']) {
                $r .= str_pad(str_repeat($pad . $pad, $time['level'] - 1) . ' ' . $timerName, self::$timerMaxLen + 1, ' ', STR_PAD_RIGHT) . $postfix;
            } else {
                $r .= str_pad(str_repeat($pad . $pad, $time['level'] - 1) . (($time['level'] > 0) ? ' ' : '') . $timerName, self::$timerMaxLen + 1, ' ', STR_PAD_RIGHT) . $postfix;
            }

        }
        echo $r;
    }


    // shutdown functions

    static function cliShutdown() {
        if (haserror()) {
            echo '🍎 errors: ' . PHP_EOL;
            foreach (errors() as $key => $msgs) {
                echo '    ' . escapeshellcmd($key) . PHP_EOL;
                foreach ($msgs as $msg) {
                    echo '        ' . escapeshellcmd($msg) . PHP_EOL;
                }
            }
        }
        if (haswarning()) {
            echo '🍋 warnings: ' . PHP_EOL;
            foreach (warnings() as $key => $msgs) {
                echo '    ' . escapeshellcmd($key) . PHP_EOL;
                foreach ($msgs as $msg) {
                    echo '        ' . escapeshellcmd($msg) . PHP_EOL;
                }
            }
        }
        if (hasinfo()) {
            echo '🍐 infos: ' . PHP_EOL;
            foreach (infos() as $key => $msgs) {
                echo '    ' . escapeshellcmd($key) . PHP_EOL;
                foreach ($msgs as $msg) {
                    echo '        ' . escapeshellcmd($msg) . PHP_EOL;
                }
            }
        }
        if (hasdevlog()) {
            echo '🥔 devlogs: ' . PHP_EOL;
            foreach (devlogs() as $key => $msgs) {
                echo '    ' . escapeshellcmd($key) . PHP_EOL;
                foreach ($msgs as $msg) {
                    echo '        ' . escapeshellcmd($msg) . PHP_EOL;
                }
            }
        }
        Director::timerPrint();
    }

}
