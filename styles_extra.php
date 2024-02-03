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

require_once("../../../config.php");
require_once("$CFG->dirroot/course/format/lib.php");
require_once($CFG->dirroot.'/lib/csslib.php');

$courseid = required_param('course', PARAM_INT);

$format = course_get_format($courseid);
$course = $format->get_course();

require_login($course);

$context = \context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/tiles/styles.php', ['course' => $courseid]));

$templateable = new \format_tiles\output\styles_extra($course);
$data = $templateable->export_for_template($OUTPUT);

$csscontent = '';

$csscontent .= $OUTPUT->render_from_template('format_tiles/styles_extra', $data);

// Site admin may have added additional CSS via the plugin settings.
$csscontent .= get_config('format_tiles', 'customcss') ?? '';

$csscontent .= \format_tiles\util::get_tilefitter_extra_css($courseid);

if ($csscontent) {
    css_send_uncached_css($csscontent);
}
