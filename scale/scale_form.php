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

require_once($CFG->dirroot . '/mod/feedback/item/feedback_item_form_class.php');

class scaleform extends feedback_item_form {

    protected $type = "scale";

    public function definition() {
        $item = $this->_customdata['item'];
        $common = $this->_customdata['common'];
        $positionlist = $this->_customdata['positionlist'];
        $position = $this->_customdata['position'];

        $mform = & $this->_form;

        $mform->addElement('header', 'general', get_string($this->type, 'feedback'));

        $mform->addElement('advcheckbox', 'required', get_string('required', 'feedback'), '', null, array(0, 1));

        $mform->addElement('text', 'name', get_string('item_name', 'feedback'), array('size' => FEEDBACK_ITEM_NAME_TEXTBOX_SIZE,
            'maxlength' => 255));

        $mform->addElement('text', 'label', get_string('item_label', 'feedback'), array('size' => FEEDBACK_ITEM_LABEL_TEXTBOX_SIZE,
            'maxlength' => 255));

        $mform->addElement('selectyesno', 'ignoreempty', get_string('do_not_analyse_empty_submits', 'feedback'));

        $mform->addElement('select', 'scalefrom', get_string('scalefrom', 'feedback'), array('0' => '0', '1' => '1'));

        $arrayto = array();
        for ($index = 0; $index <= 10; $index++) {
            $arrayto[$index] = $index;
        }
        $mform->addElement('select', 'scaleto', get_string('scaleto', 'feedback'), $arrayto);

        $mform->addElement('text', 'scalelabelfrom', get_string('scalelabelfrom', 'feedback'));
        $mform->setType('scalelabelfrom', PARAM_RAW);
        $mform->addElement('text', 'scalelabelto', get_string('scalelabelto', 'feedback'));
        $mform->setType('scalelabelto', PARAM_RAW);
        $mform->addElement('static', 'hint', '', get_string('use_one_line_for_each_value', 'feedback'));

        parent::definition();
        $this->set_data($item);
    }

    public function set_data($item) {
        $info = $this->_customdata['info'];
        $item->scalefrom = $info->scalefrom;
        $item->scaleto = $info->scaleto;
        $item->scalelabelfrom = $info->scalelabelfrom;
        $item->scalelabelto = $info->scalelabelto;

        return parent::set_data($item);
    }

    public function get_data() {
        if (!$item = parent::get_data()) {
            return false;
        }

        $item->presentation = $item->scalelabelfrom . FEEDBACK_SCALE_START_SEP;
        $item->presentation .= $item->scalefrom . FEEDBACK_SCALE_VALUES_SEP;
        $item->presentation .= $item->scaleto . FEEDBACK_SCALE_END_SEP;
        $item->presentation .= $item->scalelabelto;

        if (!isset($item->hidenoselect)) {
            $item->hidenoselect = 1;
        }
        if (!isset($item->ignoreempty)) {
            $item->ignoreempty = 0;
        }

        return $item;
    }

}
