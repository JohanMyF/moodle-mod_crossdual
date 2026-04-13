<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_crossduel_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        /*
         * -------------------------------------------------------------
         * General section (Moodle standard)
         * -------------------------------------------------------------
         */
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Activity name
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Standard intro (description)
        $this->standard_intro_elements();

        /*
         * -------------------------------------------------------------
         * Cross Duel settings (YOUR section)
         * -------------------------------------------------------------
         */
        $mform->addElement('header', 'crossduelsettings', 'Cross Duel settings');

        // Instructions
        $instructions = implode("\n", [
            'Enter one word and clue per line using the format:',
            'word|clue',
            '',
            'Examples:',
            'algorithm|A step-by-step procedure for solving a problem',
            'variable|A named value that can change',
            'loop|A repeated sequence of instructions',
            '',
            'Rules:',
            '- Text before | is the answer word',
            '- Text after | is the clue',
            '- Use one entry per line',
            '- Blank lines are ignored',
            '- Maximum allowed: 50 entries',
            '- For version 1, simple single words are safest',
        ]);

        $mform->addElement(
            'static',
            'crossduelinstructions',
            'How to enter words and clues',
            nl2br(s($instructions))
        );

        // Word list
        $mform->addElement(
            'textarea',
            'wordlist',
            get_string('wordlist', 'crossduel'),
            ['rows' => 16, 'cols' => 80]
        );
        $mform->setType('wordlist', PARAM_RAW);

        // Reveal %
        $mform->addElement(
            'text',
            'revealpercent',
            get_string('revealpercent', 'crossduel'),
            ['size' => '6']
        );
        $mform->setType('revealpercent', PARAM_FLOAT);
        $mform->setDefault('revealpercent', 10);

        $mform->addElement(
            'static',
            'revealpercenthelp',
            '',
            'Enter the percentage of letters to reveal before the game begins.'
        );

        // Pass %
        $mform->addElement(
            'text',
            'passpercentage',
            get_string('passpercentage', 'crossduel'),
            ['size' => '6']
        );
        $mform->setType('passpercentage', PARAM_FLOAT);
        $mform->setDefault('passpercentage', 60);

        $mform->addElement(
            'static',
            'passpercentagehelp',
            '',
            'Percentage required to pass this activity.'
        );

        /*
         * -------------------------------------------------------------
         * Standard Moodle grading (IMPORTANT FIX)
         * -------------------------------------------------------------
         */
        $this->standard_grading_coursemodule_elements();

        /*
         * -------------------------------------------------------------
         * Standard module settings
         * -------------------------------------------------------------
         */
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        /*
         * WORD LIST VALIDATION
         */
        $rawtext = trim((string)($data['wordlist'] ?? ''));

        if ($rawtext === '') {
            $errors['wordlist'] = get_string('nowords', 'crossduel');
        } else {
            $lines = preg_split('/\r\n|\r|\n/', $rawtext);
            $validcount = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;

                if (substr_count($line, '|') !== 1) {
                    $errors['wordlist'] = get_string('invalidwordformat', 'crossduel');
                    break;
                }

                list($word, $clue) = array_map('trim', explode('|', $line, 2));

                if ($word === '' || $clue === '') {
                    $errors['wordlist'] = get_string('invalidwordformat', 'crossduel');
                    break;
                }

                $validcount++;
            }

            if (!isset($errors['wordlist'])) {
                if ($validcount === 0) {
                    $errors['wordlist'] = get_string('nowords', 'crossduel');
                } else if ($validcount > 50) {
                    $errors['wordlist'] = get_string('toomanywords', 'crossduel');
                }
            }
        }

        /*
         * REVEAL %
         */
        if (!is_numeric($data['revealpercent']) || $data['revealpercent'] < 5 || $data['revealpercent'] > 50) {
            $errors['revealpercent'] = 'Must be between 5 and 50.';
        }

        /*
         * PASS %
         */
        if (!is_numeric($data['passpercentage']) || $data['passpercentage'] < 0 || $data['passpercentage'] > 100) {
            $errors['passpercentage'] = 'Must be between 0 and 100.';
        }

        return $errors;
    }
}