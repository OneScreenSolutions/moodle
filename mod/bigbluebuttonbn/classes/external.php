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
 * BigBlueButtonBN external API
 *
 * @package   mod_bigbluebuttonbn
 * @category  external
 * @copyright 2018 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * BigBlueButtonBN external functions
 *
 * @package   mod_bigbluebuttonbn
 * @category  external
 * @copyright 2018 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_bigbluebuttonbn_external extends external_api
{

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_bigbluebuttonbn_parameters()
    {
        return new external_function_parameters(
            array(
                'bigbluebuttonbnid' => new external_value(PARAM_INT, 'bigbluebuttonbn instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $bigbluebuttonbnid the bigbluebuttonbn instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_bigbluebuttonbn($bigbluebuttonbnid)
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/bigbluebuttonbn/lib.php");

        $params = self::validate_parameters(
            self::view_bigbluebuttonbn_parameters(),
            array(
                'bigbluebuttonbnid' => $bigbluebuttonbnid
            )
        );
        $warnings = array();

        // Request and permission validation.
        $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id' => $params['bigbluebuttonbnid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($bigbluebuttonbn, 'bigbluebuttonbn');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/bigbluebuttonbn:view', $context);

        // Call the bigbluebuttonbn/lib API.
        bigbluebuttonbn_view($bigbluebuttonbn, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_bigbluebuttonbn_returns()
    {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_bigbluebuttonbns_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_bigbluebuttonbns_by_courses_parameters()
    {
        return new external_function_parameters(
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course id'),
                    'Array of course ids',
                    VALUE_DEFAULT,
                    array()
                ),
            )
        );
    }

    /**
     * Returns a list of bigbluebuttonbns in a provided list of courses.
     * If no list is provided all bigbluebuttonbns that the user can view will be returned.
     *
     * @param array $courseids course ids
     * @return array of warnings and bigbluebuttonbns
     * @since Moodle 3.3
     */
    public static function get_bigbluebuttonbns_by_courses($courseids = array())
    {

        $warnings = array();
        $returnedbigbluebuttonbns = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_bigbluebuttonbns_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the bigbluebuttonbns in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $bigbluebuttonbns = get_all_instances_in_courses("bigbluebuttonbn", $courses);
            foreach ($bigbluebuttonbns as $bigbluebuttonbn) {
                $context = context_module::instance($bigbluebuttonbn->coursemodule);
                // Entry to return.
                $bigbluebuttonbn->name = external_format_string($bigbluebuttonbn->name, $context->id);

                list($bigbluebuttonbn->intro, $bigbluebuttonbn->introformat) = external_format_text(
                    $bigbluebuttonbn->intro,
                    $bigbluebuttonbn->introformat,
                    $context->id,
                    'mod_bigbluebuttonbn',
                    'intro',
                    null
                );
                $bigbluebuttonbn->introfiles = external_util::get_area_files(
                    $context->id,
                    'mod_bigbluebuttonbn',
                    'intro',
                    false,
                    false
                );

                $returnedbigbluebuttonbns[] = $bigbluebuttonbn;
            }
        }

        $result = array(
            'bigbluebuttonbns' => $returnedbigbluebuttonbns,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_bigbluebuttonbns_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_bigbluebuttonbns_by_courses_returns()
    {
        return new external_single_structure(
            array(
                'bigbluebuttonbns' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Module id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_INT, 'Course id'),
                            'name' => new external_value(PARAM_RAW, 'Name'),
                            'intro' => new external_value(PARAM_RAW, 'Description'),
                            'meetingid' => new external_value(PARAM_RAW, 'Meeting id'),
                            'introformat' => new external_format_value('intro', 'Summary format'),
                            'introfiles' => new external_files('Files in the introduction text'),
                            'timemodified' => new external_value(PARAM_INT, 'Last time the instance was modified'),
                            'section' => new external_value(PARAM_INT, 'Course section id'),
                            'visible' => new external_value(PARAM_INT, 'Module visibility'),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode'),
                            'groupingid' => new external_value(PARAM_INT, 'Grouping id'),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function can_join_parameters()
    {
        return new external_function_parameters(
            array(
                'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * This will check if current user can join the session from this module
     * @param int $cmid
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function can_join($cmid)
    {
        global $SESSION, $CFG;
        require_once($CFG->dirroot . "/mod/bigbluebuttonbn/locallib.php");

        $params = self::validate_parameters(
            self::can_join_parameters(),
            array(
                'cmid' => $cmid
            )
        );
        $canjoin = \mod_bigbluebuttonbn\locallib\bigbluebutton::can_join_meeting($cmid);
        $canjoin['cmid'] = $cmid;
        return $canjoin;
    }

    /**
     * Return value for can join function
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function can_join_returns()
    {
        return new external_single_structure(
            array(
                'can_join' => new external_value(PARAM_BOOL, 'Can join session'),
                'message' => new external_value(PARAM_RAW, 'Message if we cannot join', VALUE_OPTIONAL),
                'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED)
            )
        );
    }

    //Function for adding bigbluebutton mcq question into moodle DB
    /**
     * Paremeters contain a object conatining mcq question data
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_bigbluebuttonbn_mcq_question_parameters()
    {
        return new external_function_parameters(
            array(
                'questionObject' => new external_value(PARAM_RAW, 'Question Object')
            )
        );
    }

    public static function isRtl($value) {
        $rtlChar = '/[\x{0590}-\x{083F}]|[\x{08A0}-\x{08FF}]|[\x{FB1D}-\x{FDFF}]|[\x{FE70}-\x{FEFF}]/u';
        return preg_match($rtlChar, $value) != 0;
    }

    /**
     * This will add question to moodle DB
     * @param int $cmid
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function add_bigbluebuttonbn_mcq_question($questionObject)
    {
        global $DB, $USER;
        $message = "Successfully added question.";
        $statusCode = 200;
        //New question object created
        $question = new stdClass();

        //Checking if Question table exists
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('question')) {
            $message = "Question banks Plugin Not Found, Please view its documentation here: https://docs.moodle.org/dev/Question_bank_plugins";
            $statusCode = 230;
            goto ProgramError;
        }

        $quizJson = json_decode($questionObject);
        if (!$quizJson->questiontext) {
            //break program here and return error
            $message = "Question text not found";
            $statusCode = 210;
            goto ProgramError;
        }
        //assgning values to question
        if(self::isRtl($quizJson->questiontext)){
            $question->questiontext = '<p dir="rtl" style="text-align: right;">'.$quizJson->questiontext.'</p>';
        }
        else{
            $question->questiontext = '<p dir="ltr" style="text-align: left;">'.$quizJson->questiontext.'</p>';
        }
        //optional params
        $question->name =  $quizJson->name;
        $question->defaultmark =  $quizJson->defaultmark;
        $question->qtype =  $quizJson->qtype;

        //If optional data not sent setting default values
        if(!$question->name){
            $question->name = "Bigbluebutton-Quiz";
        }
        if(!$question->defaultmark){
            $question->defaultmark = 10;
        }
        if(!$question->qtype){
            $question->qtype = "multichoice";
        }
        $question->parent = 0;
        $question->questiontextformat = 1;
        $question->generalfeedbackformat = 1;
        $question->generalfeedback = '';
        $question->penalty = 0.333;
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->createdby = $USER->id;
        $question->timecreated = time();
        $question->modifiedby = $USER->id;
        $question->timemodified = time();
        $question->id = $DB->insert_record('question', $question);
        //Goto Label
        ProgramError:
        $dataObj = new stdClass();
        $responseData = [
            'code' => $statusCode,
            'message' => $message,
            'data' => json_encode($question)
        ];
        print_r(json_decode( json_encode($question)));
        echo "<script>console.log('Debug Objects: " . json_encode($question) . "' );</script>";
        return $responseData;
    }

    /**
     * Return response if question is added
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function add_bigbluebuttonbn_mcq_question_returns()
    {
        return new external_single_structure(
            array(
                'code' => new external_value(PARAM_INT, 'status code of response'),
                'message' => new external_value(PARAM_TEXT, 'message returned'),
                'data' => new external_value(PARAM_RAW, 'Data returned')
            )
        );
    }
}
