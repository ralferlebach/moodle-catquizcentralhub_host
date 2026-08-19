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
 * Tests for the collect_responses external function.
 *
 * @package    catquizcentralhub_host
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catquizcentralhub_host\external;

use advanced_testcase;
use invalid_parameter_exception;

/**
 * Tests for collect_responses external function.
 *
 * @package    catquizcentralhub_host
 * @covers     \catquizcentralhub_host\external\collect_responses
 */
final class collect_responses_test extends advanced_testcase {
    /** @var string source URL used in tests */
    private const SOURCE = 'https://node.example.com';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Builds a minimal valid response payload for JSON encoding.
     *
     * @param array $overrides values overriding the defaults
     * @return array the response payload
     */
    private function make_responses(array $overrides = []): array {
        return array_merge([
            'questionhash' => 'aabbccdd11223344aabbccdd11223344aabbccdd11223344aabbccdd11223344',
            'attemptid' => 1,
            'fraction' => 0.5,
            'ability' => 1.0,
        ], $overrides);
    }

    public function test_execute_with_single_valid_response_returns_added_one(): void {
        $result = collect_responses::execute(json_encode([$this->make_responses()]), self::SOURCE);
        $this->assertTrue($result['status']);
        $this->assertSame(1, $result['added']);
        $this->assertSame(0, $result['skipped']);
        $this->assertEmpty($result['errors']);
    }

    public function test_execute_inserts_record_into_rresponses_table(): void {
        global $DB;
        collect_responses::execute(json_encode([$this->make_responses()]), self::SOURCE);
        $this->assertSame(1, $DB->count_records('local_catquiz_rresponses'));
    }

    public function test_execute_with_multiple_valid_responses(): void {
        $responses = [
            $this->make_responses(['questionhash' => 'hash000000000000000000000000000000000000000000000000000000000001']),
            $this->make_responses(['questionhash' => 'hash000000000000000000000000000000000000000000000000000000000002']),
            $this->make_responses(['questionhash' => 'hash000000000000000000000000000000000000000000000000000000000003']),
        ];
        $result = collect_responses::execute(json_encode($responses), self::SOURCE);
        $this->assertSame(3, $result['added']);
    }

    public function test_execute_with_invalid_json_throws_invalid_parameter_exception(): void {
        $this->expectException(invalid_parameter_exception::class);
        collect_responses::execute('not valid json {{{', self::SOURCE);
    }

    public function test_execute_with_missing_questionhash_throws_exception(): void {
        $this->expectException(invalid_parameter_exception::class);
        $invalid = [['attemptid' => 1, 'fraction' => 0.5, 'ability' => 1.0]];
        collect_responses::execute(json_encode($invalid), self::SOURCE);
    }

    public function test_execute_with_missing_attemptid_throws_exception(): void {
        $this->expectException(invalid_parameter_exception::class);
        $invalid = [['questionhash' => 'abc', 'fraction' => 0.5, 'ability' => 1.0]];
        collect_responses::execute(json_encode($invalid), self::SOURCE);
    }

    public function test_execute_with_missing_fraction_throws_exception(): void {
        $this->expectException(invalid_parameter_exception::class);
        $invalid = [['questionhash' => 'abc', 'attemptid' => 1, 'ability' => 1.0]];
        collect_responses::execute(json_encode($invalid), self::SOURCE);
    }

    public function test_execute_with_missing_ability_throws_exception(): void {
        $this->expectException(invalid_parameter_exception::class);
        $invalid = [['questionhash' => 'abc', 'attemptid' => 1, 'fraction' => 0.5]];
        collect_responses::execute(json_encode($invalid), self::SOURCE);
    }

    public function test_execute_duplicate_response_is_skipped(): void {
        $payload = json_encode([$this->make_responses()]);
        collect_responses::execute($payload, self::SOURCE);
        $result = collect_responses::execute($payload, self::SOURCE);
        $this->assertSame(0, $result['added']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_execute_does_not_insert_duplicate(): void {
        global $DB;
        $payload = json_encode([$this->make_responses()]);
        collect_responses::execute($payload, self::SOURCE);
        collect_responses::execute($payload, self::SOURCE);
        $this->assertSame(1, $DB->count_records('local_catquiz_rresponses'));
    }

    public function test_execute_mixed_new_and_duplicate(): void {
        $first = json_encode([$this->make_responses(
            ['questionhash' => 'hash000000000000000000000000000000000000000000000000000000000001']
        )]);
        collect_responses::execute($first, self::SOURCE);

        $mixed = [
            $this->make_responses(['questionhash' => 'hash000000000000000000000000000000000000000000000000000000000001']),
            $this->make_responses(['questionhash' => 'hash000000000000000000000000000000000000000000000000000000000002']),
        ];
        $result = collect_responses::execute(json_encode($mixed), self::SOURCE);
        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_execute_with_non_numeric_fraction_returns_error(): void {
        $invalid = [$this->make_responses(['fraction' => 'notanumber'])];
        $result = collect_responses::execute(json_encode($invalid), self::SOURCE);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_execute_with_non_numeric_attemptid_returns_error(): void {
        $invalid = [$this->make_responses(['attemptid' => 'notanumber'])];
        $result = collect_responses::execute(json_encode($invalid), self::SOURCE);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_execute_with_empty_array_returns_zero_added(): void {
        $result = collect_responses::execute(json_encode([]), self::SOURCE);
        $this->assertTrue($result['status']);
        $this->assertSame(0, $result['added']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_execute_result_contains_required_keys(): void {
        $result = collect_responses::execute(json_encode([$this->make_responses()]), self::SOURCE);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('added', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_execute_same_hash_different_sources_both_stored(): void {
        global $DB;
        $payload = json_encode([$this->make_responses()]);
        collect_responses::execute($payload, 'https://node1.example.com');
        $result = collect_responses::execute($payload, 'https://node2.example.com');
        $this->assertSame(1, $result['added']);
        $this->assertSame(2, $DB->count_records('local_catquiz_rresponses'));
    }
}
