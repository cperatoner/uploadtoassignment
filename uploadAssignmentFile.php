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
 * Manage files in folder module instance
 *
 * @package   mod_folder
 * @copyright 2010 Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require '../../config.php';
require_once "$CFG->dirroot/mod/assign/locallib.php";
require_once "$CFG->dirroot/repository/lib.php";
require_once "$CFG->dirroot/lib/form/filemanager.php";

// echo json_encode(array('status' => true, "user" => $USER));
// die;
$urltogo = $CFG->wwwroot . '/login/index.php';
if (!isloggedin()) {
    die(json_encode(array("status" => false, "message" => "Please login first.")));
}

$redirect_url = $CFG->wwwroot . '/course/view.php?id=' . $_GET['course_id'];
$dir = $CFG->assignmentUploadDir;
$files = scandir($dir, 1);
$luserid = $USER->id;

function filter_files($var)
{
    global $USER;
    if ($var != '') {
        $fileInfo = explode('-', $var ?? '');
        $userInfo = explode('^', $fileInfo[2] ?? '');
        if (!empty($fileInfo) && trim($fileInfo[0]) == trim($_GET['course_id']) && trim($userInfo[0]) == trim($USER->id)) {
            return true;
        }
    }
}

$files = array_values(array_filter($files, "filter_files"));

