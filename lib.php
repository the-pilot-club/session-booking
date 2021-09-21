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
 * Session Booking Plugin
 *
 * @package    local_booking
 * @author     Mustafa Hajjar (mustafahajjar@gmail.com)
 * @copyright  BAVirtual.co.uk © 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_booking\external\bookings_exporter;
use local_booking\external\assigned_students_exporter;
use local_booking\external\instructor_participation_exporter;
use local_booking\external\logbook_exporter;
use local_booking\external\logentry_exporter;
use local_booking\local\session\entities\booking;
use local_booking\local\slot\entities\slot;
use local_booking\local\message\notification;
use local_booking\local\participant\entities\participant;
use local_booking\local\logbook\forms\create as create_logentry_form;
use local_booking\local\logbook\forms\create as update_logentry_form;
use local_booking\external\week_exporter;
use local_booking\local\logbook\entities\logbook;
use local_booking\local\logbook\entities\logentry;
use local_booking\local\participant\entities\student;
use local_booking\local\subscriber\subscriber;

/**
 * LOCAL_BOOKING_RECENCYWEIGHT - constant value for session recency weight multipler
 */
define('LOCAL_BOOKING_RECENCYWEIGHT', 10);
/**
 * LOCAL_BOOKING_SLOTSWEIGHT - constant value for session availability slots weight multipler
 */
define('LOCAL_BOOKING_SLOTSWEIGHT', 10);
/**
 * LOCAL_BOOKING_ACTIVITYWEIGHT - constant value for course activity weight multipler
 */
define('LOCAL_BOOKING_ACTIVITYWEIGHT', 1);
/**
 * LOCAL_BOOKING_COMPLETIONWEIGHT - constant value for lesson completion weight multipler
 */
define('LOCAL_BOOKING_COMPLETIONWEIGHT', 10);
/**
 * LOCAL_BOOKING_MINLANES - constant value for minimum number of lanes in a weekday in the availability view
 */
define('LOCAL_BOOKING_MINLANES', 4);
/**
 * LOCAL_BOOKING_MAXLANES - constant value for maximum number of student slots shown in parallel a day
 */
define('LOCAL_BOOKING_MAXLANES', 20);
/**
 * LOCAL_BOOKING_FIRSTSLOT - default value of the first slot of the day
 */
define('LOCAL_BOOKING_FIRSTSLOT', 8);
/**
 * LOCAL_BOOKING_LASTSLOT - default value of the first slot of the day
 */
define('LOCAL_BOOKING_LASTSLOT', 23);
/**
 * LOCAL_BOOKING_WEEKSLOOKAHEAD - default value of the first slot of the day
 */
define('LOCAL_BOOKING_WEEKSLOOKAHEAD', 4);
/**
 * LOCAL_BOOKING_DAYSFROMLASTSESSION - default value of the days allowed to mark since last session
 */
define('LOCAL_BOOKING_DAYSFROMLASTSESSION', 12);
/**
 * LOCAL_BOOKING_ONHOLDGROUP - constant string value for On-hold students for group quering purposes
 */
define('LOCAL_BOOKING_ONHOLDGROUP', 'OnHold');
/**
 * LOCAL_BOOKING_GRADUATESGROUP - constant string value for graduated students for group quering purposes
 */
define('LOCAL_BOOKING_GRADUATESGROUP', 'Graduates');
/**
 * LOCAL_BOOKING_ONHOLDWAITMULTIPLIER - constant for multiplying wait period (in days) for placing students on-hold: 3x wait period
 */
define('LOCAL_BOOKING_ONHOLDWAITMULTIPLIER', 3);
/**
 * LOCAL_BOOKING_SUSPENDWAITMULTIPLIER - constant for multiplying wait period (in days) for suspending inactive students: 9x wait period
 */
define('LOCAL_BOOKING_SUSPENDWAITMULTIPLIER', 9);
/**
 * LOCAL_BOOKING_SESSIONOVERDUEMULTIPLIER - constant for multiplying wait period (in days) for overdue sessions: 3x wait period
 */
