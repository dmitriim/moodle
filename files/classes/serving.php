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
 * File serving interface.
 *
 * @package    core_files
 * @author     2019 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_files;

defined('MOODLE_INTERNAL') || die();

abstract class serving {

    /**
     * @var string
     */
    protected $component;

    /**
     * @param string $component
     *
     * @return \core_files\serving
     */
    public static function get_instance(string $component) {
        $serving = null;
        $classname = $component . '\\files\\serving';

        if (class_exists($classname)) {
            if (is_subclass_of($classname, self::class)) {
                $serving = new $classname($component);
            } else {
                throw new \coding_exception('');
            }
        }

        return $serving;
    }

    /**
     * serving constructor.
     *
     * @param $component
     */
    protected function __construct($component) {
        $this->component = $component;
    }


    /**
     * @param \stored_file $file
     * @param $forcedownload
     * @param $sendfileoptions
     */
    public function serve(\stored_file $file, $forcedownload, $sendfileoptions) {
        $this->check_access($file);
        send_stored_file($file, null, 0, $forcedownload, $sendfileoptions);
    }

    /**
     * @param $contextid
     * @param $filearea
     * @param array|null $args
     *
     * @return bool|\stored_file
     */
    public function get_stored_file($contextid, $filearea, array $args = null) {
        $fs = get_file_storage();

        $itemid = !empty($args) ? (int)array_shift($args) : 0;
        $filename = array_pop($args);
        $filepath = $args ? '/'.implode('/', $args).'/' : '/';

        return $fs->get_file($contextid, $this->component, $filearea, $itemid, $filepath, $filename);
    }

    /**
     * @param \stored_file $file
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws \require_login_exception
     */
    protected function check_access(\stored_file $file) {
        if (!$file->can_access()) {
            send_file_not_found();
        }

        if ($file->is_directory()) {
            send_file_not_found();
        }

        list($context, $course, $cm) = get_context_info_array($file->get_contextid());

        switch ($file->get_login_level()) {
            case file_interface::FILE_ACCESS_LEVEL_LOGIN:
                require_login();
                break;
            case file_interface::FILE_ACCESS_LEVEL_COURSE:
                require_course_login($course->id);
                break;
            case file_interface::FILE_ACCESS_LEVEL_MODULE:
                require_course_login($course->id, true, $cm);
                break;
            case file_interface::FILE_ACCESS_LEVEL_ADMIN:
                require_admin();
                break;
            default:
                send_file_not_found();
        }
    }

}