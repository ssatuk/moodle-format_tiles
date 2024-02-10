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
 * Special dynamic styles for Tiles format e.g. Tiles format allows editors to set individual course colours.
 *
 * @package format_tiles
 * @copyright 2024 David Watson {@link http://evolutioncode.uk}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
// We cannot use define('ABORT_AFTER_CONFIG', true) since we need to access get_config() etc).

require_once("../../../config.php");
require_once("$CFG->libdir/configonlylib.php");

require_login();

$basecolour = null;
$shadeheadingbar = false;
$courseid = null;
$usingtilefitter = false;
$tilefittermaxwidth = null;

$csscontent = '';
$errors = [];

$expectednumberofslashargs = 7;
$slashargument = min_get_slash_argument();
if ($slashargument) {
    $slashargument = ltrim($slashargument, '/');
    $slashargs = explode('/', $slashargument, $expectednumberofslashargs);
    $countfoundargs = count($slashargs);
    if ($countfoundargs == $expectednumberofslashargs) {
        list($themename, $themerev, $courseid, $basecolour, $shadeheadingbar, $usingtilefitter, $tilefittermaxwidth) = $slashargs;
        $basecolour = '#' . min_clean_param($basecolour, 'SAFEDIR');
        $shadeheadingbar = min_clean_param($shadeheadingbar, 'INT') ? 1 : 0;
        $courseid = min_clean_param($courseid, 'INT');
        $usingtilefitter = min_clean_param($usingtilefitter, 'INT');
        $tilefittermaxwidth = min_clean_param($tilefittermaxwidth, 'INT');
    } else {
        // Some slash args missing so add a comment to CSS for debugging. Default values will be used below.
        $errors[] = "Expected $expectednumberofslashargs found $countfoundargs";
    }
}

// Should not happen, but if we reach here with no valid colour from slash args, use default values so that course looks ok.
if (!$basecolour || strlen($basecolour) !== 7) {
    $courseid = $courseid ?? 0;
    $defaultcolour = \format_tiles\output\styles_extra::get_tile_base_colour();
    $errors[] = "Using default colour $defaultcolour as hex '$basecolour' is invalid";
    $basecolour = $defaultcolour;
    $shadeheadingbar = $shadeheadingbar ?? false;
}

if ($courseid) {
    // Set course context if present so that any use of $PAGE elsewhere works correctly.
    $PAGE->set_context(context_course::instance($courseid));
    if ($usingtilefitter) {
        $csscontent .= \format_tiles\output\styles_extra::get_tilefitter_extra_css($courseid, $tilefittermaxwidth) . "\n";
    }
} else {
    $PAGE->set_context(context_system::instance());
}

$data = \format_tiles\output\styles_extra::export_for_template($basecolour, $shadeheadingbar);
$m = new Mustache_Engine;
$csscontent .= $m->render(
    file_get_contents("$CFG->dirroot/course/format/tiles/templates/styles_extra.mustache"),
    $data
);

// Site admin may have added additional CSS via the plugin settings.
$adminsetting = get_config('format_tiles', 'customcss') ?? '';
if (trim($adminsetting)) {
    $csscontent .= "/*admin*/ " . trim($adminsetting);
}

if (trim($csscontent)) {
    \format_tiles\output\styles_extra::send_uncached_css($csscontent, $errors);
}
