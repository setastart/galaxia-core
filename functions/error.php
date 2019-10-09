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

function errorPage(int $errorCode, string $msg = '') {
    $errors = [
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error',
    ];
    if (!in_array($errorCode, [403, 404, 500])) $errorCode = 500;
    http_response_code($errorCode);

    $errorFile = '';
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $errorFile = $_SERVER['DOCUMENT_ROOT'] . '/' . $errorCode . '.html';
    }
    if ($errorFile && file_exists($errorFile)) {
        include $errorFile;
    } else {
        $title = 'Error: ' . $errorCode . ' - ' . $errors[$errorCode];
        echo '<!doctype html><meta charset=utf-8><title>' . $title . '</title><body style="font-family: monospace;"><p style="font-size: 1.3em; margin-top: 4em; text-align: center;">' . $title . '</p>' . PHP_EOL;
    }

    if ($msg) echo '<!-- Error: ' . $msg . ' -->' . PHP_EOL;
    $includes = get_included_files();
    foreach ($includes as $include) {
        $include = preg_replace('~^' . dirname(dirname(dirname(__DIR__))) . '/~m', '', $include);
        echo '<!-- ' . $include . ' -->' . PHP_EOL;
    }
    exit();
}
function redirect($location = '', int $code = 303) {
    $location = trim($location);
    if (headers_sent()) {
        echo 'headers already sent. redirect: <a href="' . h($location) . '">' . h($location) . '</a>' . PHP_EOL;
        exit();
    }
    $location = ltrim(h($location), '/');
    header('Location: /' . $location, true, $code);
    exit();
}





// session messages

function msgBoxes($type, $arrayIndex = false) {
    $key = $type . 's';
    $domain = $type . 'Box';
    if ($arrayIndex !== false) return (isset($_SESSION[$key][$domain][$arrayIndex])) ? $_SESSION[$key][$domain][$arrayIndex] : [];
    return (isset($_SESSION[$key][$domain])) ? $_SESSION[$key][$domain] : [];
}




function error($msg, $domain = 'errorBox', $arrayIndex = false) {
    if ($arrayIndex !== false) {
        $_SESSION['errors'][$domain][$arrayIndex][] = $msg;
    } else {
        $_SESSION['errors'][$domain][] = $msg;
    }
}
function hasError($domain = null, $arrayIndex = false) {
    if (isset($domain)) {
        if ($arrayIndex !== false) return (isset($_SESSION['errors'][$domain][$arrayIndex]));
        return (isset($_SESSION['errors'][$domain]));
    } else {
        return (isset($_SESSION['errors']));
    }
}
function errors($domain = null, $arrayIndex = false) {
    if (isset($domain)) {
        if ($arrayIndex !== false) return (isset($_SESSION['errors'][$domain][$arrayIndex])) ? $_SESSION['errors'][$domain][$arrayIndex] : [];
        return (isset($_SESSION['errors'][$domain])) ? $_SESSION['errors'][$domain] : [];
    } else {
        return (isset($_SESSION['errors'])) ? $_SESSION['errors'] : [];
    }
}




function warning($msg, $domain = 'warningBox', $arrayIndex = false) {
    if ($arrayIndex !== false) {
        $_SESSION['warnings'][$domain][$arrayIndex][] = $msg;
    } else {
        $_SESSION['warnings'][$domain][] = $msg;
    }
}
function hasWarning($domain = null, $arrayIndex = false) {
    if (isset($domain)) {
        if ($arrayIndex !== false) return (isset($_SESSION['warnings'][$domain][$arrayIndex]));
        return (isset($_SESSION['warnings'][$domain]));
    } else {
        return (isset($_SESSION['warnings']));
    }
}
function warnings($domain = null, $arrayIndex = false) {
    if (isset($domain)) {
        if ($arrayIndex !== false) return (isset($_SESSION['warnings'][$domain][$arrayIndex])) ? $_SESSION['warnings'][$domain][$arrayIndex] : [];
        return (isset($_SESSION['warnings'][$domain])) ? $_SESSION['warnings'][$domain] : [];
    } else {
        return (isset($_SESSION['warnings'])) ? $_SESSION['warnings'] : [];
    }
}




function info($msg, $domain = 'infoBox', $arrayIndex = false) {
    if ($arrayIndex !== false) {
        $_SESSION['infos'][$domain][$arrayIndex][] = $msg;
    } else {
        $_SESSION['infos'][$domain][] = $msg;
    }
}
function hasInfo($domain = null, $arrayIndex = false) {
    if (isset($domain)) {
        if ($arrayIndex !== false) return (isset($_SESSION['infos'][$domain][$arrayIndex]));
        return (isset($_SESSION['infos'][$domain]));
    } else {
        return (isset($_SESSION['infos']));
    }
}
function infos($domain = null, $arrayIndex = false) {
    if (isset($domain)) {
        if ($arrayIndex !== false) return (isset($_SESSION['infos'][$domain][$arrayIndex])) ? $_SESSION['infos'][$domain][$arrayIndex] : [];
        return (isset($_SESSION['infos'][$domain])) ? $_SESSION['infos'][$domain] : [];
    } else {
        return (isset($_SESSION['infos'])) ? $_SESSION['infos'] : [];
    }
}




function devlog($msg, $domain = 'devlogBox', $arrayIndex = false) {
    if ($arrayIndex !== false) {
        $_SESSION['devlogs'][$domain][$arrayIndex][] = $msg;
    } else {
        $_SESSION['devlogs'][$domain][] = $msg;
    }
}
function hasDevlog($domain = null, $arrayIndex = false) {
    if (isset($domain)) {
        if ($arrayIndex !== false) return (isset($_SESSION['devlogs'][$domain][$arrayIndex]));
        return (isset($_SESSION['devlogs'][$domain]));
    } else {
        return (isset($_SESSION['devlogs']));
    }
}
function devlogs($domain = null, $arrayIndex = false) {
    if (isset($domain)) {
        if ($arrayIndex !== false) return (isset($_SESSION['devlogs'][$domain][$arrayIndex])) ? $_SESSION['devlogs'][$domain][$arrayIndex] : [];
        return (isset($_SESSION['devlogs'][$domain])) ? $_SESSION['devlogs'][$domain] : [];
    } else {
        return (isset($_SESSION['devlogs'])) ? $_SESSION['devlogs'] : [];
    }
}




function cleanMessages() {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    unset($_SESSION['errors']);
    unset($_SESSION['infos']);
    unset($_SESSION['warnings']);
    unset($_SESSION['devlogs']);
}

