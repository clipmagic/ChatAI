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

use ProcessWire;
use ProcessWire\Wire;
use ProcessWire\Languages;

class PersonaliseTab
{
    public function build($form, $data): array
    {

        $m = \ProcessWire\Wire('modules');
//        $m = $m->get('ProcessChatAI');
//        $m = \ProcessWire\Wire('languages');
        $user = \ProcessWire\wire('user');
        $userLang = $user->language;



        $inputfields = $m->get('InputfieldWrapper');
        $inputfields->addClass('WireTab');
        $inputfields->attr('name', 'personalise');

        $TabId = $userLang->_('Personalise');
        $inputfields->attr('id', $TabId);

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'fsbot');
        $fieldset->label($m->_('Chatbot Persona'));

        // Name
        $f = $m->get('InputfieldText');
        $f->attr('name', 'botname');
        $f->label($m->_('Chatbot name'));
        $f->columnWidth(40);
        $f->useLanguages = true;
        $value = $data['botname'] ?? 'Fred';
        $f->attr('value', $value);
        $f->stripTags = true;
        $fieldset->add($f);


        // Intro
        $f = $m->get('InputfieldText');
        $f->attr('name', 'botintro');
        $f->label($m->_('Welcome message'));
        $f->columnWidth(60);
        $f->useLanguages = true;
        $value = $data['botintro'] ?? "Hello! My name is {botname}. How may I help you today?";
        $f->attr('value', $value);
        $f->stripTags = true;
        $fieldset->add($f);
        $inputfields->add($fieldset);


        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->label($m->_('Background'));

        // Role
        $f = $m->get('InputfieldText');
        $f->attr('name', 'botrole');
        $f->label($m->_('Role'));
        $value = $data['botrole'] ??  'a friendly and helpful assistant';
        $f->attr('value', $value);

        $f->columnWidth(30);
        $f->stripTags = true;
        $fieldset->add($f);

        // Business name
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'bizname');
        $f->label($m->_('Employer'));
        $value = $data['bizname'] ?? '';
        $f->attr('value', $value);
        $f->columnWidth(20);
        $f->stripTags = true;
        $fieldset->add($f);

        // Tone
        $f = $m->get('InputfieldText');
        $f->name('tone');
        $f->label($m->_('Tone of the answers'));
        $value = $data['tone'] ?? 'friendly and professional';
        $f->attr('value', $value);
        $f->columnWidth(30);
        $f->stripTags = true;
        $fieldset->add($f);

        $fieldset->add($f);
        $inputfields->add($fieldset);

        // User widget settings
        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'widgetsettings');
        $fieldset->label($m->_('User input form'));

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'input_placeholder');
        $f->label($m->_('Input field placeholder'));
        $f->columnWidth(50);
        $f->useLanguages = true;
        $value = $data['input_placeholder'] ?? "Ask a question...";
        $f->attr('value', $value);
        $f->stripTags = true;
        $fieldset->add($f);

        // Submit button text
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'submit_text');
        $f->label($m->_('Send button text'));
        $f->columnWidth(50);
        $f->useLanguages = true;
        $value = $data['submit_text'] ?? "Send";
        $f->attr('value', $value);
        $f->stripTags = true;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Warning messages
//        $fieldset = $m->get('InputfieldFieldset');
//        $fieldset->attr('name+id', 'warnings');
//        $fieldset->label($m->_('Warning messages'));
//
//        $f = $m->get('InputfieldTextarea');
//        $f->attr('name+id', 'oneleft');
//        $f->label($m->_('One more message allowed'));
//        $f->useLanguages = true;
//        $value = $data['oneleft'] ?? "One more message allowed";
//        $f->attr('value', $value);
//        $f->columnWidth(50);
//        $f->stripTags = true;
//        $fieldset->add($f);
//
//        $f = $m->get('InputfieldTextarea');
//        $f->attr('name+id', 'goodbye');
//        $f->label($m->_('No more messages allowed'));
//        $f->useLanguages = true;
//        $value = $data['goodbye'] ?? "Well, that was fun!\n I have to go now. Bye.";
//        $f->attr('value', $value);
//        $f->columnWidth(50);
//        $f->stripTags = true;
//        $fieldset->add($f);
//
//        $inputfields->add($fieldset);

        $form->add($inputfields);

        $output = [];
        $key = $TabId;
        $value = $inputfields->render();
        $output[$key] = $value;
        $output['form'] = $form;

        return $output;
    }
}



