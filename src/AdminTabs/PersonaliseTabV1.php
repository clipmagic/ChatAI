<?php
/*
 * Copyright (c) 2025.
 * Clip Magic - Prue Rowland
 * Web: www.clipmagic.com.au
 * Email: admin@clipmagic.com.au
 *
 * ProcessWire 3.x
 * Copyright (C) 2014 by R
 * Licensed under GNU/GPL
 *
 * https://processwire.com
 */

namespace ChatAI\AdminTabs;

use ProcessWire\Process;

class PersonaliseTabV1
{
    public function build(Process $process, $form, $store, $languages): array
    {
        $m = $process->modules;

        $inputfields = $m->get('InputfieldWrapper');
        $inputfields->addClass('WireTab');
        $inputfields->attr('name', 'personalise');

        $TabId = $process->_('Personalise');
        $inputfields->attr('id', $TabId);

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'fsbot');
        $fieldset->label($process->_('Chatbot Persona'));

        // Name
        $f = $m->get('InputfieldText');
        $f->attr('name', 'botname');
        $f->label($process->_('Chatbot name'));
        $value = $data->botname ??  $process->_('Fred');
        $f->attr('value', $value);
        $f->columnWidth(20);
        $f->useLanguages = true;

        $f->stripTags = true;
        $fieldset->add($f);

        // Role
        $f = $m->get('InputfieldText');
        $f->attr('name', 'botrole');
        $f->label($process->_('Role'));
        $value = $data->botrole ??  $process->_('a friendly and helpful assistant');
        $f->attr('value', $value);
        $f->columnWidth(30);
        $f->stripTags = true;
        $fieldset->add($f);

        // Business name
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'bizname');
        $f->label($process->_('Employer'));
        $value = !empty($data->bizname) ? $data->bizname : null;
        $f->attr('value', $value);
        $f->columnWidth(20);
        $f->stripTags = true;
        $fieldset->add($f);

        // Tone
        $f = $m->get('InputfieldText');
        $f->name('tone');
        $f->label($process->_('Tone of the answers'));
        $value = $data->tone ?? $process->_('friendly and professional');
        $f->attr('value', $value);
        $f->columnWidth(30);
        $f->stripTags = true;
        $fieldset->add($f);

        // Intro
        $f = $m->get('InputfieldText');
        $f->attr('name', 'botintro');
        $f->label($process->_('Welcome message'));
        $value = $data->botintro ?? $process->_("Hello! My name is {botname}. How may I help you today?");
        $f->attr('value', $value);
        $f->columnWidth(100);
        $f->useLanguages = true;
        $f->stripTags = true;
        $fieldset->add($f);
        $inputfields->add($fieldset);

        // User widget settings
        // Text field placeholder text
        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'widgetsettings');
        $fieldset->label('User input form');

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'placeholder');
        $f->label('Input field placeholder');
        $value = $data->placeholder ?? $process->_('Ask a question...');
        $f->attr('value', $value);
        $f->columnWidth(50);
        $f->stripTags = true;
        $fieldset->add($f);

        // Submit button text
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'sendtobot');
        $f->label('Send button text');
        $value = $data->sendtobot ?? $process->_('Send');
        $f->attr('value', $value);
        $f->columnWidth(50);
        $f->stripTags = true;
        $fieldset->add($f);
        $inputfields->add($fieldset);


        // Warning messages
        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'warnings');
        $fieldset->label('Warning messages');

        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'oneleft');
        $f->label('One more message allowed');

        $value = $data->oneleft ?? $process->_("Warning:\nYou have one message remaining. Use it wisely!");
        $f->attr('value', $value);
        $f->columnWidth(50);
        $f->stripTags = true;
        $fieldset->add($f);


        $form->add($inputfields);

        $output = [];
        $key = $TabId;
        $value = $inputfields->render();
        $output[$key] = $value;
        $output['form'] = $form;

        return $output;
    }
}



