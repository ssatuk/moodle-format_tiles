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
 * Tiles course format, extra styles output class
 *
 * @package format_tiles
 * @copyright 2018 David Watson {@link http://evolutioncode.uk}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_tiles\output;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot .'/course/format/lib.php');

/**
 * Prepares data for adding extra styles via template to provide custom colour for tiles
 *
 * @package format_tiles
 * @copyright 2018 David Watson {@link http://evolutioncode.uk}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class styles_extra implements \renderable, \templatable {
    /**
     * The hex code for the base colour used in this course.
     * @var
     */
    private $basecolourhex;

    /**
     * Whether the shade heading bar is set to yes for this course.
     * @var
     */
    private $shadeheadingbar;

    /**
     * Styles extra constructor
     * @param string $basecolourhex the hex code for the base colour used in this course.
     * @param bool $shadeheadingbar whether the shade heading bar is set to yes for this course.
     */
    public function __construct(string $basecolourhex, bool $shadeheadingbar) {
        $this->basecolourhex = $basecolourhex;
        $this->shadeheadingbar = $shadeheadingbar;
    }

    /**
     * Export the data for the mustache template.
     * @see \format_tiles\util::width_template_data()
     * @param \renderer_base $output
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function export_for_template($output) {

        $tilestyle = get_config('format_tiles', 'tilestyle') ?? \format_tiles\output\course_output::TILE_STYLE_STANDARD;

        $basecolourrgba = $this->rgbacolour($this->basecolourhex);
        $outputdata = [
            "isstyle-$tilestyle" => true,
            'isstyle1or2' => $tilestyle == 1 || $tilestyle == 2,
            'base_colour_rgba' => $basecolourrgba,
        ];

        if (get_config('format_tiles', 'allowphototiles')) {
            $outputdata['allowphototiles'] = 1;
            $outputdata['photo_tile_text_bg_opacity'] =
                1.0 - (float)get_config('format_tiles', 'phototiletitletransarency');

            // The best values here vary by theme and browser, so mostly come from admin setting.
            // If the site admin sets background opacity to solid then it doesn't matter if the lines overlap.
            $outputdata['phototilefontsize'] = 20;
            $outputdata['phototiletextpadding'] = number_format(
                (float)get_config('format_tiles', 'phototitletitlepadding') / 10, 1
            );
            $outputdata['phototiletextlineheight'] = number_format(
                (float)get_config('format_tiles', 'phototitletitlelineheight') / 10, 1
            );
        }
        $outputdata['shade_heading_bar'] = $this->shadeheadingbar;

        return $outputdata;
    }

    /**
     * Convert hex colour from plugin settings admin page to RGBA
     * so that can add transparency to it when used as background
     * @param string $hex the colour in hex form e.g. #979797
     * @return string rgba colour
     */
    private function rgbacolour($hex) {
        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        return "$r,$g,$b";
    }
}