define('LOCAL_BOOKING_SESSIONOVERDUEMULTIPLIER', 2);
/**
 * LOCAL_BOOKING_SESSIONLATEMULTIPLIER - constant for multiplying wait period (in days) for late sessions: 4x wait period
 */
define('LOCAL_BOOKING_SESSIONLATEMULTIPLIER', 3);
/**
 * LOCAL_BOOKING_INSTRUCTORINACTIVEMULTIPLIER - constant for multiplying wait period (in days) for late sessions: 3x wait period
 */
define('LOCAL_BOOKING_INSTRUCTORINACTIVEMULTIPLIER', 2);
/**
 * LOCAL_BOOKING_SLOTCOLOR - constant for standard slot color
 */
define('LOCAL_BOOKING_SLOTCOLOR', '#00e676');

/**
 * Process user  table name.
 */
const DB_USER = 'user';

/**
 * Process assign table name.
 */
const DB_ASSIGN = 'assign';

/**
 * Process quiz table name.
 */
const DB_QUIZ = 'quiz';

/**
 * Process  modules table name.
 */
const DB_MODULES = 'modules';

/**
 * Process course modules table name.
 */
const DB_COURSE_MODULES = 'course_modules';

/**
 * Process course sections table name.
 */
const DB_SECTIONS = 'course_sections';

/**
 * This function extends the navigation with the booking item
 *
 * @param global_navigation $navigation The global navigation node to extend
 */

function local_booking_extend_navigation(global_navigation $navigation) {
    global $COURSE, $USER;

    $courseid = $COURSE->id;
    $context = context_course::instance($courseid);
    $course = new subscriber($courseid);

    if (!empty($course->subscribed) && $course->subscribed) {
        // Add student availability navigation node
        if (has_capability('local/booking:logbookview', $context)) {
            $node = $navigation->find('logbook', navigation_node::NODETYPE_LEAF);
            if (!$node && $courseid!==SITEID) {
                // form URL and parameters
                $params = array('course'=>$courseid);
                $url = new moodle_url('/local/booking/logbook.php', $params);

                $parent = $navigation->find($courseid, navigation_node::TYPE_COURSE);
                $node = navigation_node::create(get_string('logbook', 'local_booking'), $url);
                $node->key = 'logbook';
                $node->type = navigation_node::NODETYPE_LEAF;
                $node->forceopen = true;
                $node->icon = new  pix_icon('logbook', '', 'local_booking');
                $parent->add_node($node);
            }
        }

        // Add student availability navigation node
        if (has_capability('local/booking:availabilityview', $context)) {
            $node = $navigation->find('availability', navigation_node::NODETYPE_LEAF);
            if (!$node && $courseid!==SITEID) {
                // form URL and parameters
                $params = array('course'=>$courseid);
                // view all capability for instructors
                if (has_capability('local/booking:view', $context)) {
                    $params['view'] = 'all';
                } else {
                    $weekday = get_next_allowed_session_date($courseid, $USER->id);
                    if ((getdate($weekday->getTimestamp()))['wday'] == 0) {
                        date_add($weekday, date_interval_create_from_date_string('1 days'));
                    }
                    $params['time'] = $weekday->getTimestamp();
                }
                $url = new moodle_url('/local/booking/availability.php', $params);

                $parent = $navigation->find($courseid, navigation_node::TYPE_COURSE);
                $node = navigation_node::create(get_string('availability', 'local_booking'), $url);
                $node->key = 'availability';
                $node->type = navigation_node::NODETYPE_LEAF;
                $node->forceopen = true;
                $node->icon = new  pix_icon('availability', '', 'local_booking');
                $parent->add_node($node);
            }
        }

        // Add instructor booking navigation node
        if (has_capability('local/booking:view', $context)) {
            $node = $navigation->find('bookings', navigation_node::NODETYPE_LEAF);
            if (!$node && $courseid!==SITEID) {
                $parent = $navigation->find($courseid, navigation_node::TYPE_COURSE);
                $node = navigation_node::create(get_string('bookings', 'local_booking'), new moodle_url('/local/booking/view.php', array('courseid'=>$courseid)));
                $node->key = 'bookings';
                $node->type = navigation_node::NODETYPE_LEAF;
                $node->forceopen = true;
                $node->icon = new  pix_icon('booking', '', 'local_booking');

                $parent->add_node($node);
            }
        }
    }
}

