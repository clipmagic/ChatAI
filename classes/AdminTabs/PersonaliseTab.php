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

        $m = \ProcessWire\wire('modules');
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
        $f->val($value);
        $f->stripTags = true;
        $fieldset->add($f);

        // Intro
        $f = $m->get('InputfieldText');
        $f->attr('name', 'botintro');
        $f->label($m->_('Welcome message'));
        $f->columnWidth(60);
        $f->useLanguages = true;
        $value = $data['botintro'] ?? $m->_("Hello! My name is {botname}. I can help you find information and pages on this site. What topic are you interested in?");
        $f->val($value);
        $f->stripTags = true;
        $fieldset->add($f);
        $inputfields->add($fieldset);

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->label($m->_('Background'));

        // Role
        $f = $m->get('InputfieldText');
        $f->attr('name', 'botrole');
        $f->label($m->_('Role'));
        $value = $data['botrole'] ??  $m->_('a friendly and helpful site guide.');
        $f->val($value);

        $f->columnWidth(30);
        $f->stripTags = true;
        $fieldset->add($f);

        // Business name
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'bizname');
        $f->label($m->_('Employer'));
        $value = $data['bizname'] ?? '';
        $f->val($value);
        $f->columnWidth(20);
        $f->stripTags = true;
        $fieldset->add($f);

        // Tone
        $f = $m->get('InputfieldText');
        $f->name('tone');
        $f->label($m->_('Tone of the answers'));
        $value = $data['tone'] ?? $m->_('friendly and professional');
        $f->val($value);
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
        $value = $data['input_placeholder'] ?? $m->_("Ask a question...");
        $f->val($value);
        $f->stripTags = true;
        $fieldset->add($f);

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'thinking_text');
        $f->label($m->_('Thinking text'));
        $f->columnWidth(50);
        $f->useLanguages = true;
        $value = $data['thinking_text'] ?? $m->_("Thinking...");
        $f->val( $value);
        $f->stripTags = true;
        $fieldset->add($f);

        // Submit button text
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'submit_text');
        $f->label($m->_('Send button text'));
        $f->columnWidth(50);
        $f->useLanguages = true;
        $value = $data['submit_text'] ?? $m->_("Send");
        $f->val($value);
        $f->stripTags = true;
        $fieldset->add($f);

        // Reset button text
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'reset_text');
        $f->label($m->_('Reset button text'));
        $f->columnWidth(50);
        $f->useLanguages = true;
        $value = $data['reset_text'] ?? $m->_("Reset this chat");
        $f->val($value);
        $f->stripTags = true;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'disclaimer_text');
        $f->label($m->_('Disclaimer text'));
        $f->useLanguages = true;
        $value = $data['disclaimer_text'] ?? $m->_("This assistant uses AI and can make mistakes. Please check important information.");
        $f->val($value);
        $f->stripTags = true;
        $fieldset->add($f);

        // use an existing HTML/RTE field if available
        $f = $m->get('InputfieldTinyMCE');
        if(!$f)
            $f = $m->get('InputfieldCKEditor') ?? $m->get('InputfieldTextarea');

        $f->attr('name+id', 'footer_text');
        $f->label($m->_('Additional information'));
        $f->notes($m->_("Give your Add content and links in the widget footer."));
        $value = !empty($data['footer_text']) ?? '';
        $f->val($value);
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



