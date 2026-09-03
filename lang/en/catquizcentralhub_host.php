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
 * Language strings for catquizcentralhub_host.
 *
 * @package     catquizcentralhub_host
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['centralscalelabels'] = 'Central scale labels';
$string['centralscalelabelsdesc'] = 'Enter one scale label per line. Only these scales will be managed by this hub instance.';
$string['enablesyncashub'] = 'Instance acts as hub';
$string['enablesyncashubdesc'] = 'When activated, client nodes can submit responses and fetch calculated item parameters from this instance.';
$string['hubdisabled'] = 'This instance is not configured to act as a central hub.';
$string['pluginname'] = 'CatQuiz Central Hub (Host)';
$string['questionnotfound'] = 'Question was not found';
$string['responses_added'] = 'New responses were submitted';
$string['responses_added_desc'] = '{$a->sourceurl} submitted new responses. {$a->added} new responses were added, {$a->skipped} '
    . 'were skipped and {$a->errors} errors occurred';
$string['scalenotmanaged'] = 'The scale "{$a}" is not managed by this hub instance.';
$string['taskqueued'] = 'Remote calculation task has been queued and will run shortly.';
$string['taskrecalculateremoteparameters'] = 'Recalculate item parameters from remote responses';