/**
 * Fragment to add a new category.
 *
 * @param array $args The fragment arguments.
 * @return string The rendered mform fragment.
 */
function local_booking_output_fragment_logentry_form($args) {
    global $USER;

    $formdata = [];
    $logentryid = isset($args['logentryid']) ? clean_param($args['logentryid'], PARAM_INT) : null;
    if (!empty($args['formdata'])) {
        parse_str($args['formdata'], $formdata);
    }

    $context = \context_user::instance($USER->id);

    if (WS_SERVER) {
        // Request via WS, ignore sesskey checks in form library.
        $USER->ignoresesskey = true;
    }

    $courseid = (!empty($args['courseid'])) ? $args['courseid'] : 0;
    $exerciseid = (!empty($args['exerciseid'])) ? $args['exerciseid'] : 0;
    $studentid = (!empty($args['studentid'])) ? $args['studentid'] : 0;
    $sessiondate = (!empty($args['sessiondate'])) ? $args['sessiondate'] : 0;

    $formoptions = [
        'context'   => $context,
        'courseid'  => $courseid,
        'exerciseid'=> $exerciseid,
        'studentid' => $studentid,
        'sessiondate' => $sessiondate,
    ];

    $logbook = new logbook($courseid, $studentid);

    if (!empty($logentryid)) {
        $logentry = $logbook->get_logentry($logentryid);
        $formoptions['logentry'] = $logentry;
        $formdata = $logentry->__toArray(true);
        $data = $formdata;
        $data['sessiondate'] = $logentry->get_sessiondate();
        $mform = new update_logentry_form(null, $formoptions, 'post', '', null, true, $formdata);
    } else {
        $logentry = new logentry($logbook);
        $logentry->set_picid($studentid);
        $logentry->set_sicid($USER->id);
        $formoptions['logentry'] = $logentry;
        $mform = new create_logentry_form(null, $formoptions, 'post', '', null, true, $formdata);
        // copy over additional data needed for setting the form
        $data['courseid'] = $courseid;
        $data['studentid'] = $studentid;
        $data['exerciseid'] = $exerciseid;
    }

    // Add to form data setup arguments
    $mform->set_data($data);

    if (!empty($args['formdata'])) {
        // Show errors if data was received.
        $mform->is_validated();
        // save log book data
    }

    return $mform->render();
}

/**
 * Get icon mapping for font-awesome.
 */
function local_booking_get_fontawesome_icon_map() {
    return [
        'local_booking:availability' => 'fa-calendar-plus-o',
        'local_booking:booking' => 'fa-plane',
        'local_booking:logbook' => 'fa-address-book-o',
        'local_booking:subscribed' => 'fa-envelope-o',
        'local_booking:unsubscribed' => 'fa-envelope-open-o',
    ];
}

/**
 * Get the calendar view output.
 *
 * @param   \calendar_information $calendar The calendar being represented
 * @param   array   $actiondata The action type and associated data
 * @param   string  $skipevents Whether to load the events or not
 * @return  array[array, string]
 */
function get_weekly_view(\calendar_information $calendar, $actiondata, $view = 'user') {
    global $PAGE;

    $renderer = $PAGE->get_renderer('core_calendar');
    $type = \core_calendar\type_factory::get_calendar_instance();

    $related = [
        'type' => $type,
        'calendar' => $calendar
    ];

    $week = new week_exporter($actiondata, $view, $related);
    $data = $week->export($renderer);
    $data->viewingmonth = true;
    $template = 'local_booking/calendar_week';

    return [$data, $template];
}

