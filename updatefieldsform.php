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

require_once('../../config.php');
require_once(dirname(__FILE__) . '/lib.php');


$removebuttonpressed = filter_input(INPUT_GET, 'remove_from_current');
$addbuttonpressed = filter_input(INPUT_GET, 'add_to_current');

$saveofficehours = filter_input(INPUT_GET, 'saveofficehours');

if ($saveofficehours) {
    $officehours = filter_input(INPUT_GET, 'officehours');
    cucourse_update_office_hours($officehours);
}

if ($removebuttonpressed) {
    $toremove = filter_input(INPUT_GET, 'moveselected', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY);

    if (isset($toremove)) {
        cucourse_update_extra_module_field('remove', $toremove);
    }
} else if ($addbuttonpressed) {
    if (isset($_REQUEST['moveselected'])) {
        $toremove = $_REQUEST['moveselected'];
        cucourse_update_extra_module_field('add', $toremove);
    }
}

redirect(new moodle_url('/my'));
