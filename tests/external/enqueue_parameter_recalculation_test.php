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
 * Tests for the enqueue_parameter_recalculation external function.
 *
 * @package    catquizcentralhub_host
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catquizcentralhub_host\external;

use advanced_testcase;
use catquizcentralhub_host\task\adhoc_recalculate_remote_item_parameters;

/**
 * Tests for enqueue_parameter_recalculation external function.
 *
 * @package    catquizcentralhub_host
 * @covers     \catquizcentralhub_host\external\enqueue_parameter_recalculation
 */
final class enqueue_parameter_recalculation_test extends advanced_testcase {
    /**
     * Scale released for this hub, created in setUp().
     * @var int
     */
    protected int $scaleid = 0;
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Issue #65: the switch is a server-side gate now. These tests ran with it
        // off and still expected the service to work - which is exactly the state
        // the issue calls out as written into the tests. Enabling it here makes the
        // positive path explicit instead of implicit.
        set_config('enable_sync_as_hub', 1, 'catquizcentralhub_host');

        // Issue #65: the scale allowlist is enforced server-side now, and it resolves
        // the id to a label. A scale that exists and is released is therefore part of
        // the fixture rather than an assumed id - the previous version passed 1 and
        // relied on nothing checking it.
        global $DB;
        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Enqueue context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $this->scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => 'Released scale',
            'label' => 'K1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        set_config('central_scale_labels', "K1\n", 'catquizcentralhub_host');
        $this->setAdminUser();
    }

    public function test_execute_returns_success_true(): void {
        $result = enqueue_parameter_recalculation::execute($this->scaleid);
        $this->assertTrue($result['success']);
    }

    public function test_execute_returns_message_string(): void {
        $result = enqueue_parameter_recalculation::execute($this->scaleid);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsString($result['message']);
    }

    public function test_execute_queues_adhoc_task(): void {
        global $DB;
        $before = $DB->count_records('task_adhoc');
        enqueue_parameter_recalculation::execute($this->scaleid);
        $this->assertSame($before + 1, $DB->count_records('task_adhoc'));
    }

    public function test_execute_task_custom_data_contains_scaleid(): void {
        enqueue_parameter_recalculation::execute($this->scaleid);
        $tasks = \core\task\manager::get_adhoc_tasks(adhoc_recalculate_remote_item_parameters::class);
        $this->assertNotEmpty($tasks);
        $task = reset($tasks);
        $data = $task->get_custom_data();
        $this->assertSame($this->scaleid, $data->scaleid);
    }

    public function test_execute_result_contains_required_keys(): void {
        $result = enqueue_parameter_recalculation::execute($this->scaleid);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }
}
