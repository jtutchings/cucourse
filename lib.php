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

require_once("$CFG->dirroot/user/profile/lib.php");
require_once("$CFG->dirroot/lib/editorlib.php");
define('CUCOURSE_STUDID_FIELD', 'StudentID');
define('CUCOURSE_COURSE_FIELD', 'UniverseCourseCode');
define('CUCOURSE_MODULES_FIELD', 'UniverseModules');
define('CUCOURSE_EXTRA_COURSE_FIELD', 'ExtraFrontpageCourseCode');
define('CUCOURSE_EXTRA_MODULES_FIELD', 'ExtraFrontpageModules');
define('CUCOURSE_OFFICE_HOURS', 'OfficeHours');
define('CUCOURSE_NEWS_ROTATION_TIME', 5);
define('CUCOURSE_NEWS_TRIM_LENGTH', 750);

/**
 * Gets the users custom fields in an array indexed by the shortname
 *
 * @param $userid The Moodle user ID
 * @return array An array of the users custom user profile fields
 */
function cucourse_get_profile_fields($userid) {
    global $CFG, $DB;

    $userfields = array();
    $categories = $DB->get_records('user_info_category', null, 'sortorder ASC');
    if ($categories) {
        foreach ($categories as $category) {
            $fields = $DB->get_records('user_info_field', array('categoryid' => $category->id), 'sortorder ASC');
            if ($fields) {
                foreach ($fields as $field) {
                    require_once($CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php');
                    $newfield = 'profile_field_' . $field->datatype;
                    $formfield = new $newfield($field->id, $userid);
                    if ($formfield->is_visible() and !$formfield->is_empty()) {
                        $userfields[$formfield->field->shortname]['name'] = $formfield->field->name;
                        $userfields[$formfield->field->shortname]['value'] = $formfield->display_data();
                    }
                }
            }
        }
    }
    return $userfields;
}

/**
 * Extracts the list of course codes when formated "ABC123 Y" or "ABC123 Y, ABC234 N"
 *
 * @param string $profilefield Course profile field test
 * @return array An array index by course code or empty array if none found
 */
function cucourse_extract_universe_codes($profilefield) {

    $crscds = array();

    $parts = explode(',', $profilefield);

    foreach ($parts as $part) {
        $bits = explode(' ', trim($part));
        if ($bits) {
            $crscds[$bits[0]] = $bits[0];
        }
    }

    return $crscds;
}

/**
 * Extracts the list of module cohorts codes when formated "208MKT_1213OCTJAN, 238SAM_1213JANMAY, 243SAM_1213OCTJAN,"
 * @param string $profilefield String formated by the nightly process listing students modules
 * @return array An array of module and cohort codes indexed by module_cohort, empty array if none found.
 */
function cucourse_extract_module_codes($profilefield) {

    $crscds = array();
    $crscds = explode(',', $profilefield);
    $crscds = array_map('trim', $crscds);
    $crscds = array_combine($crscds, $crscds);

    return $crscds;
}

/**
 * Get an overview of activity per course for the currecnt user
 *
 * @global type $CFG
 * @global type $USER
 * @global type $DB
 * @global type $OUTPUT
 * @param type $courses array of courses that the user is on
 * @param array $remotecourses
 * @return array An array of the overview course activity for each course since the users last access
 */
function cucourse_get_overview($courses, array $remotecourses = array()) {
    global $CFG, $USER, $DB;

    $htmlarray = array();

    foreach ($courses as $course) {

        if (isset($USER->lastcourseaccess[$course->id])) {
            $course->lastaccess = $USER->lastcourseaccess[$course->id];
        } else {
            $course->lastaccess = 0;
        }
        $course->mods = array();
    }

    $modules = $DB->get_records('modules');
    if ($modules) {
        foreach ($modules as $mod) {
            if (file_exists($CFG->dirroot . '/mod/' . $mod->name . '/lib.php')) {
                include_once($CFG->dirroot . '/mod/' . $mod->name . '/lib.php');
                $fname = $mod->name . '_print_overview';
                if (function_exists($fname)) {
                    $fname($courses, $htmlarray);
                }
            }
        }
    }

    $returncourses = array();

    foreach ($courses as $course) {

        if (array_key_exists($course->id, $htmlarray)) {

            foreach ($htmlarray[$course->id] as $modname => $html) {
                $course->mods[$modname]['displaytext'] = $html;

                $course->mods[$modname]['icon'] = '/theme/image.php/' . $CFG->theme . '/' . $modname . '/1/icon';
            }
        }

        $returncourses[$course->shortname] = $course;
    }

    foreach ($remotecourses as $course) {
        $course->remoteurl = true;
        $returncourses[$course->shortname] = $course;
    }
    return $returncourses;
}

/**
 * Lists the course that the user has access to via any cohorts that the user is in
 *
 * @global type $DB
 * @global type $USER
 * @return array List of the courses that are attached to any of the cohorts the user is in
 */
function cucourse_get_users_cohorted_courses() {
    global $DB, $USER;

    $userscohorts = $DB->get_records('cohort_members', array('userid' => $USER->id));
    if ($userscohorts) {
        $cohortedcourseslist = $DB->get_records_sql('select '
                . 'courseid '
                . 'from {enrol} '
                . 'where enrol = "cohort" '
                . 'and customint1 in (?)', array_keys($userscohorts));
        $cohortedcourses = $DB->get_records_list('course', 'id', array_keys($cohortedcourseslist), null, 'shortname');
        return($cohortedcourses);
    }
    return array();
}

/**
 * Get the news items that need to be displayed
 *
 * @global type $USER
 * @param type $course a course to get the news items from for the current user
 * @return array List of news items to show
 */
function cucourse_get_course_news($course) {
    global $USER;

    $posttext = '';

    $newsitems = array();
    $lastlogin = 0;
    if (!isset($USER->lastcourseaccess[$course->id])) {
        $USER->lastcourseaccess[$course->id] = $lastlogin;
    }
    $newsforum = forum_get_course_forum($course->id, 'news');
    $cm = get_coursemodule_from_instance('forum', $newsforum->id, $newsforum->course);

    $strftimerecent = get_string('strftimerecent');
    $discussions = forum_get_discussions($cm);
    $notread = forum_get_discussions_unread($cm);
    if (count($notread) < 1) {
        $discussions = array();
    }

    foreach ($discussions as $discussion) {

        if (empty($notread[$discussion->discussion])) {
            continue;
        }
        $newsitems[$discussion->id]['course'] = $course->shortname;
        $newsitems[$discussion->id]['courseid'] = $course->id;
        $newsitems[$discussion->id]['discussion'] = $discussion->discussion;
        $newsitems[$discussion->id]['modified'] = $discussion->modified;
        $newsitems[$discussion->id]['author'] = $discussion->firstname . ' ' . $discussion->lastname;
        $newsitems[$discussion->id]['subject'] = $discussion->subject;
        $newsitems[$discussion->id]['message'] = $discussion->message;
        $newsitems[$discussion->id]['userdate'] = userdate($discussion->modified, $strftimerecent);

        $posttext .= $discussion->subject;
        $posttext .= userdate($discussion->modified, $strftimerecent);
        $posttext .= $discussion->message . "\n";
    }
    return $newsitems;
}

/**
 * Format the news from a course ready for diaplying
 * @global type $CFG
 * @param course $course The course that the news is from.
 * @param array $newsitems The newsitems to be displayed.
 * @param array $returns The other news items that to be displayed.
 * @return array The $returns with the extra news items added.
 */
function cucourse_format_course_news($course, $newsitems, $returns) {
    global $CFG;

    if (!$newsitems) {
        return $returns;
    }

    foreach ($newsitems as $newsitem) {

        $returns->headlines[] = html_writer::link(new moodle_url('/mod/forum/discuss.php',
                                                    array('d' => $newsitem['discussion'])), $newsitem['subject']);

        $headline = html_writer::tag('div', html_writer::link(new moodle_url('/mod/forum/discuss.php',
                                                        array('d' => $newsitem['discussion'])), $newsitem['subject']),
                                                        array('class' => 'cucourseNewsHeadline'));

        $headline .= html_writer::tag('span', $course->shortname, array('class' => 'cucourseNewsCourseShortname'));
        $headline .= html_writer::tag('span', $newsitem['author'], array('class' => 'cucourseNewsAuthor'));
        $headline .= html_writer::tag('span', date('l d/m/Y', $newsitem['modified']), array('class' => 'cucourseNewsDate'));
        $newsmessage = '';
        if ($CFG->cucourse_news_trim_length == 0) {
            $newsmessage = $newsitem['message'];
        } else if (strlen($newsitem['message']) > $CFG->cucourse_news_trim_length) {
            $newsmessage = cucourse_truncate_news($newsitem['message'], $CFG->cucourse_news_trim_length);
        } else {
            $newsmessage = $newsitem['message'];
        }
        $returns->newsitems[] = html_writer::tag('div', $headline .
                        html_writer::tag('span', $newsmessage, array('class' => 'cucourseNewsMessage')),
                                        array('class' => 'cucourseNewsItem'));
    }
    return $returns;
}

/**
 * Format the news items in the main container for the news ready for display
 * @param array $newsitems The news items to be added into the main news container
 * @return string The news container with the news items formated as HTML.
 */
function cucourse_format_course_news_container($newsitems) {

    if (empty($newsitems->headlines)) {
        return '';
    }

    $returnstring = html_writer::start_tag('div', array('class' => 'cucourseNewsContainer'));

    $image = html_writer::empty_tag('img', array('src' => new moodle_url('/blocks/cucourse/images/showheadlines.png'),
                'alt' => get_string('show_headlines', 'block_cucourse')));

    $returnstring .= html_writer::tag('div', $image, array('id' => 'cucourseNewsTOCTrigger', 'class' => 'cucourseNewsTOCTrigger'));

    // Put a wrapper div to control the toc and newsitems.
    $returnstring .= html_writer::start_tag('div', array('class' => 'cucourseNewContentHolder'));
    $returnstring .= html_writer::tag('div', html_writer::alist($newsitems->headlines),
                                        array('class' => 'cucourseNewsItemsTOC', 'id' => 'cucourseNewsItemsTOC'));

    $returnstring .= html_writer::tag('div', html_writer::alist($newsitems->newsitems,
                                        array('class' => 'cucourseNewsItems')),
                                        array('class' => 'cucourseNewsContent', 'id' => 'cucourseNewsContent'));
    $returnstring .= html_writer::end_tag('div'); // End cucourseNewContentHolder.
    $returnstring .= html_writer::end_tag('div'); // End cucourseNewsContainer.
    return $returnstring;
}

/**
 * Get the course summary for a given course.
 *
 * @global type $DB
 * @param type $course The course to ge the summary for.
 * @return string The course summary for the given course.
 */
function cucourse_get_course_summary($course) {
    global $DB;

    $rec = $DB->get_record('course', array('id' => $course->id));

    return $rec->summary;
}

/**
 * Format the course headings for the given set of courses
 *
 * @global type $CFG
 * @param type $courses All the courses that the user is on.
 * @param type $crscds The course codes that are required.
 * @return string The course headings formated ready for displaying in HTML.
 */
function cucourse_format_course_heading($courses, $crscds) {

    global $CFG;

    $string = '';
    $maincourse = true;
    foreach ($crscds as $crscd) {

        if (!isset($courses[$crscd])) {
            continue;
        }

        $course = $courses[$crscd];
        $courseactivities = '';

        if (isset($course->mods)) {

            foreach ($course->mods as $mods => $mod) {
                $courseactivities .= html_writer::empty_tag('img', array('src' => new moodle_url($mod['icon']),
                                                    'title' => strip_tags($mod['displaytext']),
                                                    'class' => 'new_activity'));
            }
        }

        $crslink = html_writer::link(new moodle_url('/course/view.php',
                                    array('id' => $course->id)), $course->fullname,
                                    array('class' => 'cucourseCourseLink'));

        $crssummarytext = '';
        if ($maincourse || $CFG->cucourse_show_description_for_extra_courses) {
            $crssummarytext = html_writer::tag('div',
                                           cucourse_get_course_summary($course),
                                           array('id' => 'coursesummary_' . $course->shortname, 'class' => 'cucoursesummary'));
            $maincourse = false;
        }

        $string .= html_writer::tag('div', html_writer::tag('div',
                                                    $crslink . $courseactivities,
                                                    array('id' => 'coursestitle_' . $course->shortname, 'class' => 'cucoursetitle'))
                                                    . $crssummarytext,
                                                    array('id' => 'universecourse' . $course->shortname,
                                                    'class' => 'cucourseUniverseCourseBlock'));
    }

    return $string;
}

/**
 * Formats the list of courses so that they appear on the corect tabs
 *
 * @global type $CFG
 * @param type $allcourses All the courses the user is enrolled on.
 * @param type $unicourses The courses that the student are on that need to be displayed as the header.
 * @param type $modules The set of modules that the student is on for the current tab.
 * @param type $assignments The list of Moodle assignments that the user has.
 * @return string The tabs as HTML.
 */
function cucourse_format_course_list($allcourses, $unicourses, $modules, $extramodules, $assignments, $editprofiletab = false) {
    global $CFG;

    $assignmentdisplaytext = html_writer::tag('div',
                                                cucourse_format_assignment_block($assignments, $allcourses),
                                                array('id' => 'assignments'));

    $tutordisplytext = html_writer::tag('div',
                                            cucourse_get_tutors($allcourses, $unicourses, $modules),
                                            array('id' => 'tutors'));

    if (isset($CFG->cucourse_help_tab_text)) {

        $helptext = $tabtoptext = $CFG->cucourse_help_tab_text;
    } else {
        $helptext = get_string('helptabtext', 'block_cucourse');
    }
    $helptext = html_writer::tag('div', $helptext, array('id' => 'help'));
    $editprofiletext = '';
    if ($editprofiletab) {
        $editprofiletext = html_writer::tag('div', cucourse_format_edit_profile_tab(), array('id' => 'edit'));
    }
    // Setting up the tabs for the module list.
    $tablist = array();
    $tablist[] = html_writer::link('#current', get_string('currenttab', 'block_cucourse'));
    $tablist[] = html_writer::link('#previous', get_string('previoustab', 'block_cucourse'));
    $tablist[] = html_writer::link('#assignments', get_string('assignmentstab', 'block_cucourse'));
    $tablist[] = html_writer::link('#tutors', get_string('tutorstab', 'block_cucourse'));
    if ($editprofiletab) {
        $tablist[] = html_writer::link('#edit', get_string('edittab', 'block_cucourse'));
    }
    $tablist[] = html_writer::link('#help', get_string('helptab', 'block_cucourse'));
    return html_writer::tag('div', html_writer::alist($tablist) .
                    html_writer::tag('div', cucourse_current_module_layout($allcourses, $modules, $extramodules) .
                            $assignmentdisplaytext . $tutordisplytext . $editprofiletext . $helptext),
                            array('id' => 'modulelist', 'class' => 'cucourseModuleList')
    );
}

/**
 * Formats the assignments for display.
 *
 * @global type $CFG
 * @param array $assignments The set of assignments to display
 * @param array $allcourses All the courses the student is on.
 * @return string The assignments as HTML ready for display.
 */
function cucourse_format_assignment_block($assignments, $allcourses) {
    global $CFG;

    $text = '';

    if (isset($CFG->cucourse_assignment_tab_text)) {
        $text = $CFG->cucourse_assignment_tab_text;
    }

    $table = new html_table();
    $table->head = array(get_string('assignmenttabmodulecol', 'block_cucourse'),
        get_string('assignmenttabasscol', 'block_cucourse'));
    $table->align = array('left', 'left');
    $table->width = '100%';
    $table->size = array('25%', '75%');
    $table->attributes = array('class' => 'generaltable');
    $table->data = array();

    $rows = array();
    foreach ($assignments as $shortname => $mod) {

        $line = array();
        $line[] = html_writer::tag('div', $shortname, array('class' => 'cucourseAssignmentModuleName'));
        $line[] = html_writer::tag('div', $mod['displaytext'],
                                        array('class' => 'cucourseAssignmentBlock', 'class' => 'new_activity'));
        $rows[] = $line;
    }
    if (count($rows) > 0) {
        $table->data = $rows;
        $text = html_writer::table($table);
    } else {
        $text = get_string('nocurrentassignments', 'block_cucourse');
    }
    $tabtoptext = '';

    if (isset($CFG->cucourse_assignment_tab_text)) {
        $tabtoptext = html_writer::tag('div', $CFG->cucourse_assignment_tab_text, array('class' => 'cucourseTabTopText'));
    }
    return $tabtoptext . $text;
}

/**
 * Format the list of tutors so they appear as course tutors, current tutors and previous tutors.
 *
 * @global type $CFG
 * @global type $DB
 * @param type $courses All the courses the user is on.
 * @param type $unicourses The Moodle courses that corrospond to the Courses that the student is on.
 * @param type $modules  The current Modules that the student is on.
 * @return string The list of tutors formated ready for display.
 */
function cucourse_get_tutors($courses, $unicourses, $modules) {
    global $CFG, $DB;

    $role = new stdClass;
    $role->id = 3;
    $tutors = array();

    $coursetutors = array();
    $currenttutors = array();

    $officehoursfield = $DB->get_field('user_info_field', 'id', array('shortname' => CUCOURSE_OFFICE_HOURS));
    $userids = array();
    $courseuserids = array();
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id);
        $users = get_users_from_role_on_context($role, $coursecontext);

        $courseuserids[$course->id] = array();
        foreach ($users as $user) {
            $userids[] = $user->userid;
            $courseuserids[$course->id][] = $user->userid;
        }
    }

    if (!$userids) {
        $returnstring = get_string('notutorsonanycourse', 'block_cucourse');
    } else {
        list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);


        $params['fieldid'] = $officehoursfield;
        $sql = "SELECT u.id, u.email, " . get_all_user_name_fields(true, 'u'). ", ud.data AS officehours, " .  user_picture::fields('u', array('id'))
                  ." FROM {user} u
                  LEFT JOIN {user_info_data} ud ON ud.userid = u.id AND ud.fieldid = :fieldid
                 WHERE u.id $usql";
        $users = $DB->get_records_sql($sql, $params);

        foreach ($users as $user) {
            $tutors[$user->id] = $user;
            $user->userid = $user->id;
            if (!$user->officehours) {
                $user->officehours = get_string('officehournotset', 'block_cucourse');
            }
            $user->courses[] = array();
        }

        foreach ($courses as $course) {
            foreach ($courseuserids[$course->id] as $userid) {
                $tutors[$userid]->courses[] = html_writer::link(new moodle_url('/course/view.php',
                                                                               array('id' => $course->id)),
                                                                $course->fullname);
                if (isset($unicourses[$course->shortname])) {
                    $coursetutors[$userid] = true;
                } else if (isset($modules[$course->shortname])) {
                    $currenttutors[$userid] = true;
                }
            }
        }

        $returnstring = '';

        // Course tutors first.
        // Make a note of any we have seen so they are not printed twice.
        $tutorseen = array();
        if ($coursetutors) {

            $data = array();
            foreach (array_keys($coursetutors) as $tutorid) {
                $data[] = cucourse_format_tutor_details($tutors[$tutorid]);
                $tutorseen[$tutorid] = true;
            }
            $returnstring .= cucourse_format_tutor_table($data, get_string('coursetutors', 'block_cucourse'));
        }

        if ($currenttutors) {

            $data = array();
            foreach (array_keys($currenttutors) as $tutorid) {
                if(isset($tutorseen[$tutorid])){
                    continue;
                }
                if (isset($tutors[$tutorid])) {
                    $data[] = cucourse_format_tutor_details($tutors[$tutorid]);
                    $tutorseen[$tutorid] = true;
                }
            }
            $returnstring .= cucourse_format_tutor_table($data, get_string('currenttutors', 'block_cucourse'));
        }

        if ($tutors) {
            $data = array();
            foreach (array_keys($tutors) as $tutorid) {
                if(isset($tutorseen[$tutorid])){
                    continue;
                }
                if (isset($tutors[$tutorid])) {
                    $data[] = cucourse_format_tutor_details($tutors[$tutorid]);
                }
                
            }
            $returnstring .= cucourse_format_tutor_table($data, get_string('previoustutors', 'block_cucourse'));
        }
    }

    $tabtoptext = '';
    if (isset($CFG->cucourse_tutor_tab_text)) {
        $tabtoptext = html_writer::tag('div', $CFG->cucourse_tutor_tab_text, array('class' => 'cucourseTabTopText'));
    }
    return html_writer::start_tag('div',
                                  array('class' => 'cututorcontainer')) . $tabtoptext . $returnstring . html_writer::end_tag('div');
}

