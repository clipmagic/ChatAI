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

use ProcessWire\InputfieldWrapper;
use ProcessWire\Process;
use ProcessWire\Wire;

class PromptTab
{
    public function build($form, $data): array
    {
        $m = \ProcessWire\Wire('modules');

        $inputfields = $m->get('InputfieldWrapper');
        $inputfields->addClass('WireTab');
        $inputfields->attr('name', 'prompt');
        $inputfields->attr('id', 'Prompt');

        // auto-generate or write your own
        // Yes or No
        $f = $m->get('InputfieldRadios');
        $f->attr('name+id', 'autogen');
        $f->label($m->_('Auto-generate Prompt?'));
        $options = [
            1 => $m->_('Yes'),
            0 => $m->_('No'),

        ];
        $f->setOptions($options);
        $f->required(1);
        $value = isset($data['autogen']) && $data['autogen'] === '0' ? '0'  : '1';
        $f->attr('value', $value);
        $inputfields->add($f);


        // Blacklist filters
        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'filters');
        $fieldset->label($m->_('Filters'));
        $fieldset->showIf('autogen=1');

        // Use blacklist?
        $f = $m->get('InputfieldCheckbox');
        $f->attr('name+id', 'use_blacklist');
        $f->label( $m->_('Use blacklist'));
        $value = empty($data['use_blacklist']) ? null : 1;
        $f->attr('value', $value);
        if($value === 1)
            $f->attr('checked', 'checked');
        $fieldset->add($f);

        // Blacklist
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id','blacklist');
        $f->label($m->_('Blacklisted terms'));



        $f->showIf('use_blacklist=1');
        $value = $data['blacklist'] ?? "answer my exam,bomb,buy drugs,cheat,cocaine,ddos,drugs,ecstasy,erotic,exploit,fetish,generate code for me,gun,hack,hang,heroin,how to jailbreak,kill,lsd,marijuana,masturbate,meth,murder,naked,nude,onlyfans,orgasm,penis,porn,proxy,prompt injection,rape,sex,shoot,shell,solve my homework,sql injection,stab,strip,suicide,terrorist,torrent,vagina,violence,vpn,weed,who won the war,write my essay,xxx,xss";
        $f->attr('value', $value);
        $f->notes($m->_('Add or remove terms as needed'));
        $f->stripTags = true;
        $fieldset->add($f);
        $inputfields->add($fieldset);

        // Extra Instructions
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'autohint');
        $f->label($m->_('Additional instructions'));
        $f->notes($m->_("Optional extra information"));
        $f->showIf('autogen=1');
        $value = $data['autohint'] ?? '';
        $f->attr('value', $value);
        $f->stripTags = true;

        $inputfields->add($f);

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'content');
        $fieldset->label($m->_('Content guidance'));
        $fieldset->showIf('autogen=1');

        // Search fields
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'context_fields');
        $f->label($m->_('Search fields'));
        $f->notes("Content fields to search in ProcessWire Selector format.\n'title|headline|' automatically prepended to the selector");
        $value = $data['context_fields'] ?? 'summary|body';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->columnWidth(30);
        $fieldset->add($f);

        // Search templates
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'context_templates');
        $f->label($m->_('Search templates'));
        $f->notes("Limit search by template in ProcessWire Selecter format");
        $value = $data['context_templates'] ?? "home|basic-page";
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->columnWidth(30);
        $fieldset->add($f);

        // Max number of page links
        $f = $m->get('InputfieldInteger');
        $f->attr('name+id', 'context_limit');
        $f->label($m->_('Max number of page links'));
        $f->notes("");
        $value = $data['context_limit'] ?? 12;
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->columnWidth(20);
        $fieldset->add($f);

        // Approx number of words
        $f = $m->get('InputfieldInteger');
        $f->attr('name+id', 'context_snippet_len');
        $f->label($m->_('Response approx words'));
        $f->notes("");
        $value = $data['context_snippet_len'] ?? 400;
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->columnWidth(20);
        $fieldset->add($f);

        $inputfields->add($fieldset);


        // Prompt preview
        $markUp = $m->get('InputfieldMarkup');
        $markUp->attr('name+id', 'prompt_preview');
        $markUp->label($m->_('Prompt preview'));
        $markUp->attr('value', '');
        $markUp->showIf('autogen=1');
        $markUp->textformatters = ['TextformatterEntities', 'TextformatterNewlineBR'];

        $inputfields->add($markUp);

        // Custom system prompt
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'custom_prompt');
        $f->label($m->_('Write your own prompt'));
        $f->description($m->_('Overrides all safety rails. Use with care!'));
        $f->notes($m->_("Give your bot guidance on how it should respond."));
        $f->showIf('autogen=0');
        $value = !empty($data['custom_prompt']) ? $data['custom_prompt'] : 'Write your own prompt';
        $f->attr('value', $value);
        $f->required(1);
        $f->requiredIf('autogen=0');
        $f->stripTags = true;

        $inputfields->add($f);

        $form->add($inputfields);


        $output = [];
        $key = $m->_('Prompt');
        $value = $inputfields->render();
        $output[$key] = $value;
        $output['form'] = $form;

        return $output;
    }
}