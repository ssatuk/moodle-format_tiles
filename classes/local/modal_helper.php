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
 * Helper class for dealing with modals class for format_tiles.
 * @package    format_tiles
 * @copyright  2023 David Watson {@link http://evolutioncode.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_tiles\local;

/**
 * Helper class for dealing with modals class for format_tiles.
 * @package    format_tiles
 * @copyright  2023 David Watson {@link http://evolutioncode.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_helper {

    /**
     * Which course modules is the site administrator allowing to be displayed in a modal?
     * @return array the permitted modules including resource types e.g. page, pdf, HTML
     * @throws \dml_exception
     */
    public static function allowed_modal_modules(): array {
        $devicetype = \core_useragent::get_device_type();
        if ($devicetype != \core_useragent::DEVICETYPE_TABLET && $devicetype != \core_useragent::DEVICETYPE_MOBILE
            && !(\core_useragent::is_ie())) {
            // JS navigation and modals in Internet Explorer are not supported by this plugin so we disable modals here.
            $resources = get_config('format_tiles', 'modalresources');
            $modules = get_config('format_tiles', 'modalmodules');
            return [
                'resources' => $resources ? explode(",", $resources) : [],
                'modules' => $modules ? explode(",", $modules) : [],
            ];
        } else {
            return ['resources' => [], 'modules' => []];
        }
    }


    /**
     * Get the course module IDs for any resource modules in this course that need a modal.
     * @param int $courseid
     * @param string $mimetype
     * @return array
     */
    public static function get_resource_modal_cmids(int $courseid, string $mimetype): array {
        global $DB;
        if (!in_array($mimetype, ['application/pdf', 'text/html'])) {
            debugging("Unexpected MIME type " . $mimetype, DEBUG_DEVELOPER);
            return [];
        }

        // This is not very efficient so we cache the results elsewhere.
        // When multiple files are uploaded to a single resource activity, Moodle displays the lowest sort order item
        // Here we use the index on the files table component-filearea-contextid-itemid.
        $excludeddisplaytypes = [
            RESOURCELIB_DISPLAY_POPUP, RESOURCELIB_DISPLAY_NEW, RESOURCELIB_DISPLAY_DOWNLOAD,
        ];
        list($insql, $params) =
            $DB->get_in_or_equal($excludeddisplaytypes, SQL_PARAMS_NAMED, 'param', false);
        $params['courseid'] = $courseid;
        $params['contextmodule'] = CONTEXT_MODULE;
        $params['mimetype'] = $mimetype;

        // First get file cmids of relevant mime type.
        // There is an index on the files table component-filearea-contextid-itemid.
        $sql = "SELECT DISTINCT cm.id
                    FROM {course_modules} cm
                    JOIN {modules} m ON m.id = cm.module and m.name = 'resource'
                    JOIN {resource} r ON cm.instance = r.id
                    JOIN {context} ctx ON ctx.contextlevel = :contextmodule AND ctx.instanceid = cm.id
                    JOIN {files} f ON f.component = 'mod_resource' AND f.filearea = 'content' AND f.contextid = ctx.id
                        AND f.itemid = 0 AND f.filesize > 0 and f.filename != '.' AND f.mimetype = :mimetype
                    WHERE cm.course = :courseid AND cm.deletioninprogress = 0 AND r.display $insql";
        return $DB->get_fieldset_sql($sql, $params);
    }

    /**
     * This is to avoid re-implementing multiple files from the course index.
     * To know which resources to launch in modals, we can get the cmids of all resources which will launch as modals.
     * @param int $courseid
     * @param bool $excludeunavailable should we check availability of each cm in list and exclude unavailable?
     * @return array course module IDs to launch in modals.
     */
    public static function get_modal_allowed_cm_ids(int $courseid, bool $excludeunavailable): array {
        global $DB, $CFG;
        $modinfo = null;

        $cmids = [];

        // The cached value is for the course and does not take user visibility into account.
        // But it may save us some time.
        $cache = \cache::make('format_tiles', 'modalcmids');
        $cachedvalue = $cache->get($courseid);
        if ($cachedvalue === false) {
            $modinfo = get_fast_modinfo($courseid);
            // Config values to be added to templates for JS to retrieve.
            // May move more to this from existing JS init in format.php.

            // To import RESOURCELIB_DISPLAY_XXX.
            require_once("$CFG->libdir/resourcelib.php");

            $allowedmodals = self::allowed_modal_modules();
            $allowedmodals = array_merge($allowedmodals['modules'] ?? [], $allowedmodals['resources'] ?? []);
            foreach ($allowedmodals as $allowedmodule) {
                if ($allowedmodule == 'url') {
                    $excludeddisplaytypes = [RESOURCELIB_DISPLAY_POPUP, RESOURCELIB_DISPLAY_NEW];
                    list($insql, $params) =
                        $DB->get_in_or_equal($excludeddisplaytypes, SQL_PARAMS_NAMED, 'param', false);
                    $params['course'] = $courseid;
                    $cmids = array_merge($cmids, $DB->get_fieldset_sql(
                        "SELECT DISTINCT cm.id FROM {url} u
                             JOIN {course_modules} cm ON cm.instance = u.id
                             JOIN {modules} m ON m.id = cm.module AND m.name = 'url'
                             WHERE u.course = :course AND cm.deletioninprogress = 0 AND u.display $insql", $params
                    ));
                } else if (in_array($allowedmodule, ['pdf', 'html'])) {
                    $resourcecmids = self::get_resource_modal_cmids(
                        $courseid, $allowedmodule == 'pdf' ? 'application/pdf' : 'text/html'
                    );
                    $cmids = array_merge($cmids, $resourcecmids);
                } else if ($allowedmodule == 'page') {
                    $cmids = [];
                    $pagecms
                        = $modinfo->get_instances_of('page');
                    foreach ($pagecms as $pagecm) {
                        $cmids[] = $pagecm->id;
                    }
                }
            }

            // Ensure all are ints for JS and sort to ease debugging.
            $cmids = array_map(function($cmid) {
                return (int)$cmid;
            }, $cmids);
            sort($cmids);

            // Now we can set the cached value for all users, before going on to check visibility for this user only.
            $cache->set($courseid, $cmids);

        } else {
            // We already have a cached value so use that.
            $cmids = $cachedvalue;
        }

        if (!$excludeunavailable) {
            return $cmids;
        }

        // Now we check user visibility for the cmids which may be relevant.
        $result = [];
        if (!empty($cmids)) {
            $modinfo = $modinfo ?: get_fast_modinfo($courseid);
            foreach ($cmids as $cmid) {
                try {
                    $cm = $modinfo->get_cm($cmid);
                } catch (\Exception $e) {
                    // This is unexpected, but we don't want an exception in the footer so continue.
                    debugging("Could not find course mod $cmid " . $e->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }

                if (!$cm->onclick && $cm->uservisible) {
                    $result[] = (int)$cm->id; // Must be ints for JS to interpret correctly.
                }
            }
        }
        return $result;
    }

    /**
     * Does a particular course module use a modal.
     * @param int $courseid
     * @param int $cmid
     * @return bool
     */
    public static function cm_has_modal(int $courseid, int $cmid): bool {
        $cmids = self::get_modal_allowed_cm_ids($courseid, false);
        return !empty($cmids) && in_array($cmid, $cmids);
    }
}
