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
 * Interface for grading rules
 *
 * @package     core
 * @copyright   2019 Monash University (http://www.monash.edu)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\grade\rule;

defined('MOODLE_INTERNAL') || die();

use MoodleQuickForm;

/**
 * Interface for grading rules
 *
 * @package     core
 * @copyright   2019 Monash University (http://www.monash.edu)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface rule_interface {

    /**
     * Handles instantiating a rule.
     *
     * @param \grade_item|null $gradeitem The grade_item object, null if it has not been created yet
     * @param int|null $gradingruleid The id in the grading_rules table, null if it has not been created yet
     * @return rule_interface
     */
    public static function create(?\grade_item $gradeitem, ?int $gradingruleid): rule_interface;

    /**
     * Get the name of the plugin.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Whether or not this rule is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Modify final grade.
     *
     * @param int $userid
     * @param float $currentvalue
     * @return float
     */
    public function final_grade_modifier(int $userid, float $currentvalue): float;

    /**
     * Modify letter.
     *
     * @param float $value
     * @param int $userid
     * @param string $currentletter
     * @return string
     */
    public function letter_modifier(float $value, int $userid, string $currentletter): string;

    /**
     * Edit the grade item edit form.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function edit_form_hook(MoodleQuickForm &$mform): void;

    /**
     * Process the form.
     *
     * @param \grade_item $gradeitem
     * @param \stdClass $data
     * @return void
     */
    public function process_form(\grade_item $gradeitem, \stdClass &$data): void;

    /**
     * Save the grade rule.
     *
     * @return void
     */
    public function save(): void;

    /**
     * Delete the grade rule.
     *
     * @return void
     */
    public function delete(): void;

    /**
     * Process the grade item recursively.
     *
     * @return void
     */
    public function recurse(): void;

    /**
     * Whether or not grade item needs updating.
     *
     * @return bool
     */
    public function needs_update(): bool;
}
