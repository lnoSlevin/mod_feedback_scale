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

defined('MOODLE_INTERNAL') OR die('not allowed');
require_once($CFG->dirroot . '/mod/feedback/item/feedback_item_class.php');


define('FEEDBACK_SCALE_START_SEP', '>>>>>');
define('FEEDBACK_SCALE_VALUES_SEP', '|');
define('FEEDBACK_SCALE_END_SEP', '<<<<<');
define('FEEDBACK_SCALE_IGNOREEMPTY', 'i');
define('FEEDBACK_SCALE_HIDENOSELECT', 'h');

class feedback_item_scale extends feedback_item_base {

    protected $type = "scale";

    public function build_editform($item, $feedback, $cm) {
        global $DB, $CFG;
        require_once('scale_form.php');

        //get the lastposition number of the feedback_items
        $position = $item->position;
        $lastposition = $DB->count_records('feedback_item', array('feedback' => $feedback->id));
        if ($position == -1) {
            $i_formselect_last = $lastposition + 1;
            $i_formselect_value = $lastposition + 1;
            $item->position = $lastposition + 1;
        } else {
            $i_formselect_last = $lastposition;
            $i_formselect_value = $item->position;
        }
        //the elements for position dropdownlist
        $positionlist = array_slice(range(0, $i_formselect_last), 1, $i_formselect_last, true);

        $item->presentation = empty($item->presentation) ? '' : $item->presentation;
        $info = $this->get_info($item);

        $item->ignoreempty = $this->ignoreempty($item);
        $item->hidenoselect = $this->hidenoselect($item);

        //all items for dependitem
        $feedbackitems = feedback_get_depend_candidates_for_item($feedback, $item);
        $commonparams = array('cmid' => $cm->id,
            'id' => isset($item->id) ? $item->id : null,
            'typ' => $item->typ,
            'items' => $feedbackitems,
            'feedback' => $feedback->id);

        //build the form
        $customdata = array('item' => $item,
            'common' => $commonparams,
            'positionlist' => $positionlist,
            'position' => $position,
            'info' => $info);

        $this->item_form = new scaleform('edit_item.php', $customdata);
    }

    public function save_item() {
        global $DB;

        if (!$this->get_data()) {
            return false;
        }
        $item = $this->item;

        if (isset($item->clone_item) AND $item->clone_item) {
            $item->id = ''; //to clone this item
            $item->position++;
        }

        $this->set_ignoreempty($item, $item->ignoreempty);
        $this->set_hidenoselect($item, $item->hidenoselect);

        $item->hasvalue = $this->get_hasvalue();
        if (!$item->id) {
            $item->id = $DB->insert_record('feedback_item', $item);
        } else {
            $DB->update_record('feedback_item', $item);
        }

        return $DB->get_record('feedback_item', array('id' => $item->id));
    }

    //gets an array with three values(typ, name, XXX)
    //XXX is an object with answertext, answercount and quotient

    /**
     * Helper function for collected data, both for analysis page and export to excel
     *
     * @param stdClass $item the db-object from feedback_item
     * @param int $groupid
     * @param int $courseid
     * @return array
     */
    protected function get_analysed($item, $groupid = false, $courseid = false) {
        $info = $this->get_info($item);
        $analysed_item = array();
        $analysed_item[] = $item->typ;
        $analysed_item[] = format_string($item->name);

        //get the possible answers
        $answers = null;

        for ($index = $info->scalefrom; $index <= $info->scaleto; $index++) {
            $answers[] = $index;
        }
        $answers[0] .= " ({$info->scalelabelfrom})";
        $answers[count($answers) - 1] .= " ({$info->scalelabelto})";

        if (!is_array($answers)) {
            return null;
        }

        //get the values
        $values = feedback_get_group_values($item, $groupid, $courseid, $this->ignoreempty($item));
        if (!$values) {
            return null;
        }

        //get answertext, answercount and quotient for each answer
        $analysed_answer = array();

        $sizeofanswers = count($answers);
        for ($i = 1; $i <= $sizeofanswers; $i++) {
            $ans = new stdClass();
            $ans->answertext = $answers[$i - 1];
            $ans->answercount = 0;
            foreach ($values as $value) {
                //ist die Antwort gleich dem index der Antworten + 1?
                if ($value->value == $i) {
                    $ans->answercount++;
                }
            }
            $ans->quotient = $ans->answercount / count($values);
            $analysed_answer[] = $ans;
        }

        $analysed_item[] = $analysed_answer;
        return $analysed_item;
    }

