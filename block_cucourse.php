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

require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once(dirname(__FILE__) . '/lib.php');

class block_cucourse extends block_base {

    public function init() {
        $this->title = get_string('blocktitle', 'block_cucourse');
    }

    public function has_config() {
        return true;
    }

    public function hide_header() {
        return true;
    }

    public function get_content() {
        global $COURSE;
        global $USER;
        global $PAGE;
        global $CFG;
        global $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';

        // Do not display on any course pages except the main course.
        if ($COURSE->id != 1) {
            $this->content = '';
            return $this->content;
        }

        $displayblock = true;
        if (isset($CFG->cucourse_roles_not_to_show_block)) {

            $roleid = -1;
            $allroles = get_all_roles(context_system::instance());

            foreach ($allroles as $role) {
                if ($role->shortname == $CFG->cucourse_roles_not_to_show_block) {
                    $roleid = $role->id;
                }
            }
            if (user_has_role_assignment($USER->id, $roleid)  ) {
                $displayblock = false;
            }
        }
        if ($displayblock) {
            // Get the news forum player and set the rotation time.
            $PAGE->requires->js('/blocks/cucourse/jquery.min.js');
            $PAGE->requires->js('/blocks/cucourse/jquery-te-1.4.0.min.js');
            $PAGE->requires->js('/blocks/cucourse/forumplayer.js?v=1');
            if (isset($CFG->cucourse_news_rotation_time)) {
                $PAGE->requires->js_init_call('setNewsRotationTime', array($CFG->cucourse_news_rotation_time * 1000));
            } else {
                $PAGE->requires->js_init_call('setNewsRotationTime', array(CUCOURSE_NEWS_ROTATION_TIME * 1000));
            }

            $this->content->text .= '<!-- START OF CUCOURSE CONTENT -->';

            if (isset($CFG->cucourse_course_top_text)) {
                $this->content->text .= html_writer::tag('div', $CFG->cucourse_course_top_text,
                                                        array('class' => 'cucourseTopText'));
            }

            $coursecohorts = cucourse_get_cohort_courses($USER->id);

            $allcourses = cucourse_get_overview(enrol_get_my_courses('id, shortname',
                                                    'visible DESC,sortorder ASC'));

            $customfields = profile_user_record($USER->id);

            $universecoursecodes = array();
            $modules = array();
            $extramodules = array();

            // First see if we need to force forum tracking.
            if ($CFG->cucourse_force_forum_tracking) {
                if (!$USER->trackforums) {
                    $DB->set_field('user', 'trackforums', 1, array('id' => $USER->id));
                    $USER->trackforums = 1;
                }
            }

            foreach ($allcourses as $c) {
                if (isset($USER->lastcourseaccess[$c->id])) {
                    $c->lastaccess = $USER->lastcourseaccess[$c->id];
                } else {
                    $c->lastaccess = 0;
                }
            }

            if (isset($customfields->{CUCOURSE_COURSE_FIELD})) {
                $universecoursecodes = cucourse_extract_universe_codes($customfields->{CUCOURSE_COURSE_FIELD});
            }

            if (isset($customfields->{CUCOURSE_EXTRA_COURSE_FIELD})) {
                $extramodules = cucourse_extract_universe_codes($customfields->{CUCOURSE_EXTRA_COURSE_FIELD});
                $universecoursecodes = array_merge($universecoursecodes, $extramodules);
            }

            if (!empty($coursecohorts)) {
                $universecoursecodes = array_merge($universecoursecodes, $coursecohorts);
            }

            if (isset($customfields->{CUCOURSE_MODULES_FIELD})) {
                $modules = cucourse_extract_module_codes($customfields->{CUCOURSE_MODULES_FIELD});
            }

            if (isset($customfields->{CUCOURSE_EXTRA_MODULES_FIELD})) {
                $extramodules = array_merge($extramodules,
                                            cucourse_extract_module_codes($customfields->{CUCOURSE_EXTRA_MODULES_FIELD}));
                $modules = array_merge($modules, $extramodules);
            }

            // Extract any assignments that are due and any news items.
            $assignments = array();
            $newsblock = new stdClass;
            $newsblock->headlines = array();
            $newsblock->newsitems = array();
            $isteacher = false;

            foreach ($allcourses as $course) {

                $coursecontext = context_course::instance( $course->id);
                if (user_has_role_assignment($USER->id, 3, $coursecontext->id)) {
                    $isteacher = true;
                }

                if (isset($course->mods['assign'])) {
                    $assignments[$course->shortname] = $course->mods['assign'];
                }

                $newsblock = cucourse_format_course_news($course, cucourse_get_course_news($course), $newsblock);
            }

            $coursdisplaytext = '';

            // We are looking at a single course so simple layout.

            if (count($universecoursecodes) == 0) {
                $coursdisplaytext .= html_writer::tag('div',
                                            get_string('no_course_enrolment_text', 'block_cucourse'),
                                            array('class' => 'cucourseUniverseCourseBlockHolder'));
            } else {
                $coursdisplaytext .= html_writer::tag('div',
                                            cucourse_format_course_heading($allcourses, $universecoursecodes),
                                            array('class' => 'cucourseUniverseCourseBlockHolder'));
            }
            $coursdisplaytext .= cucourse_format_course_news_container($newsblock);
            $coursdisplaytext .= cucourse_format_course_list($allcourses,
                                                    $universecoursecodes,
                                                    $modules,
                                                    $extramodules,
                                                    $assignments,
                                                    $isteacher);

            $this->content->text .= html_writer::tag('div',
                                                $coursdisplaytext,
                                                array('id' => 'cucourseLayout', 'class' => 'cucourseLayout'));

            if (isset($CFG->cucourse_course_bottom_text)) {
                $this->content->text .= html_writer::tag('div',
                                                $CFG->cucourse_course_bottom_text,
                                                array('class' => 'cucourseBottomText'));
            }

            $this->content->text .= '<!-- END OF CUCOURSE CONTENT -->';
        } else {
                $this->content->text .= html_writer::tag('div',
                                                $CFG->cucourse_no_block_top_text,
                                                array('class' => 'cucourseBottomText'));
                $search = optional_param('search', '', PARAM_RAW);  // Search words
                $search = trim(strip_tags($search)); // Trim & clean raw searched string.
                $courserenderer = $PAGE->get_renderer('core', 'course');
                $searchform = $courserenderer->course_search_form($search, 'navbar');

                $this->content->text .= $searchform;
                $this->content->text .= html_writer::link(new moodle_url('/course/index.php'),
                                                            get_string('linktocategories', 'block_cucourse'));
                $this->content->text .= html_writer::tag('div', $CFG->cucourse_no_block_footer_text,
                                                            array('class' => 'cucourseBottomText'));

        }
        return $this->content;
    }
}