/**
 * Format the tutor details into a table ready for displaying.
 *
 * @param array $data The data to display in the table.
 * @param string $summary The summary for the table.
 * @return string The tutor table formated ready for displaying.
 */
function cucourse_format_tutor_table($data, $summary = '') {

    if (empty($data)) {
        return '';
    }
    $table = new html_table();
    $table->summary = $summary;
    $table->head = array(get_string('tutorsprofileimgcol', 'block_cucourse'),
                    get_string('tutorsprofiledetailscol', 'block_cucourse'),
                    get_string('tutorsofficehourscol', 'block_cucourse'));
    $table->align = array('left', 'left', 'left');
    $table->width = '100%';
    $table->size = array('100px', '25%');
    $table->attributes = array('class' => 'generaltable');
    $table->data = array();

    $table->data = $data;

    return html_writer::tag('span', $table->summary, array('class' => 'cucourseTutorHeading')) . html_writer::table($table);
}

/**
 *  Format a single tutors details.
 * @global type $OUTPUT
 * @param type $tutor The tutor to display.
 * @return string The formated HTML for the tutor.
 */
function cucourse_format_tutor_details($tutor) {
    global $OUTPUT;

    $tutordetails = '';

    $tutordetails .= html_writer::tag('div', fullname($tutor), array('class' => 'tutorprofilename'));
    $tutordetails .= html_writer::tag('div',
                                        html_writer::link('mailto:' . $tutor->email, $tutor->email),
                                        array('class' => 'tutorprofileemail'));

    $officehours = $tutor->officehours;

    return array($OUTPUT->user_picture($tutor,
                                        array('size' => '100', 'class' => 'tutorprofilepicture')),
                                        $tutordetails,
                                        $officehours);
}

