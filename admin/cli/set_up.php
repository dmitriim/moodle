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
 * CLI script to initial set up enrolment configuration.
 *
 * Assumptions:
 *  - Moodle version is 4.4
 *  - tool_dynamic_cohorts is installed
 *  - profile_autocomplete is installed
 *  - there is only on level of categories
 *  - this script can only be run once.
 *
 * This script creates custom field category and custom fields to store courses,
 * categories, tags and unenrolment date.
 * Then it goes through all categories, courses and tags and creates related cohorts.
 * For each cohort it creates a rule for dynamic cohorts plugin so users with matching
 * related fields can be added to cohort. Then it goes through all courses and
 * adds cohort sync enrolment instances: one for course cohort, one for category cohort
 * and if a course have tags, then one for each tag related cohort.
 *
 * Then, once a user us created/updated, based on data in custom fields he would be
 * added to one of cohorts which will enroll the user to all related courses.
 *
 * @package    core
 * @copyright  2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dynamic_cohorts\cohort_manager;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;

define('CLI_SCRIPT', true);

const SETUP_PROFILE_CATEGORY = 'Profile field';
const SETUP_FIELD_COURSE = 'course';
const SETUP_FIELD_CATEGORY = 'category';
const SETUP_FIELD_TAG = 'tag';
const SETUP_FIELD_ENROLLED_UNTIL = 'enrolleduntil';
const SETUP_STUDENT_ROLE = 'student';
const SETUP_COURSE_NAME = 'fullname';

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/profile/definelib.php');

$help = "Command line tool to set up custom enrolment functionality. 
The script will clean up before execution.

Options:
    -h --help      Print this help.
    --run          Execute Set up. If this option is not set, then the script will be run in a dry mode.
    --onlycleanup  Only cleans up: deletes all cohort enrolments, all cohorts, all conditions and rules 
                   as well as custom profile fields.

Usage:
    # php set_up.php  --run
";

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'run' => false,
    'onlycleanup' => false,
], [
    'h' => 'help'
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($help);
    exit(0);
}

/**
 * A helper function to add custom profile field.
 *
 * @param string $name Field name.
 * @param string $shortname Field shortname.
 * @param string $datatype Field data type.
 * @param array $extras Extra data.
 *
 * @return int ID of the record.
 */
function set_up_add_user_profile_field(string $name, string $shortname, string $datatype, array $extras = []): int {
    global $DB;

    $data = new \stdClass();
    $data->shortname = $shortname;
    $data->datatype = $datatype;
    $data->name = $name;
    $data->required = false;
    $data->locked = false;
    $data->forceunique = false;
    $data->signup = false;
    $data->visible = '0';
    $data->categoryid = 1;

    foreach ($extras as $name => $value) {
        $data->{$name} = $value;
    }

    return $DB->insert_record('user_info_field', $data);
}

/**
 * Helper method to add enrolment method to a course.
 *
 * @param stdClass $course Course.
 * @param stdClass $cohort Cohort.
 * @param array $options Pass in CLI options.
 *
 * @return void
 */
function set_up_add_enrolment_method(stdClass $course, stdClass $cohort, array $options): void {
    global $DB;

    $studentrole = $DB->get_record('role', ['shortname' => SETUP_STUDENT_ROLE]);

    $fields = [
        'customint1' => $cohort->id,
        'roleid' => $studentrole->id,
        'courseid' => $course->id,
    ];

    if (!$DB->record_exists('enrol', $fields)) {
        if ($options['run']) {
            $enrol = enrol_get_plugin('cohort');
            $enrol->add_instance($course, $fields);
            cli_writeln("Added enrolment method for cohort ID $cohort->id to course ID $course->id");
        } else {
            cli_writeln("Will add enrolment method for cohort ID $cohort->id to course ID $course->id");
        }
    } else {
        cli_writeln("Course ID $course->id already have enrolment method for cohort ID $cohort->id. Skipping.");
    }
}

/**
 * A helper method to set up rule for given cohort.
 *
 * @param stdClass $cohort Cohort.
 * @param string $fieldshortname Related profile field shortname.
 * @param array $options Pass in CLI options.
 *
 * @return void
 */
