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

        // Quick replies
        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->label($m->_('Quick answers'));
        $fieldset->attr('name+id', 'quick_answers');

        // Small-talk reply
        $f = $m->get('InputfieldTextarea');
        $f->attr('name', 'smalltalk_reply');
        $f->label($m->_('Small-talk reply'));
        $f->useLanguages = true;
//        $f = $this->formRenderHelper($f, 'Hello. How can I help?', $languages, $this['values']);
        $f->attr('value', 'Hello. How can I help?');
        $fieldset->add($f);

        // No-context reply
        $f = $m->get('InputfieldTextarea');
        $f->attr('name', 'no_context_reply');
        $f->label($m->_('No-context reply'));
        $f->useLanguages = true;
        $f->attr('value', 'I can help with pages and information on this site. Tell me what you are looking for and I will point you to the right page.');
//        $f = $this->formRenderHelper($f, 'I can help with pages and information on this site. Tell me what you are looking for and I will point you to the right page.', $languages, $this['values']);
        $fieldset->add($f);

        // Small-talk triggers (one per line)
        $f = $m->get('InputfieldTextarea');
        $f->attr('name', 'smalltalk_triggers');
        $f->label($m->_('Small-talk triggers (one per line)'));
        $f->useLanguages = true;
//        $f = $this->formRenderHelper($f, "hi\nhello\nhey\nok\nthanks\nbonjour\nsalut", $languages, $this['values']);
        $f->attr('value', "hi\nhello\nhey\nok\nthanks\nbonjour\nsalut");
        $fieldset->add($f);
        $inputfields->add($fieldset);

        $form->add($inputfields);

        $output = [];
        $key = $TabId;
        $value = $inputfields->render();
        $output[$key] = $value;
        $output['form'] = $form;

        return $output;
    }
}