/**
 * Format the current modules for displaying.
 *
 * @global type $CFG
 * @param array $allcourses All the courses the user is on.
 * @param array $currentmodules The list of modules required.
 * @param array $extramodules The List of modules that have been added via the profile fields.
 * @return string Containg the HTML table for the current set of modules.
 */
function cucourse_current_module_layout($allcourses, $currentmodules, $extramodules) {

    global $CFG;

    $templatetable = new html_table();
    $templatetable->head = array(get_string('select_activity_col', 'block_cucourse'),
        get_string('unit_name_col', 'block_cucourse'),
        get_string('unit_activity_col', 'block_cucourse'));

    $templatetable->align = array('center', 'left', 'left');
    $templatetable->width = '100%';

    $templatetable->data = array();

    $currentmoduleslist = array();
    $pastmodulelist = array();

    ksort($allcourses, SORT_NUMERIC);
    foreach ($allcourses as $shortname => $course) {
        $row = new html_table_row();

        /*
        * If the module is current and is not one of the ones that have been added via
        * the profile field then is can not be moved, otherwise it wants to be selectable
        * so that it can be moved from one tab to another.
        */
        if (isset($currentmodules[$shortname]) && !isset($extramodules[$shortname])) {
            $selectcourse = new html_table_cell(get_string('select_not_available', 'block_cucourse'));
        } else {
            $selectcourse = new html_table_cell(
                    html_writer::checkbox('moveselected[]', $course->shortname, false));
        }

        $courselink = new html_table_cell(html_writer::link(new moodle_url('/course/view.php',
                                                                array('id' => $course->id)), $course->fullname));
        $courseactivities = '';
        if (isset($course->mods)) {
            foreach ($course->mods as $mods => $mod) {
                $courseactivities .= html_writer::empty_tag('img', array('src' => new moodle_url($mod['icon']),
                            'title' => strip_tags($mod['displaytext']),
                            'class' => 'new_activity'));
            }
        }

        if (!$courseactivities) {
            $courseactivities = get_string('unit_no_new_activities', 'block_cucourse');
        }

        $row->cells = array($selectcourse, $courselink, $courseactivities);

        if (isset($currentmodules[$shortname])) {
            $currentmoduleslist[] = $row;
        } else {
            $pastmodulelist[] = $row;
        }
    }

    if (!$currentmodules) {
        $currentmoduleslist = $pastmodulelist;
    }

    $templatetable->data = $currentmoduleslist;
    $tabtoptext = '';

    if (isset($CFG->cucourse_current_tab_text)) {
        $tabtoptext = html_writer::tag('div', $CFG->cucourse_current_tab_text, array('class' => 'cucourseTabTopText'));
    }

    // Need to wrap the table in a form and add a submit button.
    $tabletext = html_writer::tag('form', html_writer::table($templatetable) .
                    html_writer::tag('button',
                                    get_string('move_to_other_tab', 'block_cucourse'),
                                    array('name' => 'remove_from_current',
                                            'type' => 'submit',
                                            'value' => 'remove_from_current')),
                                    array('action' => new moodle_url('/blocks/cucourse/updatefieldsform.php')));

    $returntext = html_writer::tag('div', $tabtoptext . $tabletext, array('id' => 'current'));

    $templatetable->align = array('center', 'left', 'left');
    $templatetable->width = '100%';
    $templatetable->data = $pastmodulelist;

    $tabtoptext = '';

    if (isset($CFG->cucourse_previous_tab_text)) {
        $tabtoptext = html_writer::tag('div', $CFG->cucourse_previous_tab_text, array('class' => 'cucourseTabTopText'));
    }
    $tabletext = html_writer::tag('form', html_writer::table($templatetable) .
                    html_writer::tag('button',
                                get_string('move_to_current_tab', 'block_cucourse'),
                                array('name' => 'add_to_current', 'type' => 'submit', 'value' => 'remove_from_current')),
                                array('action' => new moodle_url('/blocks/cucourse/updatefieldsform.php')));

    $returntext .= html_writer::tag('div', $tabtoptext . $tabletext, array('id' => 'previous'));

    return $returntext;
}