/**
 * Get the student's progression view output.
 *
 * @param   int     $courseid the associated course.
 * @param   int     $categoryid the course's category.
 * @return  array[array, string]
 */
function get_bookings_view($courseid) {
    global $PAGE;

    $renderer = $PAGE->get_renderer('local_booking');

    $template = 'local_booking/bookings';
    $data = [
        'courseid'  => $courseid,
    ];

    $bookings = new bookings_exporter($data, ['context' => \context_course::instance($courseid)]);
    $data = $bookings->export($renderer);

    return [$data, $template];
}

/**
 * Get the student's log book view output.
 *
 * @param   int     $courseid the associated course.
 * @return  array[array, string]
 */
function get_logbook_view($courseid) {
    global $PAGE, $USER;

    $studentid = $USER->id;
    $logbook = new logbook($courseid, $studentid);
    list($totalflighthours, $totalsessionhours, $totalsolohours) = $logbook->get_summary();
    $renderer = $PAGE->get_renderer('local_booking');

    $template = 'local_booking/logbook';
    $data = [
        'courseid'  => $courseid,
        'studentid'  => $studentid,
        'studentname'  => get_fullusername($studentid),
        'totalflighttime'  => $totalflighthours,
        'totalsessiontime'  => $totalsessionhours,
        'totalsolotime'  => $totalsolohours,
    ];

    $logbook = new logbook_exporter($data, ['context' => \context_course::instance($courseid)]);
    $data = $logbook->export($renderer);

    return [$data, $template];
}

/**
 * Get the logbook entry view output.
 *
 * @param   int     $logentryid the logbook entry id.
 * @param   int     $courseid
 * @param   int     $studentid
 * @return  array[array, string]
 */
function get_logentry_output($logentryid, $courseid, $studentid) {
    global $PAGE;

    $renderer = $PAGE->get_renderer('local_booking');

    $template = 'local_booking/bookings';
    $data = [
        'logentryid'  => $logentryid,
        'courseid'  => $courseid,
        'studentid'  => $studentid,
    ];

    $logbook = new logbook($courseid);
    $logentry = $logbook->get_logentry($logentryid);
    $logentryexp = new logentry_exporter($data, $logentry, ['context' => \context_course::instance($courseid)]);
    $data = $logentryexp->export($renderer);

    return [$data, $template];
}

/**
 * Get instructor assigned students view output.
 *
 * @param   int     $courseid the associated course.
 * @return  array[array, string]
 */
function get_students_view($courseid) {
    global $PAGE;

    $renderer = $PAGE->get_renderer('local_booking');

    $template = 'local_booking/my_students';
    $data = [
        'courseid'  => $courseid,
    ];

    $students = new assigned_students_exporter($data, ['context' => \context_course::instance($courseid)]);
    $data = $students->export($renderer);

    return [$data, $template];
}

/**
 * Get instructor participation view output.
 *
 * @param   int     $courseid the associated course.
 * @return  array[array, string]
 */
function get_participation_view($courseid) {
    global $PAGE;

    $renderer = $PAGE->get_renderer('local_booking');

    $template = 'local_booking/participation';
    $data = [
        'courseid'  => $courseid,
    ];

    $participation = new  instructor_participation_exporter($data, ['context' => \context_course::instance($courseid)]);
    $data = $participation->export($renderer);

    return [$data, $template];
}

/**
 * Save a new booking for a student by the instructor.
 *
 * @param   array   $params an array containing the webservice parameters
 * @return  bool
 */
