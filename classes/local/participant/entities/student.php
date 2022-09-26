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
 * Class representing all student course participants
 *
 * @package    local_booking
 * @author     Mustafa Hajjar (mustafahajjar@gmail.com)
 * @copyright  BAVirtual.co.uk © 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_booking\local\participant\entities;

use local_booking\local\session\entities\priority;
use local_booking\local\session\entities\booking;
use local_booking\local\session\entities\grade;
use local_booking\local\slot\data_access\slot_vault;
use local_booking\local\slot\entities\slot;
use local_booking\local\subscriber\entities\subscriber;

class student extends participant {

    /**
     * Process user enrollments table name.
     */
    const SLOT_COLOR = '#00e676';

    /**
     * @var array $exercises The student exercise grades.
     */
    protected $exercises;

    /**
     * @var array $quizes The student quize grades.
     */
    protected $quizes;

    /**
     * @var array $grades The student exercise/quize grades.
     */
    protected $grades;

    /**
     * @var array $slots The student posted timeslots.
     */
    protected $slots;

    /**
     * @var string $slotcolor The slot color for the student slots.
     */
    protected $slotcolor;

    /**
     * @var int $total_posts The student's total number of availability posted.
     */
    protected $total_posts;

    /**
     * @var int $nextlesson The student's next upcoming lesson.
     */
    protected $nextlesson;

    /**
     * @var array $nextexercise The student's next exercise and section.
     */
    protected $nextexercise;

    /**
     * @var array $currentexercise The student's current exercise and section.
     */
    protected $currentexercise;

    /**
     * @var booking $activebooking The currently active booking for the student.
     */
    protected $activebooking;

    /**
     * @var DateTime $restrictiondate The student's end of restriction period date.
     */
    protected $restrictiondate;

    /**
     * @var priority $priority The student's priority object.
     */
    protected $priority;

    /**
     * @var boolean $lessonsecomplete Whether the student completed all pending lessons.
     */
    protected $lessonsecomplete;

    /**
     * @var boolean $courseworkcomplete Whether the student completed all course work including lessons.
     */
    protected $courseworkcomplete;

    /**
     * @var string $evaluationfile the student's skill test evaluation file.
     */
    protected $evaluationfile;

    /**
     * Constructor.
     *
     * @param subscriber $course The subscribing course the student is enrolled in.
     * @param int $studentid     The student id.
     */
    public function __construct(subscriber $course, int $studentid, string $studentname = '', int $enroldate = 0) {
        parent::__construct($course, $studentid);
        $this->username = $studentname;
        $this->enroldate = $enroldate;
        $this->slotcolor = self::SLOT_COLOR;
        $this->is_student = true;
    }

    /**
     * Save a student list of slots
     *
     * @param array $params The year, and week.
     * @return bool $result The result of the save transaction.
     */
    public function save_slots(array $params) {
        global $DB;

        $slotid = 0;
        $slotids = '';
        $slots = $params['slots'];
        $year = $params['year'];
        $week = $params['week'];

        // start transaction
        $transaction = $DB->start_delegated_transaction();

        // remove all week/year slots for the user to avoid updates
        $result = slot_vault::delete_slots($this->course->get_id(), $year, $week, $this->userid);

        if ($result) {
            foreach ($slots as $slot) {
                $newslot = new slot(0,
                    $this->userid,
                    $this->course->get_id(),
                    $slot['starttime'],
                    $slot['endtime'],
                    $year,
                    $week,
                );

                // add each slot and record the slot id
                $slotid = slot_vault::save_slot($newslot);
                $slotids .= (!empty($slotids) ? ',' : '') . $slotid;
                $result = $result && $slotid != 0;
            }
        }

        if ($result) {
            $transaction->allow_commit();
        } else {
            $transaction->rollback(new \moodle_exception(get_string('slotssaveunable', 'local_booking')));
        }

        return $slotids;
    }

    /**
     * Delete student slots
     *
     * @param array $params The year, and week.
     * @return bool $result The result of the save transaction.
     */
    public function delete_slots(array $params) {
        global $DB;

        $year = $params['year'];
        $week = $params['week'];

        // start transaction
        $transaction = $DB->start_delegated_transaction();

        // remove all week/year slots for the user to avoid updates
        $result = slot_vault::delete_slots($this->course->get_id(), $this->userid, $year, $week);
        if ($result) {
            $transaction->allow_commit();
        } else {
            $transaction->rollback(new \moodle_exception(get_string('slotsdeleteunable', 'local_booking')));
        }

        return $result;
    }

    /**
     * Get a list of the student assignment objects.
     *
     * @return {object}[]   An array of the student exercise objects.
     */
    public function get_assignments() {
        $assigns = [];
        $exercises = $this->get_exercises();
        foreach ($exercises as $exerciseid => $exercise) {
            list ($course, $cm) = get_course_and_cm_from_cmid($exerciseid, 'assign');
            $context = \context_module::instance($cm->id);
            $assigns[] = new \assign($context, $cm, $course);
        }

        return $assigns;
    }