/**
 * Truncates the News Item so it fits in the news tabs nicely.
 *
 * @param type $text The news item text.
 * @param type $length The length to trim it down to.
 * @param type $ending What to display at the end of the string if we have trimmed the item.
 * @param type $exact
 * @param type $considerhtml If the html make up tages should be ignored in the lenght to trim the text down to.S
 * @return string
 */
function cucourse_truncate_news($text, $length = 100, $ending = '...', $exact = false, $considerhtml = true) {
    if ($considerhtml) {
        // If the plain text is shorter than the maximum length, return the whole text.
        if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }
        // Splits all html-tags to scanable lines.
        preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
        $totallength = strlen($ending);
        $opentags = array();
        $truncate = '';
        foreach ($lines as $linematchings) {
            // If there is any html-tag in this line, handle it and add it (uncounted) to the output.
            if (!empty($linematchings[1])) {
                // If it's an "empty element" with or without xhtml-conform closing slash.
                if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $linematchings[1], $tagmatchings)) {
                    // Delete tag from $opentags list.
                    $pos = array_search($tagmatchings[1], $opentags);
                    if ($pos !== false) {
                        unset($opentags[$pos]);
                    }
                    // If tag is an opening tag.
                } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $linematchings[1], $tagmatchings)) {
                    // Add tag to the beginning of $opentags list.
                    array_unshift($opentags, strtolower($tagmatchings[1]));
                }
                // Add html-tag to $truncate'd text.
                $truncate .= $linematchings[1];
            }
            // Calculate the length of the plain text part of the line; handle entities as one character.
            $contentlength = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $linematchings[2]));
            if ($totallength + $contentlength > $length) {
                // The number of characters which are left.
                $left = $length - $totallength;
                $entitieslength = 0;
                // Search for html entities.
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i',
                                    $linematchings[2],
                                    $entities,
                                    PREG_OFFSET_CAPTURE)) {
                    // Calculate the real length of all entities in the legal range.
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entitieslength <= $left) {
                            $left--;
                            $entitieslength += strlen($entity[0]);
                        } else {
                            // No more characters left.
                            break;
                        }
                    }
                }
                $truncate .= substr($linematchings[2], 0, $left + $entitieslength);
                // Maximum length is reached, so get off the loop.
                break;
            } else {
                $truncate .= $linematchings[2];
                $totallength += $contentlength;
            }
            // If the maximum length is reached, get off the loop.
            if ($totallength >= $length) {
                break;
            }
        }
    } else {
        if (strlen($text) <= $length) {
            return $text;
        } else {
            $truncate = substr($text, 0, $length - strlen($ending));
        }
    }
    // If the words shouldn't be cut in the middle...
    if (!$exact) {
        // ...search the last occurance of a space...
        $spacepos = strrpos($truncate, ' ');
        if (isset($spacepos)) {
            // ...and cut the text in this position.
            $truncate = substr($truncate, 0, $spacepos);
        }
    }
    // Add the defined ending to the text.
    $truncate .= $ending;
    if ($considerhtml) {
        // Close all unclosed html-tags.
        foreach ($opentags as $tag) {
            $truncate .= '</' . $tag . '>';
        }
    }
    return $truncate;
}

