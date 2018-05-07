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
 * Course role filter.
 *
 * @package   core_user
 * @category  user
 * @copyright 2018 Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot .'/user/filters/lib.php');

/**
 * User filter based on roles in a courses a course category.
 */
class user_filter_courserolesubcat extends user_filter_type {
    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     */
    public function __construct($name, $label, $advanced) {
        parent::__construct($name, $label, $advanced);
    }

    /**
     * Returns an array of available roles
     * @return array of availble roles
     */
    public function get_roles() {
        $context = context_system::instance();
        $roles = array(0 => get_string('anyrole', 'filters')) + get_default_enrol_roles($context);
        return $roles;
    }

    /**
     * Returns an array of course categories
     * @return array of course categories
     */
    public function get_course_categories() {
        global $CFG;
        require_once($CFG->libdir.'/coursecatlib.php');
        return array(0 => get_string('anycategory', 'filters')) + coursecat::make_categories_list();
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param moodleform $mform a MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        $objs = array();

        $objs['role'] = $mform->createElement('select', $this->_name  . '_rl', null, $this->get_roles());
        $objs['role']->setLabel(get_string('courserole', 'filters'));
        $objs['category'] = $mform->createElement('select', $this->_name  . '_ct', null, $this->get_course_categories());
        $objs['category']->setLabel(get_string('coursecategory', 'filters'));
        $objs['includesubcats'] = $mform->createElement('checkbox', $this->_name . '_sct', null);
        $objs['includesubcats']->setLabel('Include subcategories');
        $mform->disabledIf($this->_name . '_sct', $this->_name  . '_ct', 'eq', 0);

        $mform->addElement('group', $this->_name.'_grp', $this->_label, $objs, '', false);
        $mform->setType($this->_name, PARAM_TEXT);

        if ($this->_advanced) {
            $mform->setAdvanced($this->_name.'_grp');
        }
    }

    /**
     * Retrieves data from the form data
     * @param stdClass $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        $field = $this->_name;
        $role = $field .'_rl';
        $category = $field .'_ct';
        $includesubcats = $field .'_sct';

        if (empty($formdata->$role) and empty($formdata->$category)) {
            // Nothing selected.
            return false;
        }

        return array(
            'includesubcats' => (string)$formdata->$includesubcats,
            'roleid'=> (int)$formdata->$role,
            'categoryid' => (int)$formdata->$category
        );
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array sql string and $params
     */
    public function get_sql_filter($data) {
        global $DB;

        static $counter = 0;

        $pref = 'ex_crolesubcat'.($counter++).'_';

        $roleid = $data['roleid'];
        $categoryid = $data['categoryid'];
        $includesubcats = $data['includesubcats'];

        $params = array();

        if (empty($roleid) and empty($categoryid)) {
            return array('', $params);
        }

        $where = "b.contextlevel=50";

        if ($roleid) {
            $where .= " AND a.roleid = :{$pref}roleid";
            $params[$pref.'roleid'] = $roleid;
        }
        if ($categoryid) {
          if ($includesubcats) {
              $categories = coursecat::get($categoryid)->get_all_children_ids();
              array_push($categories, $categoryid);
              list($catsql, $catparams) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, $pref);
              $where .= ' AND c.category ' . $catsql;
              $params = array_merge($params, $catparams);
          } else {
              $where .= " AND c.category = :{$pref}categoryid";
              $params[$pref.'categoryid'] = $categoryid;
          }
        }

        return array("id IN (SELECT userid
                               FROM {role_assignments} a
                         INNER JOIN {context} b ON a.contextid=b.id
                         INNER JOIN {course} c ON b.instanceid=c.id
                              WHERE $where)", $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     *
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        global $DB;

        $roleid     = $data['roleid'];
        $categoryid = $data['categoryid'];
        $includesubcats = $data['includesubcats'];

        $a = new stdClass();
        $a->label = $this->_label;

        if ($roleid) {
            $role = $DB->get_record('role', array('id' => $roleid));
            $a->rolename = '"'.role_get_name($role).'"';
        } else {
            $a->rolename = get_string('anyrole', 'filters');
        }

        if ($categoryid) {
            $catname = $DB->get_field('course_categories', 'name', array('id' => $categoryid));
            $a->categoryname = '"'.format_string($catname).'"';

            if ($includesubcats) {
                $a->categoryname .= ' (including subcategories)';
            }

        } else {
            $a->categoryname = get_string('anycategory', 'filters');
        }

        $string = $a->label . ' is '  . $a->rolename . ' in All courses from ' . $a->categoryname;

        return $string;
    }
}
