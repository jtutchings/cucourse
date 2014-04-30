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

$settings->add(new admin_setting_heading('block_cucourse_block',
                    get_string('settingscoursetabheading', 'block_cucourse'),
                    get_string('settingscoursetabtext', 'block_cucourse')));

$settings->add(new admin_setting_confightmleditor('cucourse_course_top_text',
                    get_string('labelcoursetoptext', 'block_cucourse'),
                    get_string('desccoursetoptext', 'block_cucourse'), ''));

$settings->add(new admin_setting_confightmleditor('cucourse_course_bottom_text',
                    get_string('labelcoursebottomtext', 'block_cucourse'),
                    get_string('desccoursebottomtext', 'block_cucourse'), ''));

$settings->add(new admin_setting_configcheckbox('cucourse_show_description_for_extra_courses',
                    get_string('labelshowdescriptionforextracourses', 'block_cucourse'),
                    get_string('descshowdescriptionforextracourses', 'block_cucourse'), 1));

$settings->add(new admin_setting_heading('block_cucourse_block',
                    get_string('settingsnewsheading', 'block_cucourse'),
                    get_string('settingsnewstext', 'block_cucourse')));

$settings->add(new admin_setting_configcheckbox('cucourse_force_forum_tracking',
                    get_string('labelforceforumtracking', 'block_cucourse'),
                    get_string('descforceforumtracking', 'block_cucourse'), 1));

$settings->add(new admin_setting_configtext('cucourse_news_rotation_time',
                    get_string('labelnewsrotationtime', 'block_cucourse'),
                    get_string('desclabelnewsrotationtime', 'block_cucourse'), CUCOURSE_NEWS_ROTATION_TIME, PARAM_INT));

$settings->add(new admin_setting_configtext('cucourse_news_trim_length',
                    get_string('labelnewstrimlenth', 'block_cucourse'),
                    get_string('desclabelnewstrimlenth', 'block_cucourse'), CUCOURSE_NEWS_TRIM_LENGTH, PARAM_INT));
// Tabs.
// Help Tab.
$settings->add(new admin_setting_heading('block_cucourse_block',
                    get_string('helptabheading', 'block_cucourse'),
                    get_string('helptabtext', 'block_cucourse')));

$settings->add(new admin_setting_confightmleditor('cucourse_help_tab_text',
                    get_string('labelhelptabdisplayname', 'block_cucourse'),
                    get_string('deschelptabdisplayname', 'block_cucourse'), ''));

$settings->add(new admin_setting_confightmleditor('cucourse_current_tab_text',
                    get_string('labelcurrenttabdisplayname', 'block_cucourse'),
                    get_string('desccurrenttabdisplayname', 'block_cucourse'), ''));

$settings->add(new admin_setting_confightmleditor('cucourse_previous_tab_text',
                    get_string('labelprevioustabdisplayname', 'block_cucourse'),
                    get_string('descprevioustabdisplayname', 'block_cucourse'), ''));

$settings->add(new admin_setting_confightmleditor('cucourse_assignment_tab_text',
                    get_string('labelassignmenttabdisplayname', 'block_cucourse'),
                    get_string('descassignmenttabdisplayname', 'block_cucourse'), ''));

$settings->add(new admin_setting_confightmleditor('cucourse_tutor_tab_text',
                    get_string('labeltutortabdisplayname', 'block_cucourse'),
                    get_string('desctutortabdisplayname', 'block_cucourse'), ''));
