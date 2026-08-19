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
 * The responses_added event.
 *
 * @package catquizcentralhub_host
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catquizcentralhub_host\event;

use local_catquiz\event\catquiz_event_base;
use moodle_url;

/**
 * Fired when a client node submits new responses to this hub.
 *
 * @package catquizcentralhub_host
 * @copyright 2024 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class responses_added extends catquiz_event_base {
    /**
     * Init parameters.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('responses_added', 'catquizcentralhub_host');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        $data = $this->data;
        $other = $this->get_other_data();

        if (!$other) {
            return '';
        }

        $data['added'] = $other->added;
        $data['skipped'] = $other->skipped;
        $data['errors'] = $other->errors;
        $data['sourceurl'] = $other->sourceurl;

        return get_string('responses_added_desc', 'catquizcentralhub_host', $data);
    }

    /**
     * Get url.
     *
     * @return moodle_url
     */
    public function get_url() {
        return new moodle_url('');
    }
}
