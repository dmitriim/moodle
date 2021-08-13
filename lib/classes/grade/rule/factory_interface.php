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
 * Interface for grade rules
 *
 * @package     core
 * @copyright   2019 Monash University (http://www.monash.edu)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\grade\rule;

/**
 * Interface for grade rules
 *
 * @package     core
 * @copyright   2019 Monash University (http://www.monash.edu)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface factory_interface {

    /**
     * Create rule_interface.
     *
     * @param string $rulename The name of the rule
     * @param \grade_item|null $gradeitem The grade_item object, null if it has not been created yet
     * @param int|null $gradingruleid The id in the grading_rules table, null if it has not been created yet
     * @return rule_interface
     */
    public static function create(string $rulename, ?\grade_item $gradeitem, ?int $gradingruleid): rule_interface;
}