function set_up_add_rule(stdClass $cohort, string $fieldshortname, array $options): void {
    if ($options['run']) {
        if (!rule::get_record(['cohortid' => $cohort->id])) {
            cohort_manager::manage_cohort($cohort->id);
            $rule = new rule(0, (object)[
                'name' => $cohort->name,
                'cohortid' => $cohort->id,
                'description' => $cohort->description,
            ]);
            $rule->save();

            $condition = condition_base::get_instance(0, (object)[
                'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile',
            ]);

            $fieldname = 'profile_field_' . $fieldshortname;
            $condition->set_config_data([
                'profilefield' => $fieldname,
                $fieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
                $fieldname . '_value' => $cohort->name,
            ]);
            $condition->get_record()->set('ruleid', $rule->get('id'));
            $condition->get_record()->set('sortorder', 0);
            $condition->get_record()->save();

            $condition = condition_base::get_instance(0, (object)[
                'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile',
            ]);

            $fieldname = 'profile_field_' . SETUP_FIELD_ENROLLED_UNTIL;
            $condition->set_config_data([
                'profilefield' => $fieldname,
                $fieldname . '_operator' => condition_base::DATE_IN_THE_FUTURE,
                $fieldname . '_value' => 0,
            ]);
            $condition->get_record()->set('ruleid', $rule->get('id'));
            $condition->get_record()->set('sortorder', 0);
            $condition->get_record()->save();

            $rule->set('enabled', 1);
            $rule->save();

            cli_writeln("Created rule for cohort '$cohort->name'.");
        } else {
            cli_writeln("Rule for $cohort->id already exists. Skipping.");
        }
    } else {
        cli_writeln("Will create rule for cohort '$cohort->name'.");
    }
}

/**
 * Add cohort.
 *
 * @param stdClass $cohort Cohort.
 * @param array $options Pass in CLI options.
 *
 * @return void
 */
function set_up_add_cohort(stdClass $cohort, array $options): void {
    global $DB;
    if (!$existingcohort = $DB->get_record('cohort', ['name' => $cohort->name])) {
        if ($options['run']) {
            cohort_add_cohort($cohort);
            cli_writeln("Created cohort '$cohort->name'.");
        } else {
            cli_writeln("Will create cohort '$cohort->name'.");
        }
    } else {
        cli_writeln("Cohort '$cohort->name' already exists. Skipping.");
        $cohort->id = $existingcohort->id;
    }
}

/**
 * Gets a list of tags related to courses.
 *
 * @return array
 */

/**
 * Gets a list of tags related to courses.
 *
 * @param int $courseid Optional to filter by course ID.
 * @return array
 */
