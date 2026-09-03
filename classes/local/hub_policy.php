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
 * Server-side policy for hub operation (issue #65).
 *
 * @package    catquizcentralhub_host
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catquizcentralhub_host\local;

use moodle_exception;

/**
 * Decides whether this instance acts as a hub, and for which scales.
 *
 * Issue #65: enable_sync_as_hub was read by the settings page and the template only.
 * The host endpoints accepted remote responses, handed out item parameters and queued
 * recalculations regardless - so switching the hub off removed the buttons while the
 * web services kept working for anyone able to call them.
 *
 * The counterpart to sync_policy on the client side, and the single place that
 * answers whether hub operation is on and which scales it covers.
 *
 * @package    catquizcentralhub_host
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_policy {
    /**
     * Whether this instance acts as a hub.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return !empty(get_config('catquizcentralhub_host', 'enable_sync_as_hub'));
    }

    /**
     * Throws unless hub operation is enabled.
     *
     * @throws moodle_exception
     * @return void
     */
    public static function require_enabled(): void {
        if (!self::is_enabled()) {
            throw new moodle_exception('hubdisabled', 'catquizcentralhub_host');
        }
    }

    /**
     * Returns the scale labels this hub manages.
     *
     * The setting describes itself as "only these scales are managed by this hub
     * instance", so it is an allowlist and is enforced as one.
     *
     * @return string[]
     */
    public static function get_allowed_scale_labels(): array {
        $labels = (string) get_config('catquizcentralhub_host', 'central_scale_labels');

        return array_values(array_filter(array_map('trim', explode("\n", $labels))));
    }

    /**
     * Whether a scale label is covered by the allowlist.
     *
     * @param string $label
     * @return bool
     */
    public static function is_scale_allowed(string $label): bool {
        return in_array(trim($label), self::get_allowed_scale_labels(), true);
    }

    /**
     * Throws unless the scale is covered by the allowlist.
     *
     * @param string $label
     * @throws moodle_exception
     * @return void
     */
    public static function require_scale_allowed(string $label): void {
        if (!self::is_scale_allowed($label)) {
            throw new moodle_exception('scalenotmanaged', 'catquizcentralhub_host', '', $label);
        }
    }
}
