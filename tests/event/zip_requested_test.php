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
 * Tests for the ZIP request event.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\event;

use block_download_certificates\local\zip_tasks;

/**
 * The event names the task row and the request type, in the requester's user context, and is
 * refused without either.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\event\zip_requested
 */
final class zip_requested_test extends \advanced_testcase {
    /**
     * The event's data, name and description.
     */
    public function test_event_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = \context_user::instance((int) $user->id);

        $sink = $this->redirectEvents();
        zip_requested::create(['objectid' => 42, 'context' => $context, 'other' => ['type' => 'course']])->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(zip_requested::class, $event);
        $this->assertSame(42, (int) $event->objectid);
        $this->assertSame(zip_tasks::TABLE, $event->objecttable);
        $this->assertSame('c', $event->crud);
        $this->assertSame($context->id, $event->get_context()->id);
        $this->assertSame(get_string('event_zip_requested', 'block_download_certificates'), $event::get_name());
        $this->assertStringContainsString("'{$user->id}'", $event->get_description());
        $this->assertStringContainsString("'42'", $event->get_description());
        $this->assertStringContainsString("'course'", $event->get_description());
    }

    /**
     * The related user and the request's ids reach the event and its description; an event
     * without them says nothing of the kind.
     */
    public function test_event_names_what_was_requested(): void {
        $this->resetAfterTest();
        $context = \context_user::instance((int) $this->getDataGenerator()->create_user()->id);

        $event = zip_requested::create([
            'objectid' => 7,
            'context' => $context,
            'relateduserid' => 31,
            'other' => ['type' => 'user', 'courseid' => 12, 'cohortid' => 9],
        ]);
        $this->assertSame(31, (int) $event->relateduserid);
        $this->assertStringContainsString("user with id '31'", $event->get_description());
        $this->assertStringContainsString("course with id '12'", $event->get_description());
        $this->assertStringContainsString("cohort with id '9'", $event->get_description());

        // The web service passes the key even when empty: left out, core would fill in the context's user.
        $plain = zip_requested::create([
            'objectid' => 7,
            'context' => $context,
            'relateduserid' => null,
            'other' => ['type' => 'all'],
        ]);
        $this->assertEmpty($plain->relateduserid);
        $this->assertStringNotContainsString('for the', $plain->get_description());
    }

    /**
     * An event without its type or its object is refused; with both it is created.
     */
    public function test_event_needs_object_and_type(): void {
        $this->resetAfterTest();
        $context = \context_user::instance((int) $this->getDataGenerator()->create_user()->id);

        $this->assertInstanceOf(
            zip_requested::class,
            zip_requested::create(['objectid' => 1, 'context' => $context, 'other' => ['type' => 'all']])
        );
        foreach ([['objectid' => 1, 'context' => $context], ['context' => $context, 'other' => ['type' => 'all']]] as $data) {
            try {
                zip_requested::create($data);
                $this->fail('The event was created from ' . json_encode(array_keys($data)));
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('must be set', $e->getMessage());
            }
        }
    }
}