function set_up_get_course_tags(int $courseid = 0): array {
    global $DB;

    $params = [];
    $where = '';
    if (!empty($courseid)) {
        $where = " AND ti.itemid = ?";
        $params[] = $courseid;
    }

    $sql = "SELECT DISTINCT t.rawname  
              FROM {tag} t
              JOIN {tag_instance} ti ON t.id = ti.tagid
             WHERE ti.itemtype = 'course' $where ORDER BY t.rawname";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Returns a list of courses.
 *
 * @return array
 */
function set_up_get_courses(): array {
    global $DB;

    return $DB->get_records('course', ['visible' => 1], SETUP_COURSE_NAME);;
}

/**
 * Get categories.
 *
 * @return array
 */
function set_up_get_categories(): array {
    global $DB;

    return $DB->get_records('course_categories', ['visible' => 1], 'name');
}

/**
 * Clean up cohort enrolments.
 */
function set_up_delete_enrolments(): void {
    global $DB;

    $instances = $DB->get_records('enrol', ['enrol' => 'cohort']);

    foreach ($instances as $instance) {
        $cohortplugin = enrol_get_plugin('cohort');
        $cohortplugin->delete_instance($instance);
    }
}

/**
 * Clean up rules and conditions.
 */
function set_up_delete_rules_and_conditions(): void {
    global $DB;

    $DB->execute("UPDATE {cohort} SET component = '' WHERE component = :component", [
        'component' => 'tool_dynamic_cohorts',
    ]);
    $DB->delete_records('tool_dynamic_cohorts_c');
    $DB->delete_records('tool_dynamic_cohorts');
}

/**
 * Delete cohorts.
 */
function set_up_delete_cohorts(): void {
    foreach (cohort_get_all_cohorts(0, 0)['cohorts'] as $cohort) {
        cohort_delete_cohort($cohort);
    }
}

/**
 * Delete custom profile fields.
 */
function set_up_delete_custom_fields(): void {
    global $DB;

    $shortnames = ['category', 'course', 'tag', 'enrolleduntil'];

    foreach ($shortnames as $shortname) {
        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if ($field) {
            profile_delete_field($field->id);
        }
    }
}

// We want to rollback if anything exploded.
$transaction = $DB->start_delegated_transaction();

try {
    // Cleaning up stuff.
    if ($options['run']) {
        set_up_delete_enrolments();
        cli_writeln("Deleted all cohort enrolments");
    } else {
        cli_writeln("Will deleted all cohort enrolments");
    }

    if ($options['run']) {
        set_up_delete_rules_and_conditions();
        cli_writeln("Deleted all dynamic cohorts rules and conditions");
    } else {
        cli_writeln("Will deleted all dynamic cohorts rules and conditions");
    }

    if ($options['run']) {
        set_up_delete_cohorts();
        cli_writeln("Deleted all cohorts");
    } else {
        cli_writeln("Will deleted all cohorts");
    }

    if ($options['run']) {
        set_up_delete_custom_fields();
        cli_writeln("Deleted required custom profile fields");
    } else {
        cli_writeln("Will deleted required custom profile fields");
    }

    if (!$options['onlycleanup']) {
        // Create custom profile fields category.
        $profilefieldcategory = $DB->get_record('user_info_category', ['name' => SETUP_PROFILE_CATEGORY]);
        if (empty($profilefieldcategory)) {
            $profilefieldcategory = new stdClass();
            $profilefieldcategory->name = SETUP_PROFILE_CATEGORY;
            if ($options['run']) {
                $profilefieldcategory->id = $DB->insert_record('user_info_category', $profilefieldcategory);
                cli_writeln("Created profile field category with name '{$profilefieldcategory->name}'");
            } else {
                cli_writeln("Will create profile field category with name '{$profilefieldcategory->name}'");
            }
        } else {
            cli_writeln("Profile field category with name '{$profilefieldcategory->name}' already exists. Skipping.");
        }

        // Create autocomplete user profile field Course.
        $coursefield = $DB->get_record('user_info_field', ['shortname' => SETUP_FIELD_COURSE]);
        if (empty($coursefield)) {
            if ($options['run']) {
                // Fill the field options with a list of courses.
                $param1 = [];
                foreach (set_up_get_courses() as $course) {
                    if ($course->id == SITEID) {
                        continue;
                    }
                    $param1[$course->{SETUP_COURSE_NAME}] = $course->{SETUP_COURSE_NAME};
                }
                $extras = [];
                $extras['categoryid'] = $profilefieldcategory->id;
                $extras['param1'] = implode("\n", $param1);
                $extras['param2'] = 1; // Enable multi-selection.
                $extras['sortorder'] = 1;
                set_up_add_user_profile_field('Course', SETUP_FIELD_COURSE, 'autocomplete', $extras);
                cli_writeln("Created profile field with shortname '" . SETUP_FIELD_COURSE . "'");
            } else {
                cli_writeln("Will create profile field with shortname '" . SETUP_FIELD_COURSE . "'");
            }
        } else {
            cli_writeln("Profile field with shortname '" . SETUP_FIELD_COURSE . "' already exists. Skipping.");
        }

        // Create autocomplete user profile field Category.
        $categoryfield = $DB->get_record('user_info_field', ['shortname' => SETUP_FIELD_CATEGORY]);
        if (empty($categoryfield)) {
            if ($options['run']) {
                // Fill the field options with a list of categories.
                $param1 = [];
                foreach (set_up_get_categories() as $category) {
                    $param1[$category->name] = $category->name;
                }
                $extras = [];
                $extras['categoryid'] = $profilefieldcategory->id;
                $extras['param1'] = implode("\n", $param1);
                $extras['param2'] = 1; // Enable multi-selection.
                $extras['sortorder'] = 0;
                set_up_add_user_profile_field('Category', SETUP_FIELD_CATEGORY, 'autocomplete', $extras);
                cli_writeln("Created profile field with shortname '" . SETUP_FIELD_CATEGORY . "'");
            } else {
                cli_writeln("Will create profile field with shortname '" . SETUP_FIELD_CATEGORY . "'");
            }
        } else {
            cli_writeln("Profile field with shortname '" . SETUP_FIELD_CATEGORY . "' already exists. Skipping.");
        }

        // Create date user profile field active until.
        $enrolleduntilfield = $DB->get_record('user_info_field', ['shortname' => SETUP_FIELD_ENROLLED_UNTIL]);
        if (empty($enrolleduntilfield)) {
            if ($options['run']) {
                $extras = [];
                $extras['categoryid'] = $profilefieldcategory->id;
                $extras['param1'] = '2023'; // Min year.
                $extras['param2'] = '2050'; // Max year.
                $extras['sortorder'] = 3;
                set_up_add_user_profile_field('Keep enrolment until', SETUP_FIELD_ENROLLED_UNTIL, 'datetime', $extras);
                cli_writeln("Created profile field with shortname '" . SETUP_FIELD_ENROLLED_UNTIL . "'");
            } else {
                cli_writeln("Will create profile field with shortname '" . SETUP_FIELD_ENROLLED_UNTIL . "'");
            }
        } else {
            cli_writeln("Profile field with shortname '" . SETUP_FIELD_ENROLLED_UNTIL . "' already exists. Skipping.");
        }

        // Create autocomplete user profile field for tags.
        $tagfield = $DB->get_record('user_info_field', ['shortname' => SETUP_FIELD_TAG]);
        if (empty($tagfield)) {
            if ($options['run']) {
                // Fill the field options with a list of course related tags.
                $tags = set_up_get_course_tags();
                $param1 = [];
                foreach ($tags as $tag) {
                    $param1[$tag->rawname] = $tag->rawname;
                }
                $extras = [];
                $extras['categoryid'] = $profilefieldcategory->id;
                $extras['param1'] = implode("\n", $param1);
                $extras['param2'] = 1; // Enable multi-selection.
                $extras['sortorder'] = 2;
                set_up_add_user_profile_field('Tags', SETUP_FIELD_TAG, 'autocomplete', $extras);
                cli_writeln("Created profile field with shortname '" . SETUP_FIELD_TAG . "'");
            } else {
                cli_writeln("Will create profile field with shortname '" . SETUP_FIELD_TAG . "'");
            }
        } else {
            cli_writeln("Profile field with shortname '" . SETUP_FIELD_TAG . "' already exists. Skipping.");
        }

        // Go through all tags and create cohort for each tag.
        $tags = set_up_get_course_tags();
        foreach ($tags as $tag) {
            $cohort = new stdClass();
            $cohort->contextid = context_system::instance()->id;
            $cohort->name = $tag->rawname;
            $cohort->idnumber = $tag->rawname;
            $cohort->description = 'Tag related';
            set_up_add_cohort($cohort, $options);
            set_up_add_rule($cohort, SETUP_FIELD_TAG, $options);
        }

        // Go through all categories and for each category create a cohort.
        foreach (set_up_get_categories() as $category) {
            $cohort = new stdClass();
            $cohort->contextid = context_system::instance()->id;
            $cohort->name = $category->name;
            $cohort->idnumber = $category->name;
            $cohort->description = 'Category related';
            set_up_add_cohort($cohort, $options);
            set_up_add_rule($cohort, SETUP_FIELD_CATEGORY, $options);
        }

        // Go through each course from each category and create cohort.
        foreach (set_up_get_courses() as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $cohort = new stdClass();
            $cohort->contextid = context_system::instance()->id;
            $cohort->name = $course->{SETUP_COURSE_NAME};
            $cohort->idnumber = $course->{SETUP_COURSE_NAME};
            $cohort->description = 'Course related';
            set_up_add_cohort($cohort, $options);
            set_up_add_rule($cohort, SETUP_FIELD_COURSE, $options);
        }

        // Cohorts are set up. Let's manage course enrolment methods and rules.

        // Go through all courses:
        // - get related course cohort and add an enrolment method for that cohort.
        // - get related category cohort and add an enrolment method for that cohort.
        // - get all tags for the course and for each tag create an enrolment method.
        foreach (set_up_get_courses() as $course) {

            if ($course->id == SITEID) {
                continue;
            }

            // Course cohort.
            if ($cohort = $DB->get_record('cohort', ['name' => $course->{SETUP_COURSE_NAME}])) {
                set_up_add_enrolment_method($course, $cohort, $options);
            } else {
                if ($options['run']) {
                    cli_writeln("Course ID $course->id: cohort for course '$course->SETUP_COURSE_NAME}}' is not found. Skipping.");
                }
            }

            // Category cohort.
            $category = $DB->get_record('course_categories', ['id' => $course->category]);
            if ($cohort = $DB->get_record('cohort', ['name' => $category->name])) {
                set_up_add_enrolment_method($course, $cohort, $options);
            } else {
                if ($options['run']) {
                    cli_writeln("Course ID $course->id: cohort for category '$category->name' is not found. Skipping.");
                }
            }

            // Tags cohorts.
            foreach (set_up_get_course_tags($course->id) as $tag) {
                if ($cohort = $DB->get_record('cohort', ['name' => $tag->rawname])) {
                    set_up_add_enrolment_method($course, $cohort, $options);
                } else {
                    if ($options['run']) {
                        cli_writeln("Course ID $course->id: cohort for tag '$tag->rawname' is not found. Skipping.");
                    }
                }
            }
        }
    }

    // Finally commit everything.
    $transaction->allow_commit();
} catch (Exception $exception) {
    $transaction->rollback($exception);
    cli_error($exception->getMessage());
}

if ($options['run']) {
    purge_all_caches();
}
cli_writeln('Done');
exit(0);
