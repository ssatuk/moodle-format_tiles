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
     * This leverages the fact that cached cminfo already contains the resource file type in the "icon" field.
     * So we can avoid querying the files table to get that here.
     * @param int $courseid
     * @param array $mimetypes
     * @return array
     */
    public static function get_resource_modal_cmids(int $courseid, array $mimetypes): array {
        global $CFG;

        if (empty($mimetypes)) {
            return [];
        }
        foreach ($mimetypes as $mimetype) {
            if (!array($mimetype, ['application/pdf', 'text/html'])) {
                throw new \Exception("Unexpected MIME type '$mimetype'");
            }
        }

        // To import RESOURCELIB_DISPLAY_XXX etc.
        require_once("$CFG->libdir/resourcelib.php");

        $result = [];
        $modinfo = get_fast_modinfo($courseid);
        $cms = $modinfo->get_instances_of('resource');
        $excludeddisplaytypes = [
            RESOURCELIB_DISPLAY_POPUP, RESOURCELIB_DISPLAY_NEW, RESOURCELIB_DISPLAY_DOWNLOAD,
        ];

        foreach ($cms as $cm) {
            // If the CM has "onclick" set (e.g. open in new tab) then it won't use a modal.
            if ($cm->onclick) {
                continue;
            }

            // We are only potentially interested in PDF and HTML files.
            if (!in_array($cm->icon, ['f/pdf', 'f/html', 'f/markup'])) {
                continue;
            }
            // We are only interested in file types allowed by site admin.
            if ($cm->icon == 'f/pdf' && !in_array('application/pdf', $mimetypes)) {
                continue;
            }
            if (in_array($cm->icon, ['f/html', 'f/markup']) && !in_array('text/html', $mimetypes)) {
                continue;
            }

            // We are only interested if the cm's display value is of the relevant type.
            $display = $cm->get_custom_data()['display'] ?? null;
            if (in_array($display, $excludeddisplaytypes)) {
                continue;
            }
            $result[] = (int)$cm->id;
        }
        return $result;
    }

    /**
     * This is to avoid re-implementing multiple files from the course index.
     * To know which resources to launch in modals, we can get the cmids of all resources which will launch as modals.
     * @param int $courseid
     * @param bool $excludeunavailable should we check availability of each cm in list and exclude unavailable?
     * @return array course module IDs to launch in modals.
     */
    public static function get_modal_allowed_cm_ids(int $courseid, bool $excludeunavailable): array {
        global $CFG;

        // First check what modals site admin is allowing.
        $allowedmodals = self::allowed_modal_modules();
        $allowedmodals = array_merge($allowedmodals['modules'] ?? [], $allowedmodals['resources'] ?? []);
        if (empty($allowedmodals)) {
            return [];
        }

        $modinfo = null;
        $cmids = [];

        // The cached value is for the course and does not take user visibility into account.
        // But it may save us some time.
        $cache = \cache::make('format_tiles', 'modalcmids');
        $cachedvalue = $cache->get($courseid);
        if ($cachedvalue === false) {
            $modinfo = get_fast_modinfo($courseid);

            // To import RESOURCELIB_DISPLAY_XXX etc.
            require_once("$CFG->libdir/resourcelib.php");

            foreach ($allowedmodals as $allowedmodule) {
                if (in_array($allowedmodule, ['pdf', 'html'])) {
                    // These are dealt with separately below, outside the loop, as more efficient.
                    continue;
                } else if ($allowedmodule == 'url') {
                    $excludeddisplaytypes = [RESOURCELIB_DISPLAY_POPUP, RESOURCELIB_DISPLAY_NEW];
                    $urlcms = $modinfo->get_instances_of('url');
                    foreach ($urlcms as $urlcm) {
                        if (!in_array($urlcm->get_custom_data()['display'] ?? null, $excludeddisplaytypes)) {
                            $cmids[] = (int)$urlcm->id;
                        }
                    }
                } else if ($allowedmodule == 'page') {
                    $pagecms
                        = $modinfo->get_instances_of('page');
                    foreach ($pagecms as $pagecm) {
                        $cmids[] = (int)$pagecm->id;
                    }
                } else {
                    debugging("Unexpected module: $allowedmodule", DEBUG_DEVELOPER);
                }
            }

            // Now deal with PDF and HTML files if any.
            $mimemapping = ['pdf' => 'application/pdf', 'html' => 'text/html'];
            $allowedresourcemimetypes = [];
            foreach ($mimemapping as $key => $value) {
                if (in_array($key, $allowedmodals)) {
                    $allowedresourcemimetypes[] = $value;
                }
            }
            $resourcecmids = self::get_resource_modal_cmids($courseid, $allowedresourcemimetypes);
            $cmids = array_merge($cmids, $resourcecmids);

            // Ensure all CM IDs are integers for JS and sort to ease debugging.
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
            // We may want to skip the availability check for efficiency, where it doesn't matter.
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

    /**
     * Is this module one which uses the cache to store modal cm data?
     * @param string $modname
     * @return bool
     */
    public static function mod_uses_cm_modal_cache(string $modname): bool {
        return in_array($modname, ['resource', 'page', 'url']);
    }

    /**
     * Clear the cache of resource modal IDs for a given course.
     * @param int $courseid
     * @return void
     */
    public static function clear_cache_modal_cmids(int $courseid) {
        // See also \cache_helper::purge_by_event('format_tiles/modaladminsettingchanged') in settings.php.
        $cache = \cache::make('format_tiles', 'modalcmids');
        $cache->delete($courseid);
    }
}
