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
 * File serving for Folder activity.
 *
 * @package    mod_folder
 * @author     2019 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_folder\files;

defined('MOODLE_INTERNAL') || die();

class serving extends \core_files\serving {

    public function serve(\stored_file $file, $forcedownload, $sendfileoptions) {
        self::check_access($file);

        if ($file->get_context()->contextlevel != CONTEXT_MODULE) {
            return false;
        }

        // Intro is handled automatically in pluginfile.php.
        if ($file->get_filearea() !== 'content') {
            return false;
        }

        // For folder module, we force download file all the time.
        send_stored_file($file, 0, 0, true, $sendfileoptions);
    }

}