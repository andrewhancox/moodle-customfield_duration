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

defined('MOODLE_INTERNAL') || die();

use customfield_duration\field_controller;
use customfield_duration\data_controller;

class customfield_duration_plugin_testcase extends advanced_testcase {

    /** @var stdClass[]  */
    private $courses = [];
    /** @var \core_customfield\category_controller */
    private $cfcat;
    /** @var \core_customfield\field_controller[] */
    private $cfields;
    /** @var \core_customfield\data_controller[] */
    private $cfdata;

    /**
     * Tests set up.
     */
    public function setUp() {
        $this->resetAfterTest();

        $this->cfcat = $this->get_generator()->create_category();

        $this->cfields[1] = $this->get_generator()->create_field(
            ['categoryid' => $this->cfcat->get('id'), 'shortname' => 'myfield1', 'type' => 'duration']);
        $this->cfields[2] = $this->get_generator()->create_field(
            ['categoryid' => $this->cfcat->get('id'), 'shortname' => 'myfield2', 'type' => 'duration',
                'configdata' => ['required' => 1, 'defaultunit' => DAYSECS]]);

        $this->courses[1] = $this->getDataGenerator()->create_course();
        $this->courses[2] = $this->getDataGenerator()->create_course();
        $this->courses[3] = $this->getDataGenerator()->create_course();

        $this->cfdata[1] = $this->get_generator()->add_instance_data($this->cfields[1], $this->courses[1]->id, 10 * DAYSECS);
        $this->cfdata[2] = $this->get_generator()->add_instance_data($this->cfields[1], $this->courses[2]->id, YEARSECS);

        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * Get generator
     * @return core_customfield_generator
     */
    protected function get_generator() : core_customfield_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_customfield');
    }

    /**
     * Test for initialising field and data controllers
     */
    public function test_initialise() {
        $f = \core_customfield\field_controller::create($this->cfields[1]->get('id'));
        $this->assertTrue($f instanceof field_controller);

        $f = \core_customfield\field_controller::create(0, (object)['type' => 'duration'], $this->cfcat);
        $this->assertTrue($f instanceof field_controller);

        $d = \core_customfield\data_controller::create($this->cfdata[1]->get('id'));
        $this->assertTrue($d instanceof data_controller);

        $d = \core_customfield\data_controller::create(0, null, $this->cfields[1]);
        $this->assertTrue($d instanceof data_controller);
    }

    /**
     * Test for configuration form functions
     *
     * Create a configuration form and submit it with the same values as in the field
     */
    public function test_config_form() {
        $submitdata = (array)$this->cfields[1]->to_record();
        $submitdata['configdata'] = $this->cfields[1]->get('configdata');

        \core_customfield\field_config_form::mock_submit($submitdata, []);
        $handler = $this->cfcat->get_handler();
        $form = $handler->get_field_config_form($this->cfields[1]);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $handler->save_field_configuration($this->cfields[1], $data);
    }

    /**
     * Test for instance form functions
     */
    public function test_instance_form() {
        global $CFG;
        require_once($CFG->dirroot . '/customfield/tests/fixtures/test_instance_form.php');
        $this->setAdminUser();
        $handler = $this->cfcat->get_handler();

        // First try to submit without required field.
        $submitdata = (array)$this->courses[1];
        core_customfield_test_instance_form::mock_submit($submitdata, []);
        $form = new core_customfield_test_instance_form('POST',
            ['handler' => $handler, 'instance' => $this->courses[1]]);
        $this->assertFalse($form->is_validated());

        // Now with required field.
        $submitdata['customfield_myfield2'] = time();
        core_customfield_test_instance_form::mock_submit($submitdata, []);
        $form = new core_customfield_test_instance_form('POST',
            ['handler' => $handler, 'instance' => $this->courses[1]]);
        $this->assertTrue($form->is_validated());

        $data = $form->get_data();
        $this->assertEmpty($data->customfield_myfield1);
        $this->assertNotEmpty($data->customfield_myfield2);
        $handler->instance_form_save($data);
    }

    /**
     * Test for data_controller::get_value and export_value
     */
    public function test_get_export_value() {
        $this->assertEquals(10 * DAYSECS, $this->cfdata[1]->get_value());
        $this->assertEquals('10 days', $this->cfdata[1]->export_value());

        // Field without data.
        $d = core_customfield\data_controller::create(0, null, $this->cfields[2]);
        $this->assertEquals(0, $d->get_value());
        $this->assertEquals(null, $d->export_value());
    }

    /**
     * Data provider for {@see test_parse_value}
     *
     * @return array
     */
    public function parse_value_provider() : array {
        return [
            // Valid times.
            ['2 days', 2 * DAYSECS],
            ['1 year', YEARSECS],
            // Invalid times.
            ['ZZZZZ', 0],
            ['202-04-01', 0],
            ['2019-15-15', 0],
        ];
    }
    /**
     * Test field parse_value method
     *
     * @param string $value
     * @param int $expected
     * @return void
     *
     * @dataProvider parse_value_provider
     */
    public function test_parse_value(string $value, int $expected) {
        $this->assertSame($expected, $this->cfields[1]->parse_value($value));
    }

    /**
     * Deleting fields and data
     */
    public function test_delete() {
        $this->cfcat->get_handler()->delete_all();
    }
}