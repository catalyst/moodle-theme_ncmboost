<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * NCM Theme based on boost theme .
 *
 * @package    theme_ncmboost
 * @copyright  2018 Nicolas Jourdain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_ncmboost_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        // We still load the default preset files directly from the boost theme. No sense in duplicating them.
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        // We still load the default preset files directly from the boost theme. No sense in duplicating them.
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/plain.scss');

    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_ncmboost', 'preset', 0, '/', $filename))) {
        // This preset file was fetched from the file area for theme_ncmboost and not theme_boost (see the line above).
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    }

    // Pre CSS - this is loaded AFTER any prescss from the setting but before the main scss.
    $pre = file_get_contents($CFG->dirroot . '/theme/ncmboost/scss/pre.scss');
    // Post CSS - this is loaded AFTER the main scss but before the extra scss from the setting.
    $post = file_get_contents($CFG->dirroot . '/theme/ncmboost/scss/post.scss');

    // Combine them together.
    return $pre . "\n" . $scss . "\n" . $post;
}

/**
 * Implement pluginfile function to deliver files which are uploaded in theme settings
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function theme_ncmboost_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('ncmboost');
        // By default, theme files must be cache-able by both browsers and proxies.
        // TODO: For new file areas: Check if the cacheability needs to be restricted.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'publi`c';
        }
        if ($filearea === 'favicon') {
            return $theme->setting_file_serve('favicon', $args, $forcedownload, $options);
        } else if (s($filearea === 'logo' || $filearea === 'backgroundimage')) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if ($filearea === 'loginbackgroundimage') {
            return $theme->setting_file_serve('loginbackgroundimage', $args, $forcedownload, $options);
        } else if ($filearea === 'fontfiles') {
            return $theme->setting_file_serve('fontfiles', $args, $forcedownload, $options);
        } else if ($filearea === 'imageareaitems') {
            return $theme->setting_file_serve('imageareaitems', $args, $forcedownload, $options);
        } else {
            send_file_not_found();
        }
    } else {
        send_file_not_found();
    }
}

/**
 * Copy the updated theme image to the correct location in dataroot for the image to be served
 * by /theme/image.php. Also clear theme caches.
 *
 * @param $settingname
 */
function theme_ncmboost_update_settings_images($settingname) {
    global $CFG;

    // The setting name that was updated comes as a string like 's_theme_ncmboost_loginbackgroundimage'.
    // We split it on '_' characters.
    $parts = explode('_', $settingname);
    // And get the last one to get the setting name..
    $settingname = end($parts);

    // Admin settings are stored in system context.
    $syscontext = context_system::instance();
    // This is the component name the setting is stored in.
    $component = 'theme_ncmboost';


    // This is the value of the admin setting which is the filename of the uploaded file.
    $filename = get_config($component, $settingname);
    // We extract the file extension because we want to preserve it.
    $extension = substr($filename, strrpos($filename, '.') + 1);

    // This is the path in the moodle internal file system.
    $fullpath = "/{$syscontext->id}/{$component}/{$settingname}/0{$filename}";

    // This location matches the searched for location in theme_config::resolve_image_location.
    $pathname = $CFG->dataroot . '/pix_plugins/theme/ncmboost/' . $settingname . '.' . $extension;

    // This pattern matches any previous files with maybe different file extensions.
    $pathpattern = $CFG->dataroot . '/pix_plugins/theme/ncmboost/' . $settingname . '.*';

    // Make sure this dir exists.
    @mkdir($CFG->dataroot . '/pix_plugins/theme/ncmboost/', $CFG->directorypermissions, true);

    // Delete any existing files for this setting.
    foreach (glob($pathpattern) as $filename) {
        @unlink($filename);
    }

    // Get an instance of the moodle file storage.
    $fs = get_file_storage();
    // This is an efficient way to get a file if we know the exact path.
    if ($file = $fs->get_file_by_hash(sha1($fullpath))) {
        // We got the stored file - copy it to dataroot.
        $file->copy_content_to($pathname);
    }

    // Reset theme caches.
    theme_reset_all_caches();
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_ncmboost_get_pre_scss($theme) {
    global $CFG;

    $scss = '';
    $configurable = [
        // Config key => [variableName, ...].
        //'brandcolor' => ['brand-primary'],
        'ncmbrandcolor' => ['ncm-brandcolor'],
        'ncmkeytxtcolor' => ['ncm-keytxtcolor']
    ];

    // Prepend variables first.
    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}