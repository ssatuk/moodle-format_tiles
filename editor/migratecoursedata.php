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
 * Page called by administrator to migrate course data (for addressing any issues on 4.3 upgrade).
 *
 * @package format_tiles
 * @copyright  2023 David Watson {@link http://evolutioncode.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

require_once('../../../../config.php');

global $PAGE, $DB, $OUTPUT;

require_login();
$systemcontext = context_system::instance();

// Admins only for this page.
if (!has_capability('moodle/site:config', $systemcontext)) {
    throw new moodle_exception('You do not have permission to perform this action.');
}

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    require_sesskey();
}

$pageurl = new moodle_url('/course/format/tiles/editor/migratecoursedata.php');
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'formatsettingtiles']);

$PAGE->set_url($pageurl);
$PAGE->set_context($systemcontext);
$PAGE->set_heading(get_string('admintools', 'format_tiles'));
$PAGE->navbar->add(get_string('administrationsite'), new moodle_url('/admin/search.php'));
$PAGE->navbar->add(get_string('plugins', 'admin'), new moodle_url('/admin/category.php', ['category' => 'modules']));
$PAGE->navbar->add(get_string('courseformats'), new moodle_url('/admin/category.php', ['category' => 'formatsettings']));
$PAGE->navbar->add(get_string('pluginname', 'format_tiles'), $settingsurl);
$PAGE->navbar->add(get_string('migratecoursedata', 'format_tiles'));

if ($courseid) {
    // In this case we need to process the course now.
    if (!$DB->record_exists('course', ['id' => $courseid, 'format' => 'tiles'])) {
        \core\notification::error(get_string('error'));
        redirect($pageurl);
    }
    \format_tiles\format_option::migrate_legacy_format_options($courseid);
    \core\notification::info(get_string('migratedcourseid', 'format_tiles', $courseid));
    redirect($pageurl);
}

$legacycourses = $DB->get_records_sql(
"SELECT * FROM
        (SELECT c.id as courseid, c.fullname,
            (SELECT COUNT(cfo.id) FROM {course_format_options} cfo
                WHERE cfo.courseid = c.id AND  cfo.format = 'tiles' AND cfo.name IN('tilephoto', 'tileicon')) as legacyoptions,
            (SELECT COUNT(tfo.id) FROM {format_tiles_tile_options} tfo
                WHERE tfo.courseid = c.id AND tfo.optiontype IN (?, ?)) as newoptions
        FROM {course} c
         ) counts
    WHERE counts.legacyoptions > 0",
    [\format_tiles\format_option::OPTION_SECTION_PHOTO, \format_tiles\format_option::OPTION_SECTION_ICON]
);
$table = new html_table();
$table->head = [
    get_string('course'),
    get_string('legacytiledata', 'format_tiles'),
    get_string('newtiledata', 'format_tiles'),
    get_string('migratenow', 'format_tiles'),
];
$table->data = [];
foreach ($legacycourses as $legacycourse) {
    $table->data[] = [
        html_writer::link(
            new moodle_url('/course/view.php', ['id' => $legacycourse->courseid]),
            $legacycourse->fullname
        ),
        $legacycourse->legacyoptions,
        $legacycourse->newoptions,
        html_writer::link(
            new moodle_url('/course/format/tiles/editor/migratecoursedata.php',
                ['courseid' => $legacycourse->courseid, 'sesskey' => sesskey()]),
            get_string('migratenow', 'format_tiles'),
            ['class' => 'btn btn-primary']
        ),
    ];
}
if (empty($table->data)) {
    $table->data[] = [get_string('none'), '', '', ''];
}
$croncheck = new \tool_task\check\cronrunning();
$cronresult = $croncheck->get_result();

echo $OUTPUT->header();
if ($cronresult->get_status() !== $cronresult::OK) {
    \core\notification::warning($cronresult->get_summary());
}
echo html_writer::div(get_string('unmigratedcoursesintro', 'format_tiles',  count($legacycourses)), 'mb-2');
echo html_writer::table($table);
echo $OUTPUT->footer();
