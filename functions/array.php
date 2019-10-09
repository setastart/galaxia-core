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

function strvalIfNotNull($value) {
    if ($value !== null) return strval($value);
}




function array_splice_preserve_keys(&$input, $offset, $length = null, $replacement = array()) {
    if (empty($replacement)) {
        return array_splice($input, $offset, $length);
    }

    $part_before  = array_slice($input, 0, $offset, $preserve_keys=true);
    $part_removed = array_slice($input, $offset, $length, $preserve_keys=true);
    $part_after   = array_slice($input, $offset+$length, null, $preserve_keys=true);

    $input = $part_before + $replacement + $part_after;

    return $part_removed;
}




function array_map_recursive(callable $func, array $arr) {
    array_walk_recursive($arr, function(&$v) use ($func) {
        $v = $func($v);
    });
    return $arr;
}




/**
 * Walk array recursively, making keys and values multilingual.
 * Also remove elements for which the user doesn't have permissions
 * Examples:
 *      keys: ['value_' => ['label' => 'Value']] becomes ['value_pt' => ['label' => 'Value'], 'value_en' => ['label' => 'Value']]
 *      values: ['slug_', 'pageId'] becomes ['slug_pt', 'slug_en', 'pageId']
 */
function arrayLanguifyRemovePerms(&$arr, $langs, $perms = []) {
    if (!is_array($arr)) return;

    $count = count($arr);
    for ($i = $count - 1; $i >= 0; $i--) {
        $subArr = array_slice($arr, $i, 1, true);
        $subKey = key($subArr);
        $subVal = $subArr[$subKey];

        // languify keys with arrays
        if (is_array($arr[$subKey])) {

            // empty element that doesn't match permission
            if (isset($arr[$subKey]['gcPerms']) && is_array($arr[$subKey]['gcPerms'])) {
                $foundPerms = array_intersect($arr[$subKey]['gcPerms'], $perms);
                if (!$foundPerms) {
                    // unset($arr[$subKey]);
                    $arr[$subKey] = [];
                    continue;
                }

                $arr[$subKey]['gcPerms'] = $foundPerms;
            }

            arrayLanguifyRemovePerms($arr[$subKey], $langs, $perms);

            if (substr($subKey, -1) == '_') {

                $j = $i;
                foreach ($langs as $lang) {

                    foreach ($subVal as $key => &$val)
                        if (is_string($val) && substr($val, -1) == '_') $val .= $lang;

                    $subItemNew = [$subKey . $lang => array_merge($subVal, [
                        'lang' => $lang,
                        'prefix' => substr($subKey, 0, -1),
                    ])];

                    $length = ($i == $j) ? 1 : 0;
                    array_splice_preserve_keys($arr, $j, $length, $subItemNew);
                    $j++;

                }
                // d($arr[$subKey], $i, $subItemNew, $subArr, $subKey, $subVal);
            }

            continue;
        }


        // languify keys with values
        if (substr($subKey, -1) == '_') {
            $j = $i;
            $subItemNew = [];
            foreach ($langs as $lang) {
                $subItemNew[$subKey . $lang] = $subVal;
                $length = ($i == $j) ? 1 : 0;
                array_splice_preserve_keys($arr, $j, $length, $subItemNew);
                $j++;
            }
        }


        // languify values in arrays
        if (is_string($subVal) && substr($subVal, -1) == '_') {
            $subItemNew = [];
            foreach ($langs as $lang)
                $subItemNew[] = $subVal . $lang;

            if (is_int($subKey)) {
                array_splice($arr, $subKey, 1, $subItemNew);
            } else if (is_string($subKey)) {
                $arr[$subKey] = $subItemNew;
            }
        }
    }
}