    /**
     * Get a list of the student exercise grades objects.
     *
     * @return {object}[]   An array of the student exercise objects.
     */
    public function get_exercises() {
        if (!isset($this->exercises)) {
            $this->exercises = $this->vault->get_student_exercises_grades($this->course->get_id(), $this->userid);
        }

        return $this->exercises;
    }

    /**
     * Get records of a specific quiz for a student.
     *
     * @return {object}[]   The exam objects.
     */
    public function get_quizes() {
        if (!isset($this->quizes)) {
            $this->quizes = $this->vault->get_student_quizes_grades($this->course->get_id(), $this->userid);
        }
        return $this->quizes;
    }

    /**
     * Get grades for a specific student from
     * assignments and quizes.
     *
     * @return {object}[] The student grades.
     */
    public function get_grades() {

        if (empty($this->grades)) {
            // join both graded assignments and attempted quizes into one grades array
            $this->grades = $this->get_exercises() + $this->get_quizes();
        }

        return $this->grades;
    }

    /**
     * Get student grade for a specific exercise.
     *
     * @param int  $exerciseid  The exercise id associated with the grade
     * @return grade The student exercise grade.
     */
    public function get_grade(int $exerciseid) {

        $grade = null;
        if (!empty($this->grades) && array_search($exerciseid, array_column($this->grades, 'exerciseid'))) {
            $grade = new grade($this->grades[$exerciseid], $this->userid);
        } else {
            $graderec = $this->vault->get_student_exercises_grade($this->course->get_id(), $this->userid, $exerciseid);
            if (!empty($graderec))
                $grade = new grade($graderec, $this->userid);
        }

        return $grade;
    }

    /**
     * Get student grade for the current exercise.
     *
     * @return grade The student exercise grade.
     */
    public function get_current_grade() {

        $grade = null;
        $exerciseid = $this->get_current_exercise();
        if (!empty($this->grades) && array_search($exerciseid, array_column($this->grades, 'exerciseid'))) {
            $grade = new grade($this->grades[$exerciseid], $this->userid);
        } else {
            $grade = new grade($this->vault->get_student_exercises_grade($this->course->get_id(), $this->userid, $exerciseid), $this->userid);
        }

        return $grade;
    }

    /**
     * Return student slots for a particular week/year.
     *
     * @return array array of days
     */
    public function get_slot(int $slotid) {

        if (empty($this->slots[$slotid]))
            $this->slots[$slotid] = slot_vault::get_slot($slotid);

        return $this->slots[$slotid];
    }

    /**
     * Return student slots for a particular week/year.
     *
     * @return array array of days
     */
    public function get_slots($weekno, $year) {

        if (empty($this->slots)) {
            $this->slots = slot_vault::get_slots($this->userid, $weekno, $year);

            // add student's slot color to each slot
            foreach ($this->slots as $slot) {
                $slot->slotcolor = $this->slotcolor;
            }
        }

        return $this->slots;
    }

    /**
     * Get student slots color.
     *
     * @return string $slotcolor;
     */
    public function get_slotcolor() {
        return $this->slotcolor;
    }

    /**
     * Return student's next lesson.
     *
     * @return string $nextlesson
     */
    public function get_next_lesson() {
        return $this->nextlesson;
    }

    /**
     * Returns the timestamp of the first
     * nonbooked availability slot for
     * the student.
     *
     * @return  DateTime
     */
    public function get_first_slot_date() {
        $firstsession = slot_vault::get_first_posted_slot($this->userid);
        $sessiondatets = !empty($firstsession) ? $firstsession->starttime : time();
        $sessiondate = new \DateTime('@' . $sessiondatets);

        return $sessiondate;
    }

    /**
     * Returns the timestamp of the next
     * allowed session date for the student.
     *
     * @return  DateTime
     */
    public function get_next_allowed_session_date() {

        if (empty($this->restrictiondate)) {
            // get wait time restriction waiver if exists
            $hasrestrictionwaiver = (bool) get_user_preferences('local_booking_' . $this->course->get_id() . '_availabilityoverride', false, $this->userid);
            $nextsessiondate = new \DateTime('@' . time());

            // process restriction if posting wait restriction is enabled or if the student doesn't have a waiver
            if ($this->course->postingwait > 0 && !$hasrestrictionwaiver) {

                $lastsession = $this->get_last_booking();

                // fallback to last graded then enrollment date
                if (!empty($lastsession)) {
                    $nextsessiondate = new \DateTime('@' . $lastsession);
                } else {
                    $lastgraded = $this->get_last_graded_date();
                    if (!empty($lastgraded))
                        $nextsessiondate = $lastgraded;
                    else
                        $nextsessiondate = $this->get_enrol_date();
                }

                // add posting wait period to last session
                date_add($nextsessiondate, date_interval_create_from_date_string($this->course->postingwait . ' days'));

                // return today's date if the posting wait restriction date had passed
                if ($nextsessiondate->getTimestamp() < time())
                    $nextsessiondate = new \DateTime('@' . time());
            }

            // rest the hours to start of the day
            $nextsessiondate->settime(0,0);
            $this->restrictiondate = $nextsessiondate;
        }

        return $this->restrictiondate;
    }

