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
 * Schedule task updated event.
 *
 * @package    core
 * @copyright  2019 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\event;

use core\task\scheduled_task;

defined('MOODLE_INTERNAL') || die();

/**
 * Schedule task updated event.
 *
 * @package    core
 * @since      Moodle 3.8
 * @copyright  2019 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_task_updated extends base {

    /** @var \stdClass $gradeitem */
    protected $taskrecord;

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'task_scheduled';
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventscheduletaskupdated', 'moodle');
    }

    /**
     * Utility method to create a new event.
     *
     * @param \stdClass $taskrecord
     *
     * @return \core\event\schedule_task_updated
     */
    public static function create_from_schedule_task_record(\stdClass $taskrecord) {
        $event = self::create([
            'objectid' => $taskrecord->id,
            'context' => \context_system::instance(),
            'other' => [
                'classname' => $taskrecord->classname,
                'component' => $taskrecord->component,
                'blocking' => $taskrecord->blocking,
                'customised' => $taskrecord->customised,
                'lastruntime' => $taskrecord->lastruntime,
                'nextruntime' => $taskrecord->nextruntime,
                'faildelay' => $taskrecord->faildelay,
                'hour' => $taskrecord->hour,
                'minute' => $taskrecord->minute,
                'day' => $taskrecord->day,
                'dayofweek' => $taskrecord->dayofweek,
                'month' => $taskrecord->month,
                'disabled' => $taskrecord->disabled,
            ],
        ]);

        $event->taskrecord = $taskrecord;

        return $event;
    }

    /**
     * Get grade object.
     *
     * @throws \coding_exception
     * @return scheduled_task  | bool
     */
    public function get_scheduled_task() {
        global $DB;

        if ($this->is_restored()) {
            throw new \coding_exception('get_scheduled_task() is intended for event observers only');
        }

        if (!isset($this->taskrecord)) {
            $this->taskrecord = $DB->get_record('task_scheduled', array('id' => $this->objectid), 'id', MUST_EXIST);
        }

        if (empty($this->taskrecord)) {
            // Should never happen.
            throw new \coding_exception('Scheduled task record is not exist');
        }

        return \core\task\manager::scheduled_task_from_record($this->taskrecord);
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/tool/task/scheduledtasks.php', ['action' => 'edit', 'task' => $this->taskrecord->classname]);
    }

    /**
     * Custom validation.
     *
     * Throw \coding_exception notice in case of any problems.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['repeatid'])) {
            throw new \coding_exception('The \'repeatid\' value must be set in other.');
        }
        if (!isset($this->other['name'])) {
            throw new \coding_exception('The \'name\' value must be set in other.');
        }
        if (!isset($this->other['timestart'])) {
            throw new \coding_exception('The \'timestart\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'event', 'restore' => 'event');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['repeatid'] = array('db' => 'event', 'restore' => 'event');

        return $othermapped;
    }
}