function save_booking($params) {
    global $USER;

    $courseid = !empty($params['courseid']) ? $params['courseid'] : SITEID;
    require_login($courseid, false);

    $result = false;
    $slottobook = $params['bookedslot'];
    $courseid   = $params['courseid'];
    $exerciseid = $params['exerciseid'];
    $studentid  = $params['studentid'];
    $instructorid = $USER->id;

    // add a new tentatively booked slot for the student.
    $sessiondata = [
        'exercise'  => get_exercise_name($exerciseid),
        'instructor'=> get_fullusername($instructorid),
        'status'    => ucwords(get_string('statustentative', 'local_booking')),
    ];

    // add new booking and update slot
    $studentslot = new slot(0,
        $studentid,
        $courseid,
        $slottobook['starttime'],
        $slottobook['endtime'],
        $slottobook['year'],
        $slottobook['week'],
        get_string('statustentative', 'local_booking'),
        get_string('bookinginfo', 'local_booking', $sessiondata)
    );

    $newbooking = new booking(0, $courseid, $studentid, $exerciseid, $studentslot,'', $instructorid);
    $result = $newbooking->save();

    if ($result) {
        // send emails to both student and instructor
        $sessiondate = new DateTime('@' . $slottobook['starttime']);
        $message = new notification();
        if ($message->send_booking_notification($studentid, $exerciseid, $sessiondate)) {
            $message->send_instructor_confirmation($studentid, $exerciseid, $sessiondate);
        }
        $sessiondata['sessiondate'] = $sessiondate->format('D M j\, H:i');
        $sessiondata['studentname'] = get_fullusername($studentid);
        \core\notification::success(get_string('bookingsavesuccess', 'local_booking', $sessiondata));
    } else {
        \core\notification::warning(get_string('bookingsaveunable', 'local_booking'));
    }

    return $result;
}

/**
 * Confirm a booking for of a tentative session
 *
 * @param   int   $couseid      The course id for this booking.
 * @param   int   $instructorid The instructor id that booked the session.
 * @param   int   $studentid    The student id being of the confirmed session.
 * @param   int   $exerciseid   The exercise id being confirmed.
 * @return  array An array containing the result and confirmation message string.
 */
function confirm_booking($courseid, $instructorid, $studentid, $exerciseid) {
    global $DB;
    $result = false;

    // Get the student slot
    $booking = new booking(0, $courseid, $studentid, $exerciseid);
    $booking->load();

    // confirm the booking and redirect to the student's availability
    $transaction = $DB->start_delegated_transaction();

    if (!empty($booking->get_id())) {
        // update the booking by the instructor.
        $sessiondatetime = (new DateTime('@' . ($booking->get_slot())->get_starttime()))->format('D M j\, H:i');
        $strdata = [
            'exercise'  => get_exercise_name($exerciseid),
            'instructor'=> get_fullusername($instructorid),
            'status'    => ucwords(get_string('statusbooked', 'local_booking')),
            'sessiondate'=> $sessiondatetime
        ];
        if ($booking->confirm(get_string('bookingconfirmmsg', 'local_booking', $strdata))) {
            // notify the instructor of the student's confirmation
            $message = new notification();
            $result = $message->send_instructor_notification($courseid, $studentid, $exerciseid, $sessiondatetime, $instructorid);
        }
    }

    if ($result) {
        $transaction->allow_commit();
        \core\notification::success(get_string('bookingconfirmsuccess', 'local_booking', $strdata));
    } else {
        $transaction->rollback(new moodle_exception(get_string('bookingconfirmunable', 'local_booking')));
        \core\notification::ERROR(get_string('bookingconfirmunable', 'local_booking'));
    }

    return [$result, ($booking->get_slot())->get_starttime(), ($booking->get_slot())->get_week()];
}

/**
 * Cancel an instructor's existing booking
 * @param   int   $booking  The booking id being cancelled.
 * @return  bool  $resut    The cancellation result.
 */
