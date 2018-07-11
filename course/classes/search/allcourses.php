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
 * Search area for all Moodle courses.
 *
 * @package    core_course
 * @copyright  2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\search;

use \core_search\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for all Moodle courses.
 *
 * @package    core_course
 * @copyright  2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allcourses extends mycourse {

    /**
     * The context levels the search implementation is working on.
     *
     * @var array
     */
    protected static $levels = [CONTEXT_COURSE];

    /**
     * Returns the document associated with this course.
     *
     * @param \stdClass $record
     * @param array $options
     * @return \core_search\document | bool
     */
    public function get_document($record, $options = array()) {
        if ($doc = parent::get_document($record, $options)) {
            $doc->set('description1', $doc->get('content'));
            $doc->set('content', '');

            return $doc;
        }

        return false;
    }

    /**
     * Whether the user can access the document or not.
     *
     * @param int $id The course instance id.
     * @return int
     */
    public function check_access($id) {
        global $DB;
        $course = $DB->get_record('course', array('id' => $id));
        if (!$course) {
            return manager::ACCESS_DELETED;
        }

        if (manager::is_enabled_include_all_courses()) {
            return manager::ACCESS_GRANTED;
        }

        return manager::ACCESS_DENIED;
    }

    /**
     * Returns the moodle component name.
     *
     * It might be the plugin name (whole frankenstyle name) or the core subsystem name.
     *
     * @return string
     */
    public function get_component_name() {
        return 'course';
    }
}
