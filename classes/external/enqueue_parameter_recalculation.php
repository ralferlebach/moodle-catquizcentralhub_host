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
 * External API for enqueuing parameter recalculation.
 *
 * @package    catquizcentralhub_host
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catquizcentralhub_host\external;

use catquizcentralhub_host\task\adhoc_recalculate_remote_item_parameters;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core\task\manager;
use catquizcentralhub_host\local\hub_policy;

/**
 * External API class for enqueuing parameter recalculation.
 */
class enqueue_parameter_recalculation extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scaleid' => new external_value(PARAM_INT, 'Scale ID'),
        ]);
    }

    /**
     * Queues an adhoc task to recalculate remote item parameters.
     *
     * @param int $scaleid The ID of the scale to recalculate
     * @return array Array containing success status and message
     */
    public static function execute(int $scaleid): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), ['scaleid' => $scaleid]);

        // Issue #65: with hub operation switched off this instance neither accepts
        // nor hands out data. Hiding the buttons is not a security boundary - the web
        // service stays reachable for anyone able to call it.
        hub_policy::require_enabled();

        $task = new adhoc_recalculate_remote_item_parameters();
        $task->set_custom_data(['scaleid' => $scaleid, 'userid' => $USER->id]);
        manager::queue_adhoc_task($task);

        return [
            'success' => true,
            'message' => get_string('taskqueued', 'catquizcentralhub_host'),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Task queued successfully'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
