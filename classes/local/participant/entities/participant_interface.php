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
 * Class representing all student and instructor course participants
 *
 * @package    local_booking
 * @author     Mustafa Hajjar (mustafahajjar@gmail.com)
 * @copyright  BAVirtual.co.uk © 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_booking\local\participant\entities;

defined('MOODLE_INTERNAL') || die();

interface participant_interface {

    /**
     * Get student's enrolment date.
     *
     * @return DateTime $enroldate  The enrolment date of the student.
     */
    public function get_enrol_date();

    /**
     * Suspends the student's enrolment to a course.
     *
     * @return bool             The result of the suspension action.
     */
    public function set_suspend_status();

    /**
     * Returns full username
     *
     * @param bool $alternate The additional alternate name
     * @return string  The full username with optional alternate info
     */
    public function get_fullname(bool $alternate = true);

    /**
     * Returns participant's simulator user field
     *
     * @return string   The participant callsign
     */
    public function get_simulator();

    /**
     * Returns pilot's callsign user field
     *
     * @return string   The participant callsign
     */
    public function get_callsign();
}