/**
 * Return the list of Moodle course that a user has access to due to being in a Moodle cohort
 * @global type $DB
 * @param int $userid The user ID to get the courses for.
 * @return array The list of courses that any cohort is attached to.
 */
function cucourse_get_cohort_courses($userid) {
    global $DB;

    $sql = 'select
        crs.id as courseid,
        crs.shortname as courseshortname,
        crs.idnumber as courseidnumber,
        c.id as cohortid,
        c.name as cohortname,
        c.idnumber as cohortidnumber
    from
        {course} crs, {enrol} e, {cohort} as c, {cohort_members} as cu
    where
        crs.id = e.courseid
        and
        e.enrol = "cohort"
        and
        c.id = e.customint1
        and
        c.id = cu.cohortid
        and
        cu.userid = ' . $userid;

    $records = $DB->get_recordset_sql($sql);
    $cohortcourses = array();
    foreach ($records as $record) {
        $cohortcourses[$record->courseshortname] = $record->courseshortname;
    }

    return $cohortcourses;
}

/**
 * Upadtes the users profile field for extra current tab modules.
 *
 * @param string $action An action of either add, remove or clear/
 * @param type $modlist The list of modules that need the action appliad for.
 * @return boolean True on success
 */
function cucourse_update_extra_module_field($action, $modlist) {
    global $USER;

    $userfields = profile_user_record($USER->id);

    $field = CUCOURSE_EXTRA_MODULES_FIELD;

    $currentmodules = cucourse_extract_module_codes($userfields->$field);

    switch ($action) {
        case 'add':
            foreach ($modlist as $modid) {
                if (isset($currentmodules[$modid])) {
                    continue;
                } else {
                    $currentmodules[$modid] = $modid;
                }
            }
            cucourse_update_profile_field(CUCOURSE_EXTRA_MODULES_FIELD,
                                        cucourse_format_extra_modules_field($currentmodules), $USER->id);

            break;

        case 'remove':
            // Go through the list and remove them from the current modules before writing the current modules back.
            foreach ($modlist as $modid) {
                if (isset($currentmodules[$modid])) {
                    unset($currentmodules[$modid]);
                }
            }

            cucourse_update_profile_field(CUCOURSE_EXTRA_MODULES_FIELD,
                                        cucourse_format_extra_modules_field($currentmodules), $USER->id);

            break;

        case 'clear':
            cucourse_update_profile_field(CUCOURSE_EXTRA_MODULES_FIELD, '', $USER->id);

            break;

        default :
            return false;
    }

    return true;
}

