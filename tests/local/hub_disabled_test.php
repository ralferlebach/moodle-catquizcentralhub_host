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
 * Issue #65: the kill-switch and the allowlist fail closed on every path.
 *
 * @package    catquizcentralhub_host
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catquizcentralhub_host\local;

use advanced_testcase;

/**
 * Negative tests for hub operation.
 *
 * The dangerous state is not a misconfigured instance but a configured one that was
 * switched off again: host, token and scale labels remain, and every execution path
 * still exists. These tests assert what must *not* happen in that state - no remote
 * response is written, no parameter is handed out, no recalculation is queued.
 *
 * @package    catquizcentralhub_host
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \catquizcentralhub_host\local\hub_policy
 */
final class hub_disabled_test extends advanced_testcase {
    /**
     * Prepares a fully configured hub that is then switched off.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        // Deliberately complete: the point is that configuration alone must not be
        // enough to make the endpoints work.
        set_config('central_scale_labels', "K1\n", 'catquizcentralhub_host');
        set_config('enable_sync_as_hub', 0, 'catquizcentralhub_host');
    }

    /**
     * Accepting remote responses is refused while the hub is off.
     *
     * @return void
     */
    public function test_collect_responses_writes_nothing(): void {
        global $DB;

        $before = $DB->count_records('local_catquiz_attempts');

        try {
            \catquizcentralhub_host\external\collect_responses::execute('[]', 'https://node.invalid');
            $this->fail('A disabled hub must not accept remote responses.');
        } catch (\moodle_exception $e) {
            $this->assertSame(
                $before,
                $DB->count_records('local_catquiz_attempts'),
                'A refused call must not have written anything.'
            );
        }
    }

    /**
     * Handing out item parameters is refused while the hub is off.
     *
     * @return void
     */
    public function test_distribute_parameters_hands_out_nothing(): void {
        $this->expectException(\moodle_exception::class);
        \catquizcentralhub_host\external\distribute_parameters::execute('K1');
    }

    /**
     * Queueing a recalculation is refused while the hub is off.
     *
     * @return void
     */
    public function test_enqueue_recalculation_queues_nothing(): void {
        global $DB;

        $before = $DB->count_records('task_adhoc');

        try {
            \catquizcentralhub_host\external\enqueue_parameter_recalculation::execute(1);
            $this->fail('A disabled hub must not queue work.');
        } catch (\moodle_exception $e) {
            $this->assertSame(
                $before,
                $DB->count_records('task_adhoc'),
                'A refused call must leave the task queue untouched.'
            );
        }
    }

    /**
     * A scale outside the allowlist is refused even with the hub enabled.
     *
     * The allowlist is a second, independent gate: switching the hub on releases the
     * instance, not every scale on it.
     *
     * @return void
     */
    public function test_scale_outside_the_allowlist_fails_closed(): void {
        global $DB;

        set_config('enable_sync_as_hub', 1, 'catquizcentralhub_host');

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Allowlist context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => 'Not released',
            'label' => 'K9',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $before = $DB->count_records('task_adhoc');

        try {
            \catquizcentralhub_host\external\enqueue_parameter_recalculation::execute($scaleid);
            $this->fail('A scale outside central_scale_labels must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame($before, $DB->count_records('task_adhoc'));
        }
    }

    /**
     * A scale that cannot be resolved is refused rather than allowed.
     *
     * An unknown scale is not a released one - the check has to fail closed.
     *
     * @return void
     */
    public function test_unknown_scale_fails_closed(): void {
        set_config('enable_sync_as_hub', 1, 'catquizcentralhub_host');

        $this->expectException(\moodle_exception::class);
        hub_policy::require_scale_id_allowed(999999);
    }
}