    public function get_printval($item, $value) {
        $info = $this->get_info($item);

        $printval = '';

        if (!isset($value->value)) {
            return $printval;
        }


        for ($index = $info->scalefrom; $index <= $info->scaleto; $index++) {
            $presentation[] = $index;
        }


        $index = 1;
        foreach ($presentation as $pres) {
            if ($value->value == $index) {
                $printval = format_string($pres);
                break;
            }
            $index++;
        }

        return $printval;
    }

    public function print_analysed($item, $itemnr = '', $groupid = false, $courseid = false) {
        global $OUTPUT;

        $analysed_item = $this->get_analysed($item, $groupid, $courseid);
        if ($analysed_item) {
            $itemname = $analysed_item[1];
            echo "<table class=\"analysis itemtype_{$item->typ}\">";
            echo '<tr><th colspan="2" align="left">';
            echo $itemnr . ' ';
            if (strval($item->label) !== '') {
                echo '(' . format_string($item->label) . ') ';
            }
            echo format_string($itemname);
            echo '</th></tr>';
            echo "</table>";
            $analysed_vals = $analysed_item[2];
            $count = 0;
            $data = [];
            foreach ($analysed_vals as $val) {
                $quotient = format_float($val->quotient * 100, 2);
                $strquotient = '';
                if ($val->quotient > 0) {
                    $strquotient = ' (' . $quotient . ' %)';
                }
                $answertext = format_text(trim($val->answertext), FORMAT_HTML, array('noclean' => true, 'para' => false));

                $data['labels'][$count] = $answertext;
                $data['series'][$count] = $val->answercount;
                $data['series_labels'][$count] = $val->answercount . $strquotient;
                $count++;
            }
            $chart = new \core\chart_bar();
            $chart->set_horizontal(true);
            $series = new \core\chart_series(format_string(get_string("responses", "feedback")), $data['series']);
            $series->set_labels($data['series_labels']);
            $chart->add_series($series);
            $chart->set_labels($data['labels']);

            echo $OUTPUT->render($chart);
        }
    }

    public function excelprint_item(&$worksheet, $row_offset, $xls_formats, $item, $groupid, $courseid = false) {

        $analysed_item = $this->get_analysed($item, $groupid, $courseid);

        $data = $analysed_item[2];

        //frage schreiben
        $worksheet->write_string($row_offset, 0, $item->label, $xls_formats->head2);
        $worksheet->write_string($row_offset, 1, $analysed_item[1], $xls_formats->head2);
        if (is_array($data)) {
            $sizeofdata = count($data);
            for ($i = 0; $i < $sizeofdata; $i++) {
                $analysed_data = $data[$i];

                $worksheet->write_string($row_offset, $i + 2, trim($analysed_data->answertext), $xls_formats->head2);

                $worksheet->write_number($row_offset + 1, $i + 2, $analysed_data->answercount, $xls_formats->default);

                $worksheet->write_number($row_offset + 2, $i + 2, $analysed_data->quotient, $xls_formats->procent);
            }
        }
        $row_offset += 3;
        return $row_offset;
    }

    /**
     * Options for the scale element
     * @param stdClass $item
     * @return array
     */
    protected function get_options($item) {
        $info = $this->get_info($item);


        $options = array();

        for ($index = $info->scalefrom; $index <= $info->scaleto; $index++) {
            $options[$index + 1] = format_text($index, FORMAT_HTML, array('noclean' => true, 'para' => false));
        }


        return $options;
    }

    /**
     * Adds an input element to the complete form
     *
     * This element has many options - it can be displayed as group or radio elements,
     * group of checkboxes or a dropdown list.
     *
     * @param stdClass $item
     * @param mod_feedback_complete_form $form
     */
    public function complete_form_element($item, $form) {
        $info = $this->get_info($item);
        $name = $this->get_display_name($item);
        $class = 'scale';
        $inputname = $item->typ . '_' . $item->id;
        $options = $this->get_options($item);
        $separator = ' ';
        $tmpvalue = $form->get_item_value($item);


        // Display group or radio or checkbox elements.
        $class .= ' scale-horizontal';
        $objs = [];

        $objs[] = ['static', '', '', $info->scalelabelfrom];
        // Radio.
        if (!array_key_exists(0, $options)) {
            // Always add '0' as hidden element, otherwise form submit data may not have this element.
            $objs[] = ['hidden', $inputname . '[0]'];
        }

        foreach ($options as $idx => $label) {
            $objs[] = ['radio', $inputname . '[0]', '', $label, $idx, array('vertical' => 'vertical')];
        }
        $objs[] = ['static', '', '', $info->scalelabelto];
        // Span to hold the element id. The id is used for drag and drop reordering.
        $objs[] = ['static', '', '', html_writer::span('', '', ['id' => 'feedback_item_' . $item->id])];
        $element = $form->add_form_group_element($item, 'group_' . $inputname, $name, $objs, $separator, $class);
        $form->set_element_default($inputname . '[0]', $tmpvalue);
        $form->set_element_type($inputname . '[0]', PARAM_INT);



        // Process 'required' rule.
        if ($item->required) {
            $elementname = $element->getName();
            $form->add_validation_rule(function($values, $files) use ($elementname, $item) {
                $inputname = $item->typ . '_' . $item->id;
                return empty($values[$inputname]) || !array_filter($values[$inputname]) ?
                        array($elementname => get_string('required')) : true;
            });
        }
    }

