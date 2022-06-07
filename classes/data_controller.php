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
 * @package customfield_duration
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2021, Andrew Hancox
 */

namespace customfield_duration;

defined('MOODLE_INTERNAL') || die;

class data_controller extends \core_customfield\data_controller {

    /**
     * Return the name of the field where the information is stored
     * @return string
     */
    public function datafield(): string {
        return 'intvalue';
    }

    /**
     * Add fields for editing data of a date field on a context.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform) {
        $field = $this->get_field();
        $config = $field->get('configdata');
        $elementname = $this->get_form_element_name();

        $mform->addElement('duration', $elementname, $this->get_field()->get_formatted_name(), ['defaultunit' => $config['defaultunit'] ?? HOURSECS]);

        $mform->setDefault($elementname, $field->get_configdata_property('default'));

        if ($field->get_configdata_property('required')) {
            $mform->addRule($elementname, null, 'required', null, 'client');
        }
    }

    /**
     * Returns the default value as it would be stored in the database (not in human-readable format).
     *
     * @return mixed
     */
    public function get_default_value() {
        return 0;
    }

    /**
     * Returns value in a human-readable format
     *
     * @return mixed|null value or null if empty
     */
    public function export_value() {
        $value = $this->get_value();

        if (empty($value)) {
            return null;
        }

        return format_time($value);
    }
}
