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
 * CLI script for course data migration on upgrade to tiles 4.3 version.
 * @see \format_tiles\task\migrate_legacy_data;
 * @package    format_tiles
 * @copyright  2023 David Watson {@link http://evolutioncode.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Developer should not need to run this script in most cases as it will be handled by upgrade.php.
// It is left in for now since it may be useful for troubleshooting.
//todo render correct template on /course/section.php page
define('CLI_SCRIPT', true);
require_once(__DIR__ . "/../../../../config.php");

$expectedvalue = '22, 112, 204';
$got = 'rgba(22, 112, 204)';
//$pattern = '/(.+)\/[^\/]*$/';
$pattern = '/rgba?\(22, 112, 204(\)|, \d\.\d\))$/';
echo preg_match($pattern, $got);