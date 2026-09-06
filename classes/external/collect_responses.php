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
 * External service for collecting responses from client nodes.
 *
 * @package    catquizcentralhub_host
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace catquizcentralhub_host\external;

use catquizcentralhub_host\event\responses_added;
use catquizcentralhub_host\response\response_handler;
use core\context\system as context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use UnexpectedValueException;
use catquizcentralhub_host\local\hub_policy;

/**
 * External service for collecting responses from client nodes.
 *
 * @package    catquizcentralhub_host
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect_responses extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'jsondata' => new external_value(PARAM_RAW, 'JSON encoded array of response data'),
            'sourceurl' => new external_value(PARAM_TEXT, 'The source URL as provided by the client'),
        ]);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status of the submission'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'added' => new external_value(PARAM_INT, 'Responses that were newly added'),
            'skipped' => new external_value(PARAM_INT, 'Responses that were skipped because they were already present'),
            'errors' => new external_multiple_structure(
                new external_single_structure([
                    'questionhash' => new external_value(PARAM_TEXT, 'Hash of the question'),
                    'attempthash' => new external_value(PARAM_INT, 'The attempt hash'),
                    'message' => new external_value(PARAM_TEXT, 'Message that describes the error'),
                ])
            ),
        ]);
    }

    /**
     * Collect responses submitted by a client node.
     *
     * @param string $jsondata The response data as json-encoded string
     * @param string $sourceurl The source url
     * @return array The status and processed responses
     */
    public static function execute($jsondata, $sourceurl) {
        global $USER;

        // With hub operation switched off this instance neither accepts
        // nor hands out data. Hiding the buttons is not a security boundary - the web
        // service stays reachable for anyone able to call it.
        hub_policy::require_enabled();

        // The scale allowlist does not apply here, and that is deliberate rather than
        // an omission: the payload identifies questions by hash and carries no scale,
        // so there is nothing to match against central_scale_labels. Adding a check
        // that always passes would look like protection while providing none. What
        // guards this endpoint is the hub switch above and the capability below.
        //
        // Accepting remote response data is an administrative act. The
        // entry in db/services.php is metadata and is not checked at run time, so
        // without this any authenticated caller could write into the hub.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $decodeddata = json_decode($jsondata, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new invalid_parameter_exception('Invalid JSON data provided: ' . json_last_error_msg());
        }

        foreach ($decodeddata as $response) {
            if (!isset($response['questionhash'], $response['attemptid'], $response['fraction'], $response['ability'])) {
                throw new invalid_parameter_exception('Each response must contain questionhash, attemptid, fraction and ability');
            }
        }

        $context = context_system::instance();
        self::validate_context($context);

        $errors = [];
        $overallstatus = true;
        $stored = 0;
        $skipped = 0;

        foreach ($decodeddata as $response) {
            try {
                if (!is_numeric($response['fraction'])) {
                    throw new invalid_parameter_exception('Invalid fraction value for question ' . $response['questionhash']);
                }

                if (!is_numeric($response['attemptid'])) {
                    throw new invalid_parameter_exception('Invalid attemptid for question ' . $response['questionhash']);
                }

                $res = response_handler::store_response(
                    $response['questionhash'],
                    $response['fraction'],
                    $response['attemptid'],
                    $sourceurl
                );
                $success = $res != response_handler::RESPONSE_ERROR;

                switch ($res) {
                    case response_handler::RESPONSE_ERROR:
                        $errors[] = [
                            'questionhash' => $response['questionhash'],
                            'attempthash' => $response['attemptid'],
                            'message' => 'Response error',
                        ];
                        break;
                    case response_handler::RESPONSE_STORED:
                        $stored++;
                        break;
                    case response_handler::RESPONSE_EXISTS:
                        $skipped++;
                        break;
                    default:
                        throw new UnexpectedValueException(sprintf('Unexpected return code %s from store_response', $res));
                }
                if (!$success) {
                    $overallstatus = false;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'questionhash' => $response['questionhash'],
                    'attempthash' => $response['attemptid'],
                    'message' => $e->getMessage(),
                ];
                $overallstatus = false;
            }
        }

        $event = responses_added::create([
            'context' => context_system::instance(),
            'userid' => $USER->id,
            'other' => [
                'sourceurl' => $sourceurl,
                'added' => $stored,
                'skipped' => $skipped,
                'errors' => count($errors),
            ],
        ]);
        $event->trigger();

        return [
            'status' => $overallstatus,
            'message' => $overallstatus ? 'All responses processed successfully' : 'Some responses failed',
            'added' => $stored,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
