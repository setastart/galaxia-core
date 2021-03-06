<?php
/* Copyright 2017-2020 Ino Detelić

 - Licensed under the EUPL, Version 1.2 only (the "Licence");
 - You may not use this work except in compliance with the Licence.

 - You may obtain a copy of the Licence at: https://joinup.ec.europa.eu/collection/eupl/eupl-text-11-12

 - Unless required by applicable law or agreed to in writing, software distributed
   under the Licence is distributed on an "AS IS" basis,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 - See the Licence for the specific language governing permissions and limitations under the Licence.
*/

const PROTO_IMAGE = [
    'fit'         => '',
    'w'           => 0,
    'h'           => 0,
    'wOriginal'   => 0,
    'hOriginal'   => 0,
    'name'        => '',
    'ext'         => '',
    'mtime'       => '',
    'fileSize'    => 0,
    'version'     => '',
    'src'         => '',
    'srcset'      => '',
    'alt'         => [],
    'lang'        => '',
    'extra'       => [],
    'sizes'       => [1],
    'sizeDivisor' => 1,
    'loading'     => 'lazy'
];



function gImageList($dirImage) {
    $images = [];
    $glob = glob($dirImage . '*', GLOB_NOSORT);
    if ($glob === false) return $images;
    foreach ($glob as $filename) {
        if (!is_dir($filename)) continue;
        $images[basename($filename)] = filemtime($filename);
    }
    arsort($images);
    return $images;
}




function gImageDimensions(string $dirImage, string $imgSlug) {
    $file = $dirImage . $imgSlug . '/' . $imgSlug . '_dim.txt';
    if (!file_exists($file)) return;
    $dim = explode('x', file_get_contents($file));
    if ($dim) return [(int)$dim[0], (int)$dim[1]];
    return [];
}




function gImageAlt(string $dirImage, string $imgSlug, $lang) {
    $file = $dirImage . $imgSlug . '/' . $imgSlug . '_alt_' . $lang . '.txt';
    if (!file_exists($file)) return;
    $alt = file_get_contents($file);
    return $alt;
}




function gImagePrepare(&$imagick) {
    $iccProfiles = $imagick->getImageProfiles("icc", true);
    $imagick->stripImage();
    // $imagick->setSamplingFactors(array('2x2', '1x1', '1x1'));
    $imagick->setImageProperty('jpeg:sampling-factor', '4:2:0');

    if(!empty($iccProfiles)) $imagick->profileImage("icc", $iccProfiles['icc']);

    $orientation = $imagick->getImageOrientation();
    switch ($orientation) {
        case Imagick::ORIENTATION_BOTTOMRIGHT:
            $imagick->rotateimage("#000", 180); // rotate 180 degrees
            break;
        case Imagick::ORIENTATION_RIGHTTOP:
            $imagick->rotateimage("#000", 90); // rotate 90 degrees CW
            break;
        case Imagick::ORIENTATION_LEFTBOTTOM:
            $imagick->rotateimage("#000", -90); // rotate 90 degrees CCW
            break;
    }
    // Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image
    $imagick->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
}




function gImageGetResizes(string $dirImage, string $imgSlug) {
    $files = [];
    $resizes = [];
    $dirImage = $dirImage . $imgSlug;
    $glob = glob($dirImage . '/*');
    if($glob === false) return $files;
    foreach ($glob as $filename) {
        if (!is_file($filename)) continue;
        if (preg_match('/_[0-9_]+\w/', basename($filename), $matches)) {
            $size = trim($matches[0], '_');
            $size = str_replace('_', 'x', $size);
            $files[$size] = basename($filename);
        }
    }
    krsort($files, SORT_NUMERIC);
    return $files;
}




function gImageDeleteResizes(string $dirImage, string $imgSlug) {
    $resizes = gImageGetResizes($dirImage, $imgSlug);
    $mtime = filemtime($dirImage . $imgSlug . '/');

    foreach ($resizes as $file) {
        if (!unlink($dirImage . $imgSlug . '/' . $file)) {
            error('Error removing resized image: ' . h($imgSlug));
        }
    }

    if ($mtime !== false) touch($dirImage . $imgSlug . '/', $mtime);
    return count($resizes);
}




