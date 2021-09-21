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
 * slot vault interface
 *
 * @package    local_booking
 * @copyright  2017 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_booking\local\slot\data_access;

defined('MOODLE_INTERNAL') || die();

use local_booking\local\slot\entities\slot;

/**
 * Interface for an slot vault class
 *
 * @copyright  2017 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface slot_vault_interface {

    /**
     * Get a based on its id
     *
     * @param int       $slot The id of the slot
     * @return slot     The slot object from the id
     */
    public function get_slot(int $slotid);

    /**
     * Get all slots for a user that fall on a specific year and week.
     *
     * @param int|null              $userid     slots for this user
     * @param int|null              $year       slots that fall in this year
     * @param int|null              $week       slots that fall in this week
     *
     * @return slot_interface[]     Array of slot_interfaces.
     */
    public function get_slots(
        $userid,
        $year = 0,
        $week = 0
    );

    /**
     * Saves the passed slot
     *
     * @param slot_interface $slot
     */
    public function save_slot(slot $slot);

    /**
     * Delete all slots for a user that fall on a specific year and week.
     *
     * @param int|null              $userid     slots for this user
     * @param int|null              $year       slots that fall in this year
     * @param int|null              $week       slots that fall in this week
     *
     * @return result               result
     */
    public function delete_slots(
        $course = 0,
        $year = 0,
        $week = 0,
        $userid = 0,
        $useredits = true
    );

    /**
     * Update the specified slot status and bookinginfo
     *
     * @param slot $slot The slot to be confirmed
     */
    public function confirm_slot(slot $slot, string $bookinginfo);

    /**
     * Get the date of the last posted availability slot
     *
     * @param int $courseid
     * @param int $studentid
     */
    public function get_last_posted_slot(int $courseid, int $studentid);
}