function cancel_booking($bookingid, $comment) {
    global $DB, $COURSE;

    // get the booking to be deleted
    $booking = new booking($bookingid);
    $booking->load();
    $courseid = !empty($booking->get_courseid()) ? $booking->get_courseid() : $COURSE->id;
    $sessiondate = new DateTime('@' . ($booking->get_slot())->get_starttime());

    require_login($courseid, false);

    // start a transaction
    $transaction = $DB->start_delegated_transaction();

    $result = $booking->delete();

    $cancellationmsg = [
        'studentname' => get_fullusername($booking->get_studentid()),
        'sessiondate' => $sessiondate->format('D M j\, H:i'),
    ];

    if ($result) {
        $transaction->allow_commit();
        // send email notification to the student of the booking cancellation
        $message = new notification();
        if ($message->send_session_cancellation($booking->get_studentid(), $booking->get_exerciseid(), $sessiondate, $comment)) {
            \core\notification::success(get_string('bookingcanceledsuccess', 'local_booking', $cancellationmsg));
        }
    } else {
        $transaction->rollback(new moodle_exception(get_string('bookingsaveunable', 'local_booking')));
        \core\notification::warning(get_string('bookingcanceledunable', 'local_booking'));
    }

    return $result;
}

/**
 * Respond to submission graded events
 *
 */
function process_submission_graded($courseid, $studentid, $exerciseid) {
    $booking = new booking(0, $courseid, $studentid, $exerciseid);
    $booking->load();
    // update the booking status from active to inactive
    $booking->deactivate();
}

/**
 * Returns exercise assignment name
 *
 * @return string  The course exercise name.
 */
function get_exercise_name($exerciseid) {
    global $DB;

    // Get the student's grades
    $sql = 'SELECT a.name AS exercisename
            FROM {' . DB_ASSIGN . '} a
            INNER JOIN {' . DB_COURSE_MODULES . '} cm on a.id = cm.instance
            WHERE cm.id = ' . $exerciseid;

    return $DB->get_record_sql($sql)->exercisename;
}


/**
 * Retrieves exercises for the course
 *
 * @return array
 */
function get_exercise_names() {
    global $DB;

    $exercisenames = [];

    // get assignments for this course based on sorted course topic sections
    $sql = 'SELECT cm.id AS exerciseid, a.name AS assignname,
            q.name AS exam, m.name AS modulename
            FROM {' . DB_COURSE_MODULES . '} cm
            INNER JOIN {' . DB_MODULES . '} m ON m.id = cm.module
            LEFT JOIN {' . DB_ASSIGN . '} a ON a.id = cm.instance
            LEFT JOIN {' . DB_QUIZ . '} q ON q.id = cm.instance
            WHERE m.name = \'assign\' OR m.name = \'quiz\'
            ORDER BY cm.section;';

    $exercises = $DB->get_records_sql($sql);
    foreach ($exercises as $exercise) {
        $exerciseitem = $exercise->modulename == 'assign' ? (object) [
            'exerciseid'    => $exercise->exerciseid,
            'exercisename'  => $exercise->assignname
        ] : (object) [
            'exerciseid'    => $exercise->exerciseid,
            'exercisename'  => $exercise->exam
        ];

        $exercisenames[$exercise->exerciseid] = $exerciseitem;
    }

    return $exercisenames;
}

/**
 * Return the days of the week where $date falls in.
 *
 * @return array array of days
 */
function get_week_days($date) {

    $days = [];
    // Calculate which day number is the first day of the week.
    $type = \core_calendar\type_factory::get_calendar_instance();
    $daysinweek = count($type->get_weekdays());
    $week_start = get_week_start($date);

    // add remaining days of the week
    for ($i = 0; $i < $daysinweek; $i++) {
        $days[] = $type->timestamp_to_date_array(date_timestamp_get($week_start), 0);
        date_add($week_start, date_interval_create_from_date_string("1 days"));
    }

    return $days;
}

/**
 * Return the days of the week where $date falls in.
 *
 * @return DateTime array of days
 */
function get_week_start($date) {
    $week_start_date = new DateTime();
    date_timestamp_set($week_start_date, $date[0]);
    $week_start_date->setISODate($date['year'], strftime('%W', $date[0]))->format('Y-m-d');
    return $week_start_date;
}

/**
 * Checks if the student completed
 * all pending lessons before marking
 * availability for an instructor session.
 *
 * @param   int     The course id
 * @param   int     The student id
 * @return  bool
 */