if (!empty($files)) {
	try {
        foreach ($files as $file) {
                for ($i=0; $i < 2; $i++) { 
                    
                    $fileex = explode('^', $file);
                    $fileInfor = explode('-', $fileex[0]);
                    if (count($fileInfor) == 3) {
                        $fileTitle = $fileex[1];
                    }
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    if (!empty($fileInfor) && $fileInfor[0] != '.' && $fileInfor[0] != '..') {
                        $course_id = $fileInfor[0];
                        $assign_id = $fileInfor[1];
                        $student_id = $fileInfor[2];
                        $userid = $student_id;
                        $currentFilePath = $dir . $file;
                        $sql = "SELECT * FROM mdl_course_modules as mfold WHERE id=$assign_id";
                        $folderInfo = $DB->get_records_sql($sql);
                        if (!empty($folderInfo)) {
                            $cm = get_coursemodule_from_id('assign', $assign_id, 0, true, MUST_EXIST);
                            $context = context_module::instance($cm->id, MUST_EXIST);
                            $assign = $DB->get_record('assign', array('id' => $cm->instance), '*', MUST_EXIST);

                            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
                            require_login($course, false, $cm);
                            $get_user_groups = groups_get_user_groups($course_id, $userid);
                            $groupid = 0;
                            if ($assign->teamsubmission) {
                                if ($assign->preventsubmissionnotingroup == 1) {
                                    if (count($get_user_groups[0]) > 1 || empty($get_user_groups[0])) {
                                        try {
                                            echo json_encode(array("status" => true, "message" => "The setting 'Require group to make submission' is enabled and some users are either not a member of any group, or are a member of more than one group, so are unable to make submissions."));
                                        } catch (moodle_exception $e) {
                                            die(json_encode((object) array('status' => false, 'message' => $e->getMessage())));
                                        }
                                    } elseif (count($get_user_groups) == 1) {
                                        $userid2 = 0;
                                        $groupid = $get_user_groups[0]['0'];
                                    } else {
                                        $userid2 = $userid;
                                        $groupid = 0;
                                    }
                                } else {
                                    if (count($get_user_groups[0]) > 1 || empty($get_user_groups[0])) {
                                        $userid2 = 0;
                                        $groupid = 0;
                                    } elseif (count($get_user_groups) == 1) {
                                        $userid2 = 0;
                                        $groupid = $get_user_groups[0]['0'];
                                    } else {
                                        $userid2 = $userid;
                                        $groupid = 0;
                                    }
                                }
                            } else {
                                $userid2 = $userid;
                                $groupid = 0;
                            }



                            $sql = "SELECT * FROM mdl_assign_submission as assign WHERE assignment=$assign->id AND userid=$userid2 AND groupid=$groupid";
                            $submission = $DB->get_record_sql($sql);
                            
                            if (!empty($submission)) {
                                $sub_id = $submission->id;
                            } else {
                                $submission = new stdClass();
                                $submission->assignment = $assign->id;
                                $submission->userid = $userid;
                                $submission->attemptnumber = 0;
                                $submission->status = 'submitted';
                                $submission->groupid = $groupid;
                                $submission->latest = 1;
                                $submission->timecreated = time();
                                $submission->timemodified = time();
                                $submission->timestarted = time();
                                $sub_id = $DB->insert_record('assign_submission', $submission);

                                $sql = "SELECT * FROM mdl_assign_submission as assign WHERE id=$sub_id";
                            	$submission = $DB->get_record_sql($sql);
                            }



                            // $PAGE->set_url('/mod/assign/index.php', array('id' => $cm->id));
                            // $PAGE->set_title($course->shortname . ': ' . $assign->name);
                            // $PAGE->set_heading($course->fullname);
                            // $PAGE->set_activity_record($assign);
                            // $newFilePath= '/'.$course_id.$assign_id.$userid.'/';
                            $newFilePath = '/';
                            $data = new stdClass();
                            $data->id = $cm->id;
                            $data->userid = $userid;
                            $maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes);
                            $options = array('subdirs' => 1, 'maxbytes' => $maxbytes, 'maxfiles' => -1, 'accepted_types' => '*', 'maxfiles' => 10, 'return_types' => 10);
                            file_prepare_standard_filemanager($data, 'files', $options, $context, 'assignsubmission_file', 'submission_files', $sub_id);
                            $formdata = new stdClass();
                            $formdata->id = $cm->id;
                            $formdata->action = 'savesubmission';
                            $formdata->userid = $userid;
                            $formdata->submitbutton = 'Save changes';

                            $formdata->files_filemanager = $data->files_filemanager;

                            $fmoptions = new stdClass();
                            $fmoptions->maxbytes = $options['maxbytes'];
                            $fmoptions->maxfiles = $options['maxfiles'];
                            $fmoptions->client_id = uniqid();
                            $fmoptions->itemid = $data->files_filemanager;
                            $fmoptions->subdirs = $options['subdirs'];
                            $fmoptions->accepted_types = $options['accepted_types'];
                            $fm = new form_filemanager($fmoptions);
                            $client_id = $fm->options->client_id;
                            $sql = "SELECT * FROM mdl_files as mf WHERE contextid=$context->id AND filepath='$newFilePath'";
                            $folderInfo = $DB->get_records_sql($sql);

                            if (empty($folderInfo)) {
                                $postData = array(
                                    'client_id' => $client_id,
                                    'filepath' => '/',
                                    'userid' => $userid,
                                    'itemid' => $data->files_filemanager,
                                    'newdirname' => $newFilePath,
                                    'action' => 'mkdir',
                                    'sesskey' => $USER->sesskey,
                                );
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $CFG->wwwroot . "/repository/draftfiles_ajax_custom.php?action=mkdir&itemid=$data->files_filemanager&newdirname=$newFilePath&filepath=/&client_id=$client_id&userid=$userid&sesskey=$USER->sesskey");
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                                curl_setopt($ch, CURLOPT_HEADER, 0);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                $output = curl_exec($ch);
                                curl_close($ch);

                                $sql = "SELECT * FROM mdl_files as mf WHERE itemid=$data->files_filemanager AND filepath='$newFilePath'";
                                $folderInfo = $DB->get_records_sql($sql);
                                if ($folderInfo) {
                                    foreach ($folderInfo as $key => $folderin) {
                                        $folderin->filearea = 'submission_files';
                                        $folderin->contextid = $context->id;
                                        $folderin->component = 'assignsubmission_file';
                                        $folderin->itemid = $data->files_filemanager;
                                        $res = $DB->update_record('files', $folderin);
                                    }
                                }
                            }

                            $author = urlencode($USER->firstname . '+' . $USER->lastname);
                            $fileTitle = urlencode($fileTitle);
                            $target_url = $CFG->wwwroot . "/repository/repository_ajax_custom.php?action=upload&overwrite=true&title=$fileTitle&repo_id=5&author=$author&userid=$userid";
                            //$target_url = $CFG->wwwroot . "/repository/repository_ajax.php?action=upload&overwrite=true&title=$fileTitle&repo_id=4&author=$author&userid=$userid";

                            if (function_exists('curl_file_create')) { // php 5.5+
                                $cFile = curl_file_create($currentFilePath);
                            } else { //
                                $cFile = '@' . realpath($currentFilePath);
                            }

                            $post = array(
                                'repo_upload_file' => $cFile, 
                                'license' => 'allrightsreserved', 
                                'p' => '', 'page' => '', 
                                'env' => 'filemanager', 
                                'client_id' => $client_id, 
                                'itemid' => $data->files_filemanager, 
                                'maxbytes' => -1, 'areamaxbytes' => -1, 
                                'ctx_id' => $context->id, 
                                'savepath' => $newFilePath, 
                                'sesskey', $USER->sesskey);

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $target_url);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                            $result = curl_exec($ch);
                            curl_close($ch);

                            if (empty($submission)) {
                                $submission = new stdClass();
                                $submission->assignment = $assign->id;
                                $submission->userid = $userid;
                                $submission->attemptnumber = 0;
                                $submission->status = 'submitted';
                                $submission->groupid = $groupid;
                                $submission->latest = 1;
                                $submission->timecreated = time();
                                $submission->timemodified = time();
                                $submission->timestarted = time();
                                $submission_id = $DB->insert_record('assign_submission', $submission);
                            } else {
                                $submission->status = 'submitted';
                                $submission->timemodified = time();
                                $DB->update_record('assign_submission', $submission);
                                $submission_id = $submission->id;
                            }
                            $sql = "SELECT * FROM mdl_assignsubmission_file WHERE assignment=$assign->id AND submission=$submission_id";
                            $submission_file = $DB->get_record_sql($sql);
                            if (empty($submission_file)) {
                                $assignsubmission = new stdClass();
                                $assignsubmission->assignment = $assign->id;
                                $assignsubmission->submission = $submission_id;
                                $assignsubmission->numfiles = 1;
                                $assignsubmission_insertid = $DB->insert_record('assignsubmission_file', $assignsubmission);
                            } else {
                            	if($i == 1){
	                                $submission_file->numfiles = ++$submission_file->numfiles;
	                                $DB->update_record('assignsubmission_file', $submission_file);
	                            }
                            }

                            $formdata = file_postupdate_standard_filemanager($formdata, 'files', $options, $context, 'assignsubmission_file', 'submission_files', $submission_id);

                        if($i == 1){
                            unlink($dir . $file);
                        }    
                    }
                }
            }
            if($i == 2){
                echo json_encode(array("status" => true, "message" => "Files have been uploaded Successfully."));
            }
        }
    } catch (moodle_exception $e) {
        die(json_encode((object) array('status' => false, 'message' => $e->getMessage(), 'line' => $e->getLine())));
    }
} else {
    try {
        echo json_encode(array("status" => false, "message" => "No files to upload for this course."));
    } catch (moodle_exception $e) {
        die(json_encode((object) array('status' => false, 'message' => $e->getMessage(), 'line' => $e->getLine())));
    }
}