function gImageSlugRename(string $dirImage, string $imgSlugOld, $imgSlugNew) {
    if (!gImageValid($dirImage, $imgSlugOld)) return false;

    $dirOld  = $dirImage . $imgSlugOld . '/';
    $dirNew  = $dirImage . $imgSlugNew . '/';
    $mtime = filemtime($dirOld);

    if (!rename($dirOld, $dirNew)) {
        error('Error renaming directory');
        return false;
    }

    $glob = glob($dirNew . '*');
    if ($glob === false) return false;
    foreach ($glob as $nameOld) {
        if (!is_file($nameOld)) continue;

        $pos = strrpos($nameOld, $imgSlugOld);
        if ($pos !== false) {
            $nameNew = substr_replace($nameOld, $imgSlugNew, $pos, strlen($imgSlugOld));
            // $nameNew = str_replace($imgSlugOld, $imgSlugNew, $nameOld);

            if (!rename($nameOld, $nameNew)) {
                error('Error renaming file:' . h($nameOld) . ' -> ' . h($nameNew));
                return false;
            }
        }
    }
    if ($mtime !== false) touch($dirNew, $mtime);
    return true;
}




function gImageValid(string $dirImage, string $imgSlug) {
    if (empty($imgSlug)) return false;
    if (preg_match('/[^a-z0-9-]/', $imgSlug)) return false;
    if (realpath($dirImage) == realpath($dirImage . $imgSlug)) return false;
    if (!is_dir($dirImage . $imgSlug)) return false;

    $fileBase = $dirImage . $imgSlug . '/' . $imgSlug;
    if (file_exists($fileBase . '.jpg')) return '.jpg';
    if (file_exists($fileBase . '.png')) return '.png';
    return false;
}




function gImageDelete(string $dirImage, string $imgSlug) {
    if (!gImageValid($dirImage, $imgSlug)) return false;

    foreach (new DirectoryIterator($dirImage . $imgSlug) as $fileInfo) {
        if ($fileInfo->isDot()) continue;
        unlink($fileInfo->getPathname());
    }
    rmdir($dirImage . $imgSlug);
    return true;
}



/** @deprecated  */
function gImageRenderReflowSpacer($w, $h) {
    if ($w < 1) return;
    if ($h < 1) return;
    $padding = round(($h / $w) * 100, 4);
    return '<div class="spacer" style="max-width:' . $w . 'px' . '; max-height:' . $h . 'px;"><div style="padding-bottom: ' . $padding . '%;"></div></div>';
}




function gImageRender($img, $extra = '') {
    if (!$img) return;
    if (!isset($img['src'])) return;
    if ($img['version']) $img['src'] .= '?v=' . h($img['version']);
    $r = '<img';

    if ($img['lang']) {
        $r .= ' alt="' . h($img['alt'][$img['lang']] ?? '') . '"';
        $r .= ' lang="' . h($img['lang']) . '"';
    } else {
        $r .= ' alt=""';
    }

    if ($img['loading'] == 'lazy') {
        $r .= ' loading="lazy"';
    }

    $r .= ' src="' . h($img['src'] ?? '') . '"';
    if ($img['srcset']) $r .= ' srcset="' . h($img['srcset'] ?? '') . '"';

    if ($extra) $r.= ' ' . $extra;

    $r .= ' width="' . h($img['w']) . '" height="' . h($img['h']) . '">';
    return $r;
}




function gImageFit($img) {
    $ratioOriginal = $img['wOriginal'] / $img['hOriginal'];

    if ($img['fit'] && is_int($img['w']) && is_int($img['h']) && $img['w'] > 0 && $img['h'] > 0) {
        if ($img['fit'] == 'cover') {
            $ratioFit = $img['w'] / $img['h'];
            if ($ratioFit >= $ratioOriginal) {
                $img['w'] = 0;
            } else if ($ratioFit < $ratioOriginal) {
                $img['h'] = 0;
            }
        } else if ($img['fit'] == 'contain') {
            $ratioFit = $img['w'] / $img['h'];
            if ($ratioFit >= $ratioOriginal) {
                $img['h'] = 0;
            } else if ($ratioFit < $ratioOriginal) {
                $img['w'] = 0;
            }
        }
    }

    if ($img['w'] < 0 || $img['h'] < 0) return;
    if ($img['w'] == 0) {
        if ($img['h'] == 0) {
            $img['w'] = (int)$img['wOriginal'];
            $img['h'] = (int)$img['hOriginal'];
        } else {
            if ($img['h'] > $img['hOriginal']) $img['h'] = $img['hOriginal'];
            $img['w'] = (int) round($img['h'] * $ratioOriginal);
        }
    } else {
        if ($img['w'] > $img['wOriginal']) $img['w'] = $img['wOriginal'];
        if ($img['h'] == 0) $img['h'] = (int) round($img['w'] / $ratioOriginal);
    }

    return $img;
}