function has_completed_lessons($courseid, $studentid) {
    $student = new student($courseid, $studentid);
    list($nextexercise, $exercisesection) = $student->get_next_exercise();
    $completedlessons = $student->get_lessons_complete($exercisesection);

    return $completedlessons;
}

/**
 * Returns the date of the last booked
 * session or today if unavailable.
 *
 * @param   int     The course id for subscribing course.
 * @param   int     The student id related.
 * @return  DateTime
 */
function get_next_allowed_session_date($courseid, $studentid) {
    $daysfromlast = (get_config('local_booking', 'nextsessionwaitdays')) ? get_config('local_booking', 'nextsessionwaitdays') : LOCAL_BOOKING_DAYSFROMLASTSESSION;

    $lastsession = slot::get_last_posting($courseid, $studentid);
    $sessiondatets = !empty($lastsession) ? $lastsession->starttime : time();
    $sessiondate = new DateTime('@' . $sessiondatets);
    date_add($sessiondate, date_interval_create_from_date_string($daysfromlast . ' days'));

    return $sessiondate;
}

/**
 * Returns full username
 *
 * @return string  The full  username (first, last, and optional alternate name)
 */
function get_fullusername(int $userid, bool $includealternate = true) {
    global $DB;

    $fullusername = '';
    if ($userid != 0) {
        // Get the full user name
        $sql = 'SELECT ' . $DB->sql_concat('u.firstname', '" "',
                    'u.lastname', '" "', 'u.alternatename') . ' AS bavname, '
                    . $DB->sql_concat('u.firstname', '" "',
                    'u.lastname') . ' AS username
                FROM {' . DB_USER . '} u
                WHERE u.id = ' . $userid;

        $userinfo = $DB->get_record_sql($sql);
        $fullusername = $includealternate ? $userinfo->bavname : $userinfo->username;
    }

    return $fullusername;
}

/**
 * Returns the course section name containing the exercise
 *
 * @param int $courseid The course id of the section
 * @param int $exerciseid The exercise id in the course inside the section
 * @return string  The section name of a course associated with the exercise
 */
function get_course_section_name(int $courseid, int $exerciseid) {
    global $DB;

    $sectionname = '';
    // Get the full user name
    $sql = 'SELECT name as sectionname FROM mdl_course_sections cs
            INNER JOIN mdl_course_modules cm ON cm.section = cs.id
            WHERE cm.id = ' . $exerciseid . '
            AND cm.course = ' . $courseid;

    $section = $DB->get_record_sql($sql);

    return $section->sectionname;
}

/**
 * Returns a random color.
 *
 * @return string   hex color
 */
function random_color_part() {
    return str_pad( dechex( mt_rand( 20, 235 ) ), 2, '0', STR_PAD_LEFT);
}

/**
 * Returns a hash of the random color for a student slot in group view.
 *
 * @return string   hex color
 */
function random_color() {
    return random_color_part() . random_color_part() . random_color_part();
}

/**
 * Returns course id of the passed course
 *
 * @return int  The course id.
 */
function get_course_id($exerciseid) {
    global $DB;

    // Get the student's grades
    $sql = 'SELECT cm.course AS courseid
            FROM {' . DB_COURSE_MODULES . '} cm
            WHERE cm.id = ' . $exerciseid;

    return $DB->get_record_sql($sql)->courseid;
}

/**
 * Returns the ATO name from config.xml
 *
 * @return string  The ATO name.
 */
function get_booking_config(string $key, $associative = null) {
    global $CFG;
    $configfile = $CFG->dirroot . '/local/booking/config.json';
    $config = null;
    if (file_exists($configfile)) {
        $jsoncontent = file_get_contents($configfile);
        $configdata = json_decode($jsoncontent, $associative);
        $config = $associative ? $configdata[$key] : $configdata->{$key};
    } else {
        var_dump(get_string('configmissing', 'local_booking', $configfile));
    }
    return $config;
}