    /**
     * Returns the student's currently active booking.
     *
     * @return booking
     */
    public function get_active_booking() {
        if (empty($this->activebooking)) {
            $booking = new booking(0, $this->course->get_id(), $this->userid);
            if ($booking->load())
                $this->activebooking = $booking;
        }
        return $this->activebooking;
    }

    /**
     * Returns the current exercise id for the student.
     *
     * @param bool $next  Whether to get the next exercise or current, default is next exercise
     * @return int The current exercise id
     */
    public function get_current_exercise() {

        if (empty($this->currentexercise)) {

            $this->currentexercise = $this->vault->get_student_exercise($this->course->get_id(), $this->userid, false);

            // check for newly enrolled student
            if ($this->currentexercise == 0)
                $this->currentexercise = array_values($this->course->get_exercises())[0]->exerciseid;
        }

        return $this->currentexercise;
    }

    /**
     * Returns the next exercise id for the student.
     *
     * @return int The next exercise id
     */
    public function get_next_exercise() {

        if (empty($this->nextexercise))
            $this->nextexercise = $this->vault->get_student_exercise($this->course->get_id(), $this->userid, true);

        return $this->nextexercise;
    }

    /**
     * Returns the student's priority object.
     *
     * @return priority The student's priority object
     */
    public function get_priority() {

        if (empty($this->priority)) {
            $this->priority = new priority($this->course->get_id(), $this->userid);
        }

        return $this->priority;
    }

    /**
     * Returns the total number of active posts.
     *
     * @return int The number of active posts
     */
    public function get_total_posts() {

        if (empty($this->total_posts))
            $this->total_posts = slot_vault::get_slot_count($this->course->get_id(), $this->userid);

        return $this->total_posts;
    }

    /**
     * Get the last grade the student received
     * assignments and quizes.
     *
     * @return grade The student last grade.
     */
    public function get_last_grade() {
        $grade = null;

        if (count($this->exercises) > 0) {
            $lastgrade = end($this->exercises);
            $grade = new grade($lastgrade, $this->userid);
        }

        return $grade;
    }

    /**
     * Get the date timestamp of the last booked slot
     *
     */
    public function get_last_booking() {
        return slot::get_last_booking($this->course->get_id(), $this->userid);
    }

    /**
     * Set the student's slot color.
     *
     * @param string $slotcolor
     */
    public function set_slot_color(string $slotcolor) {
        $this->slotcolor = $slotcolor;
    }

    /**
     * Set the student's next lesson.
     *
     * @param string $nextlesson
     */
    public function set_next_lesson(string $nextlesson) {
        $this->nextlesson = $nextlesson;
    }

    /**
     * Returns whether the student completed
     * all lessons prior to the upcoming next
     * exercise.
     *
     * @return  bool    Whether the lessones were completed or not.
     */
    public function has_completed_lessons() {

        if (!isset($this->lessonsecomplete)) {

            // check if the student is not graduating
            if (!$this->has_completed_coursework()) {

                // exercise associated with completed lessons depends on whether the student passed the current exercise
                $grade = $this->get_grade($this->get_current_exercise());
                if (!empty($grade))
                    // check if passed exercise or received a progressing or objective not met grade
                    $exerciseid = $grade->is_passinggrade() ? $this->get_next_exercise() : $this->get_current_exercise();
                else
                    $exerciseid = $this->get_current_exercise();

                // get lessons complete
                $this->lessonsecomplete = $this->vault->is_student_lessons_complete($this->userid, $this->course->get_id(), $exerciseid);

            } else {
                $this->lessonsecomplete = true;
            }
        }

        return $this->lessonsecomplete;
    }

    /**
     * Returns whether the student completed
     * all course work including skill test.
     *
     * @return  bool    Whether the course work has been completed.
     */
    public function has_completed_coursework() {

        if (!isset($this->courseworkcomplete)) {
            $grade = $this->get_grade($this->course->get_graduation_exercise());
            if (!empty($grade))
                $this->courseworkcomplete = !empty($grade->get_finalgrade());
            else
                $this->courseworkcomplete = false;
        }

        return $this->courseworkcomplete;
    }

    /**
     * Returns whether the student has been evaluated
     * where by the evaluation form is uploaded to
     * the feedback file submission of the skill test exercise.
     *
     * @return  bool    Whether the student has been evaluated.
     */
    public function evaluated() {
        $finalgrade = $this->get_grade($this->course->get_graduation_exercise());
        return !empty($finalgrade->get_feedback_file('assignfeedback_file', 'feedback_files'));
    }

    /**
     * Returns whether the student has graduated
     * and in the graduates group.
     *
     * @return  bool    Whether the student had graduated.
     */
    public function graduated() {
        return $this->is_member_of(LOCAL_BOOKING_GRADUATESGROUP);
    }
}