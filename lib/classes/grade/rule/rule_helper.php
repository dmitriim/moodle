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
 * Class containing grading rule helper functions that handle the 'grading_rules' table read/writes.
 *
 * @package     core
 * @copyright   2019 Monash University (http://www.monash.edu)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\grade\rule;

defined('MOODLE_INTERNAL') || die();

use core\plugininfo\graderule;

/**
 * Contains grading rule helper functions that handle the 'grading_rules' table read/writes.
 *
 * @package     core
 * @copyright   2019 Monash University (http://www.monash.edu)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_helper {

    /**
     * Get all enabled rule names.
     *
     * @return string[]
     */
    public static function get_enabled_rules(): array {
        return graderule::get_enabled_plugins();
    }

    /**
     * Returns the rules for a grade item by ID.
     *
     * @param \grade_item|null $gradeitem
     * @param bool $onlyactive True if we only want to return rules that have been applied, or false to show all that can be used.
     * @return rule_interface[]
     */
    public static function get_rules_for_grade_item(?\grade_item $gradeitem, bool $onlyactive = false): array {
        global $DB;

        if (is_null($gradeitem)) {
            return self::get_blank_grade_rule_instances([]);
        }

        $rawrules = $DB->get_records('grading_rules', ['gradeitemid' => $gradeitem->id]);
        $rules = [];
        $rulesinuse = [];

        if (!empty($rawrules)) {
            foreach ($rawrules as $rawrule) {
                if (array_key_exists($rawrule->rulename, self::get_enabled_rules())) {
                    if (self::is_used_by_grade_item($gradeitem->id, $rawrule->rulename)) {
                        $rule = factory::create($rawrule->rulename, $gradeitem, $rawrule->id);
                        $rules[] = $rule;
                        $rulesinuse[] = $rule->get_name();
                    }
                }
            }
        }

        if (!$onlyactive) {
            $rules = array_merge($rules, self::get_blank_grade_rule_instances($rulesinuse));
        }

        self::sort_rules($rules);

        return $rules;
    }

    /**
     * Checks if a particular rule is used by a grade item.
     *
     * @param int $gradeitemid
     * @param string $rulename
     * @return bool
     */
    public static function is_used_by_grade_item(int $gradeitemid, string $rulename): bool {
        global $DB;

        return $DB->record_exists('grading_rules', ['gradeitemid' => $gradeitemid, 'rulename' => $rulename]);
    }

    /**
     * Save a rule association.
     *
     * @param int $gradeitemid
     * @param string $rulename
     * @return int The id in the grading_rules table
     */
    public static function save_rule_association(int $gradeitemid, string $rulename): int {
        global $DB;

        $record = new \stdClass();
        $record->gradeitemid = $gradeitemid;
        $record->rulename = $rulename;

        return $DB->insert_record('grading_rules', $record);
    }

    /**
     * Delete a rule association.
     *
     * @param int $gradingruleid The id in the grading_rules table
     * @return void
     */
    public static function delete_rule_association(int $gradingruleid): void {
        global $DB;

        $DB->delete_records('grading_rules', ['id' => $gradingruleid]);
    }

    /**
     * Load blank instances of said rules.
     *
     * @param array $rulesinuse The rules in use
     * @return array
     */
    private static function get_blank_grade_rule_instances(array $rulesinuse): array {
        $rulesnotinuse = array_diff(array_keys(self::get_enabled_rules()), $rulesinuse);

        $blankrules = [];
        if (!empty($rulesnotinuse)) {
            foreach ($rulesnotinuse as $rulename) {
                $blankrules[] = factory::create($rulename, null, null);
            }
        }

        return $blankrules;
    }

    /**
     * Sort the rule.
     *
     * @param rule_interface[] $rules
     * return void
     */
    private static function sort_rules(array &$rules): void {
        $order = self::get_enabled_rules();

        $comparator = function(rule_interface $a, rule_interface $b) use ($order) {
            $valuea = array_search($a->get_name(), array_keys($order));
            $valueb = array_search($b->get_name(), array_keys($order));

            if ($valuea < $valueb) {
                return -1;
            } else if ($valuea > $valueb) {
                return 1;
            } else {
                return 0;
            }
        };

        uasort($rules, $comparator);
    }
}
