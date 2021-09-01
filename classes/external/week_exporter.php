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
 * Contains event class for displaying the week view.
 *
 * @package   local_booking
 * @copyright 2017 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_booking\external;

defined('MOODLE_INTERNAL') || die();

use core\external\exporter;
use core_calendar\external\date_exporter;
use core_calendar\type_base;
use renderer_base;
use moodle_url;

/**
 * Class for displaying the week view.
 *
 * @package   core_calendar
 * @copyright 2017 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class week_exporter extends exporter {

    /**
     * Process user enrollments table name.
     */
    const DB_USER = 'user';

    /**
     * @var \calendar_information $calendar The calendar to be rendered.
     */
    protected $calendar;

    /**
     * @var array $days An array of week_timeslot_exporter objects.
     */
    protected $days = [];

    /**
     * @var array $days An array of week_timeslot_exporter objects.
     */
    protected $studentsslots;

    /**
     * @var array $maxlanes - The maximum amount of lanes required to fit in a day.
     */
    protected $maxlanes;

    /**
     * @var bool $showlocaltime Whether to show local time.
     */
    protected $showlocaltime;

    /**
     * @var array $days An array of week_timeslot_exporter objects.
     */
    protected $weekno;

    /**
     * @var int $firstdayofweek The first day of the week.
     */
    protected $firstdayofweek;

    /**
     * @var array $action The action data being peformed.
     */
    protected $actiondata;

    /**
     * @var string $view The view type for the availability slot grid.
     */
    protected $view;

    /**
     * @var moodle_url $url The URL for the week page.
     */
    protected $url;

    /**
     * @var bool $initialeventsloaded Whether the events have been loaded for this month.
     */
    protected $initialeventsloaded = true;

    /**
     * Constructor.
     *
     * @param \calendar_information $calendar The calendar information for the period being displayed
     * @param mixed $timeslots An array of week_day_exporter objects.
     * @param array $actiondata associated with the booking action.
     * @param array $related Related objects.
     */
    public function __construct(\calendar_information $calendar, type_base $type, $actiondata, $view, $related) {
        global $CFG, $USER;

        // Use core calendar and action
        $this->calendar = $calendar;
        $this->actiondata = $actiondata;
        $this->view = $view;

        // Get user preference to show local time column
        set_user_preference('local_booking_showlocaltime', 'yes');
        $showlocaltime = get_user_preferences('local_booking_showlocaltime');
        if (empty($showlocaltime)) {
            set_user_preference('local_booking_showlocaltime', 'yes');
        }
        $this->showlocaltime = (bool)$showlocaltime;

        // Get current week of the year and GMT date
        $this->weekno = strftime('%W', $this->calendar->time);
        $this->firstdayofweek = $type->get_starting_weekday();
        $calendarday = $type->timestamp_to_date_array($this->calendar->time);
        $GMTdate = $type->timestamp_to_date_array(gmmktime(0, 0, 0, $calendarday['mon'], $calendarday['mday'], $calendarday['year']));
        $this->days = get_week_days($GMTdate);

        // view the slots for a single student user or all students
        switch ($view) {
            case 'user':
                $studentid = $USER->id;
                break;
            case 'all':
                $studentid = 0;
                break;
        }

        // view the slot for the student id passed in action data
        if ($actiondata['action'] == 'book') {
            $studentid = $actiondata['studentid'];
        }

        $this->studentsslots = get_active_student_slots($this->weekno, $GMTdate['year'], $studentid);

        // Get exporter data
        $this->url = new moodle_url('/local/booking/availability.php', [
                'time' => $calendar->time,
            ]);

        if ($this->calendar->course && SITEID !== $this->calendar->course->id) {
            $this->url->param('course', $this->calendar->course->id);
        } else if ($this->calendar->categoryid) {
            $this->url->param('category', $this->calendar->categoryid);
        }

        $related['type'] = $type;

        $data = [
            'url'         => $this->url->out(false),
            'username'    => $this->actiondata['action'] == 'book' ? get_fullusername($this->actiondata['studentid']) : '',
            'action'      => $this->actiondata['action'],
            'studentid'   => $this->actiondata['studentid'],
            'exerciseid'  => $this->actiondata['exerciseid'],
            'editing'     => $this->view == 'user' && $this->actiondata['action'] != 'book',    // Editing is not allowed if user id is passed for booking
            'groupview'   => $this->view == 'all',                                              // Group view no editing or booking buttons
            'viewalllink' => $CFG->httpswwwroot . '/local/booking/availability.php?course=' . $this->calendar->courseid . '&view=all',
            'hiddenclass' => $this->actiondata['action'] != 'book' && $this->view == 'user',
            'visible'     => $this->actiondata['action'] != 'book' && $this->view == 'user',
        ];

        parent::__construct($data, $related);
    }

    protected static function define_properties() {
        return [
            'url' => [
                'type' => PARAM_URL,
            ],
            'username' => [
                'type' => PARAM_RAW,
                'default' => null,
            ],
            'action' => [
                'type' => PARAM_RAW,
            ],
            'studentid' => [
                'type' => PARAM_INT,
            ],
            'exerciseid' => [
                'type' => PARAM_INT,
            ],
            'editing' => [
                'type' => PARAM_BOOL,
            ],
            'groupview' => [
                'type' => PARAM_BOOL,
            ],
            'viewalllink' => [
                'type' => PARAM_RAW,
            ],
            'hiddenclass' => [
                'type'  => PARAM_BOOL,
            ],
            'visible' => [
                'type' => PARAM_BOOL,
            ],
        ];
    }

    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties() {
        return [
            'courseid' => [
                'type' => PARAM_INT,
            ],
            'categoryid' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'date' => [
                'type' => date_exporter::read_properties_definition(),
            ],
            'daynames' => [
                'type' => day_name_exporter::read_properties_definition(),
                'multiple' => true,
            ],
            'timeslots' => [
                'type' => week_timeslot_exporter::read_properties_definition(),
                'multiple' => true,
            ],
            'maxlanes' => [
                'type' => PARAM_INT,
            ],
            'showlocaltime' => [
                'type' => PARAM_BOOL,
            ],
            'weekofyear' => [
                'type' => PARAM_INT,
            ],
            'periodname' => [
                'type' => PARAM_RAW,
            ],
            'previousperiod' => [
                'type' => date_exporter::read_properties_definition(),
            ],
            'previousweek' => [
                'type' => PARAM_INT,
            ],
            'previousweekts' => [
                'type' => PARAM_INT,
            ],
            'previousperiodname' => [
                'type' => PARAM_RAW,
            ],
            'previousperiodlink' => [
                'type' => PARAM_URL,
            ],
            'nextperiod' => [
                'type' => date_exporter::read_properties_definition(),
            ],
            'nextweek' => [
                'type' => PARAM_INT,
            ],
            'nextweekts' => [
                'type' => PARAM_INT,
            ],
            'nextperiodname' => [
                'type' => PARAM_RAW,
            ],
            'nextperiodlink' => [
                'type' => PARAM_URL,
            ],
            'larrow' => [
                // The left arrow defined by the theme.
                'type' => PARAM_RAW,
            ],
            'rarrow' => [
                // The right arrow defined by the theme.
                'type' => PARAM_RAW,
            ],
            // Tracks whether the first set of events have been loaded and provided to the exporter.
            'initialeventsloaded' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
            'defaulteventcontext' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
        global $USER;

        // notify student if wait period restriction since last session is not up yet
        if ($this->actiondata['action'] != 'book' & $this->view == 'user') {
            if (!has_completed_lessons($USER->id)) {
                \core\notification::WARNING(get_string('lessonsincomplete', 'local_booking'));
            }
        }

        $date = $this->related['type']->timestamp_to_date_array($this->calendar->time);

        // Get previous and next periods navigation information
        list($previousperiod, $previousperiodlink) = $this->get_period($date, '-');
        list($nextperiod, $nextperiodlink) = $this->get_period($date, '+');

        $return = [
            'courseid' => $this->calendar->courseid,
            // week data
            'daynames' => $this->get_day_names($output),
            'weekofyear' => $this->weekno,
            'timeslots' => $this->get_time_slots($output),
            // day slots data
            'maxlanes' => $this->maxlanes <= LOCAL_BOOKING_MAXLANES ? $this->maxlanes : LOCAL_BOOKING_MAXLANES,
            'showlocaltime' => $this->showlocaltime,
            'date' => (new date_exporter($date))->export($output),
            // navigation data
            'periodname' => strftime(get_string('strftimeweekinyear','local_booking'), $this->calendar->time),
            'previousperiod' => (new date_exporter($previousperiod))->export($output),
            'previousweek' => strftime('%W', $previousperiod[0]),
            'previousweekts' => $previousperiod[0],
            'previousperiodname' => strftime(get_string('strftimeweekinyear','local_booking'), $previousperiod[0]),
            'previousperiodlink' => $previousperiodlink->out(false),
            'nextperiod' => (new date_exporter($nextperiod))->export($output),
            'nextweek' => strftime('%W', $nextperiod[0]),
            'nextweekts' => $nextperiod[0],
            'nextperiodname' => strftime(get_string('strftimeweekinyear','local_booking'), $nextperiod[0]),
            'nextperiodlink' => $nextperiodlink->out(false),
            'larrow' => $output->larrow(),
            'rarrow' => $output->rarrow(),
            'initialeventsloaded' => $this->initialeventsloaded,
        ];

        if ($context = $this->get_default_add_context()) {
            $return['defaulteventcontext'] = $context->id;
        }

        if ($this->calendar->categoryid) {
            $return['categoryid'] = $this->calendar->categoryid;
        }

        return $return;
    }

    /**
     * Get the list of day names for display, re-ordered from the first day
     * of the week.
     *
     * @param   renderer_base $output
     * @return  day_name_exporter[]
     */
    protected function get_day_names(renderer_base $output) {
        $weekdaynames = $this->related['type']->get_weekdays();
        $daysinweek = count($weekdaynames);

        $daynames = [];
        for ($i = 0; $i < $daysinweek; $i++) {
            // Bump the currentdayno and ensure it loops.
            $dayno = ($i + $this->firstdayofweek + $daysinweek) % $daysinweek;
            $dayofmonthname = $this->days[$i]['mday'] . '/' . $this->days[$i]['mon'];
            $dayname = new day_name_exporter($dayno, $dayofmonthname, $weekdaynames[$dayno]);

            $daynames[] = $dayname->export($output);
        }

        return $daynames;
    }

    /**
     * Get the list of day hours in 24hr format for display
     * of the week.
     *
     * @return  $timeslots[]
     */
    protected function get_time_slots(renderer_base $output) {
        // Get daily slots from settings
        $firstsessionhour = (get_config('local_booking', 'firstsession')) ? substr(get_config('local_booking', 'firstsession'), 0, 2) : LOCAL_BOOKING_FIRSTSLOT;
        $lastsessionhour = (get_config('local_booking', 'lastsession')) ? substr(get_config('local_booking', 'lastsession'), 0, 2) : LOCAL_BOOKING_LASTSLOT;

        // Get user timezone offset
        $usertz = new \DateTimeZone(usertimezone());
        $usertime = new \DateTime("now", $usertz);
        $usertimezoneoffset = intval($usertz->getOffset($usertime)) / 3600;

        $weeklanes = $this->get_week_slot_lanes();

        $slots = [];
        for ($i = $firstsessionhour; $i <= $lastsessionhour; $i++) {
            $daydata = [];
            $daydata['timeslot'] = substr('00' . $i, -2) . ':00';
            $daydata['usertimeslot'] = substr('00' . ($i + $usertimezoneoffset) % 24, -2) . ':00';
            $daydata['studentid'] = $this->actiondata['studentid'];
            $daydata['hour'] = $i;
            $daydata['days'] = $this->days;
            $daydata['maxlanes'] = $this->maxlanes;
            $daydata['groupview'] = $this->view == 'all' && $this->actiondata['action'] != 'book';
            $timeslot = new week_timeslot_exporter($this->calendar, $daydata, $weeklanes, $this->related);

            $slots[] = $timeslot->export($output);
        }

        return $slots;
    }

    /**
     * Returns a list of week day lanes
     * containing student slots.
     *
     * @return array
     */
    protected function get_week_slot_lanes() {
        $this->maxlanes = 1;
        $weeklanes = [];

        // go through the week stacking slots
        foreach ($this->days as $daydata) {

            $daylanes = [];
            $laneindex = 0;
            // check students that have slot(s) on this day
            foreach ($this->studentsslots as $studentslots) {

                $slots = $studentslots;
                // check students that have slot(s) on this day
                foreach ($slots as $slot) {
                    // skip if the student doesn't have a slot on this day
                    if (getdate($slot->starttime)['wday'] == $daydata['wday']) {

                        // lookup an empty lane that can take the slot w/o overlap
                        $emptylaneidx = $this->find_empty_space($slot, $daylanes);
                        if ($emptylaneidx == -1) {
                            // no empty space found
                            $laneindex++;
                            $daylanes[$laneindex][] = $slot;
                        } else {
                            $daylanes[$emptylaneidx][] = $slot;
                        }

                        // evaluate max number of lanes
                        $this->maxlanes = $laneindex + 1 > $this->maxlanes ? $laneindex + 1 : $this->maxlanes;
                    }
                }
            }
            $weeklanes[$daydata['wday']] = $daylanes;
        }

        return $weeklanes;
    }

    /**
     * Returns a the lane index where an empty spot
     * is found, otherwise returns false (0).
     *
     * @param object    slot object to checked for conflict
     * @param array     An array of day lanes with previous slots
     * @return bool
     */
    protected function find_empty_space($savedslot, $daylanes) {
        $emptylaneidx = -1;
        $laneidx = 0;

        // make sure there are lanes in the day
        if (count($daylanes) != 0) {
            // go through all lanes of the day
            foreach ($daylanes as $lane) {
                // go throuh all slots in the lane
                foreach ($lane as $laneslot) {
                    // does the saved slot start-to-end time conflict with existing lane slots
                    if ((($savedslot->starttime >= $laneslot->starttime && $savedslot->starttime <= $laneslot->endtime) ||
                        ($laneslot->starttime >= $savedslot->starttime && $laneslot->starttime <= $savedslot->endtime)) &&
                        // ok to overlap booked slots by instructors of student marked slots
                        empty($savedslot->slotstatus)) {
                            break 2;
                    }
                }
                // assign the current lane index to the empty one as there is no overlap on this lane: $savedslot->userid != $laneslot->userid)
                $emptylaneidx = $laneidx;
                $laneidx++;
            }
        } else { $emptylaneidx = 0; }

        return $emptylaneidx;
    }

    /**
     * Get the previous and next week timestamps
     * and URL for navigation links.
     *
     * @return int The previous and next week's timestamp.
     */
    protected function get_period($date, $nextprev) {
        $perioddate = date_create();
        date_timestamp_set($perioddate, $date[0]);
        $perioddate->modify($nextprev . '7 days');
        $newperioddate = $this->related['type']->timestamp_to_date_array(date_timestamp_get($perioddate));

        $periodlink = new moodle_url($this->url);
        $periodlink->param('time', $newperioddate[0]);
        $periodlink->param('week', strftime('%W', $newperioddate[0]));
        $periodlink->param('view', $this->view);

        // Pass the user and exercise ids for booking actions
        if ($this->actiondata['action'] == 'book') {
            $periodlink->param('userid', $this->actiondata['studentid']);
            $periodlink->param('exid', $this->actiondata['exerciseid']);
            $periodlink->param('action', 'book');
        }

        return [$newperioddate, $periodlink];
    }

    /**
     * Get the default context for use when adding a new event.
     *
     * @return null|\context
     */
    protected function get_default_add_context() {
        if (calendar_user_can_add_event($this->calendar->course)) {
            return \context_course::instance($this->calendar->course->id);
        }

        return null;
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'type' => '\core_calendar\type_base',
        ];
    }
}