    /**
     * Prepares value that user put in the form for storing in DB
     * @param array $value
     * @return string
     */
    public function create_value($value) {
        $value = array_unique(array_filter($value));
        return join(FEEDBACK_SCALE_VALUES_SEP, $value);
    }

    /**
     * Compares the dbvalue with the dependvalue
     *
     * @param stdClass $item
     * @param string $dbvalue is the value input by user in the format as it is stored in the db
     * @param string $dependvalue is the value that it needs to be compared against
     */
    public function compare_value($item, $dbvalue, $dependvalue) {

        if (is_array($dbvalue)) {
            $dbvalues = $dbvalue;
        } else {
            $dbvalues = explode(FEEDBACK_SCALE_VALUES_SEP, $dbvalue);
        }

        $info = $this->get_info($item);

        if ($info->presentation) {
            $presentation = array();
            $start = explode(FEEDBACK_SCALE_START_SEP, $presentation);
            $end = explode(FEEDBACK_SCALE_END_SEP, $start[1]);
            $itemvalues = explode(FEEDBACK_SCALE_VALUES_SEP, $end[0]);


            $info->scalefrom = $itemvalues[0];
            $info->scaleto = $itemvalues[1];
            $info->scalelabelfrom = $start[0];
            $info->scalelabelto = $end[1];

            for ($index1 = $info->scalefrom; $index1 < $info->scaleto; $index1++) {
                $presentation[$index1] = $index1;
            }
        }

        $index = 1;
        foreach ($presentation as $pres) {
            foreach ($dbvalues as $dbval) {
                if ($dbval == $index AND trim($pres) == $dependvalue) {
                    return true;
                }
            }
            $index++;
        }
        return false;
    }

    public function get_info($item) {
        $presentation = empty($item->presentation) ? '' : $item->presentation;

        $info = new stdClass();
        //check the subtype of the scale


        $info->presentation = '';
        if ($presentation) {
            $start = explode(FEEDBACK_SCALE_START_SEP, $presentation);
            $end = explode(FEEDBACK_SCALE_END_SEP, $start[1]);
            $itemvalues = explode(FEEDBACK_SCALE_VALUES_SEP, $end[0]);

            $info->scalefrom = $itemvalues[0];
            $info->scaleto = $itemvalues[1];
            $info->scalelabelfrom = $start[0];
            $info->scalelabelto = $end[1];
        }


        return $info;
    }

    public function set_ignoreempty($item, $ignoreempty = true) {
        $item->options = str_replace(FEEDBACK_SCALE_IGNOREEMPTY, '', $item->options);
        if ($ignoreempty) {
            $item->options .= FEEDBACK_SCALE_IGNOREEMPTY;
        }
    }

    public function ignoreempty($item) {
        if (strstr($item->options, FEEDBACK_SCALE_IGNOREEMPTY)) {
            return true;
        }
        return false;
    }

    public function set_hidenoselect($item, $hidenoselect = true) {
        $item->options = str_replace(FEEDBACK_SCALE_HIDENOSELECT, '', $item->options);
        if ($hidenoselect) {
            $item->options .= FEEDBACK_SCALE_HIDENOSELECT;
        }
    }

    public function hidenoselect($item) {
        if (strstr($item->options, FEEDBACK_SCALE_HIDENOSELECT)) {
            return true;
        }
        return false;
    }

    /**
     * Return the analysis data ready for external functions.
     *
     * @param stdClass $item     the item (question) information
     * @param int      $groupid  the group id to filter data (optional)
     * @param int      $courseid the course id (optional)
     * @return array an array of data with non scalar types json encoded
     * @since  Moodle 3.3
     */
    public function get_analysed_for_external($item, $groupid = false, $courseid = false) {

        $externaldata = array();
        $data = $this->get_analysed($item, $groupid, $courseid);

        if (!empty($data[2]) && is_array($data[2])) {
            foreach ($data[2] as $d) {
                $externaldata[] = json_encode($d);
            }
        }
        return $externaldata;
    }

}