function cucourse_format_extra_modules_field($modsin) {

    $modlist = '';
    foreach ($modsin as $modid) {
        if ($modlist === '') {
            $modlist = $modid;
        } else {
            $modlist .= ',' . $modid;
        }
    }

    return $modlist;
}

function cucourse_update_office_hours($officehours) {
    global $USER;

    cucourse_update_profile_field(CUCOURSE_OFFICE_HOURS, $officehours, $USER->id);
}

function cucourse_update_profile_field($fieldname, $value, $userid) {
    global $DB;

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => $fieldname));
    if (!$fieldid) {
        return false;
    }

    $temprec = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $fieldid));

    if (!$temprec) {
        $temprec = new stdClass();
        $temprec->userid = $userid;
        $temprec->fieldid = $fieldid;
        $temprec->data = $value;

        return $DB->insert_record('user_info_data', $temprec);
    } else {
        $temprec->data = $value;
        return $DB->update_record('user_info_data', $temprec);
    }

}

function cucourse_format_edit_profile_tab() {
    global $CFG;
    global $USER;
    global $DB;

    $text = '';

    if (isset($CFG->cucourse_edit_tab_top_text)) {
        $text = $CFG->cucourse_edit_tab_top_text;
    }

    $customfields = profile_user_record($USER->id);
    $officehourtext = '';

    if (isset($customfields->{CUCOURSE_OFFICE_HOURS}) ) {
        $officehourtext = $customfields->{CUCOURSE_OFFICE_HOURS};
    } else {
        $sql = 'select v.data from ' .
                '{user_info_data} v, {user_info_field} f ' .
                'where f.id = v.fieldid ' .
                'and f.shortname = "' . CUCOURSE_OFFICE_HOURS . '"' .
                'and v.userid = ' . $USER->id;
        $rec = $DB->get_record_sql($sql);
        if ($rec) {
            $officehourtext = $rec->data;
        }

    }
    $format = FORMAT_HTML;
    $editor = editors_get_preferred_editor($format);
    $options = array();
    $options['trusttext'] = false;
    $options['forcehttps'] = false;
    $options['subdirs'] = false;
    $options['maxfiles'] = 0;
    $options['maxbytes'] = 0;
    $options['changeformat'] = 0;
    $options['noclean'] = false;
    $options['height'] = 10;

    $editor->use_editor('officehours', $options);
    $text .= get_string('editofficehourstext', 'block_cucourse');

    $editofficehourtext = html_writer::tag('textarea',
                                    $officehourtext,
                                    array('name' => 'officehours',
                                            'id' => 'officehours',
                                            'class' => 'officehours',
                                            'cols' => 50,
                                            'rows' => 50));

    $editofficehourtext .= html_writer::tag('input',
                                        ' ',
                                        array('type' => 'submit',
                                                'value' => get_string('saveofficehoursbuttontext', 'block_cucourse'),
                                                'name' => 'saveofficehours'));

    $text .= $editofficehourtext;

    $tabletext = html_writer::tag('form', $text, array('action' => new moodle_url('/blocks/cucourse/updatefieldsform.php')));
    return $tabletext;
    $table = new html_table();
    $table->head = array(get_string('assignmenttabmodulecol', 'block_cucourse'),
        get_string('assignmenttabasscol', 'block_cucourse'));
    $table->align = array('left', 'left');
    $table->width = '100%';
    $table->size = array('25%', '75%');
    $table->attributes = array('class' => 'generaltable');
    $table->data = array();

    $rows = array();
    foreach ($assignments as $shortname => $mod) {

        $line = array();
        $line[] = html_writer::tag('div',
                                    $shortname,
                                    array('class' => 'cucourseAssignmentModuleName'));
        $line[] = html_writer::tag('div',
                                    $mod['displaytext'],
                                    array('class' => 'cucourseAssignmentBlock', 'class' => 'new_activity'));
        $rows[] = $line;
    }
    if (count($rows) > 0) {
        $table->data = $rows;
        $text = html_writer::table($table);
    } else {
        $text = get_string('nocurrentassignments', 'block_cucourse');
    }
    $tabtoptext = '';

    if (isset($CFG->cucourse_assignment_tab_text)) {
        $tabtoptext = html_writer::tag('div', $CFG->cucourse_assignment_tab_text, array('class' => 'cucourseTabTopText   '));
    }
    return $tabtoptext . $text;
    if (isset($CFG->cucourse_edit_tab_bottom_text)) {
        $text .= $CFG->cucourse_edit_tab_bottom_text;
    }
    return "edit";
}
