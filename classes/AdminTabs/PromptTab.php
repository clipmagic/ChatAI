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
    public static array $adminTemplates = ['admin', 'language', 'user', 'permission', 'role'];

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


        $f = $m->get('InputfieldAsmSelect');
        $f->attr('name+id', 'rag_candidate_templates');
        $f->label($m->_('Include templates'));
        $f->notes("Leave blank to include all");
        $f->class = 'uk-select';
        $f->options = $this->getTemplateOptions();
        $f->stripTags = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $f = $m->get('InputfieldAsmSelect');
        $f->attr('name+id', 'rag_exclude_templates');
        $f->label($m->_('Exclude templates'));
        $f->notes("Leave blank to exclude none");
        $f->class = 'uk-select';
        $f->options = $this->getTemplateOptions();
        $f->stripTags = true;
        $f->columnWidth(50);
        $fieldset->add($f);



        // Allowed HTML tags
        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'allowed_tags');
        $f->label($m->_("Allowed HTML tags"));
        $value = $data['allowed_tags'] ?? 'p,ul,li,ol,strong,em,code,br,a[href]';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->columnWidth(60);
        $f->notes = $m->_("Comma separated list of HTML tags allowed in the Chatbot reply, eg: p,ul,li,ol,strong,em,code,br,a[href]");
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

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'rag_selectors');
        $fieldset->label($m->_('HTML selectors'));


        // Include HTML selectors
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'rag_candidate_selectors');
        $f->label($m->_('Selectors to include'));
        $f->notes("Comma separated list of selectors with high-value content to use when building dictionary");
        $value = $data['rag_candidate_selectors'] ?? "article, [role='main'], .content, .page-content, .entry-content, .post-content, #content";
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Exclude HTML selectors
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'rag_exclude_selectors');
        $f->label($m->_('Exclude content within these selectors'));
        $f->notes("Comma separated list of selectors to exclude when building dictionary");
        $value = $data['rag_exclude_selectors'] ?? "#chatbot-toggle, #chatbot-dialog, header, footer, nav, aside, form[role='search'], [role='banner'], [role='navigation'], [role='contentinfo'], .sidebar, .breadcrumbs, .menu, .mega-menu, .toolbar, .cookie, .consent, .newsletter, .promo, .ad, .related, .share, .social, #header, #footer";
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $inputfields->add($fieldset);


//        $fieldset = $m->get('InputfieldFieldset');
//        $fieldset->attr('name+id', 'relevance');
//        $fieldset->label($m->_('Phrase relevance vs noise'));
//
//
//        // Custom terms
//        $f = $m->get('InputfieldTextarea');
//        $f->attr('name+id', 'custom_terms');
//        $f->label($m->_('Custom terms'));
//        $f->notes("Phrases relevant to site content.\nOne phrase per line.\nMay be weighted, eg term|2.0");
//        $value = $data['custom_terms'] ?? '';
//        $f->attr('value', $value);
//        $f->stripTags = true;
//        $f->useLanguages = true;
//        $f->columnWidth(50);
//        $fieldset->add($f);
//
//        // Meta terms
//        $f = $m->get('InputfieldTextarea');
//        $f->attr('name+id', 'meta_terms');
//        $f->label($m->_('Meta terms'));
//        $value = $data['meta_terms'] ?? 'api, api key, aws, billing, bug, cache, cdn, cloudflare, console, cookie, crash, css, ddev, error, gcp, git, github, html, javascript, latency, login, logout, mail, mailpit, model, module, php, pricing, procache, processwire, prompt, rate limit, refresh, reload, selector, session, smtp, stack trace, stripe, subscription, template, timeout, token, upgrade, version, webhook';
//        $f->attr('value', $value);
//        $f->stripTags = true;
//        $f->useLanguages = true;
//        $f->columnWidth(50);
//        $fieldset->add($f);
//
//        // Hard stop words
//        $f = $m->get('InputfieldTextarea');
//        $f->attr('name+id', 'stop_terms_hard');
//        $f->label($m->_('Hard stop noise words'));
//        $f->notes("Phrases the bot should ignore\nComma separated.");
//        $value = $data['stop_terms_hard_'] ?? 'the, a, an, of, for, to, in, on, at, by, with, from, and, or, but,about, info, information, details, stuff, things, something, anything,hi, hello, hey, please, thanks, thank you, cheers, ok, okay,etc, misc, n/a, tba, tbc';
//        $f->attr('value', $value);
//        $f->stripTags = true;
//        $f->useLanguages = true;
//        $f->columnWidth(50);
//        $fieldset->add($f);
//
//        // Soft stop words
//        $f = $m->get('InputfieldTextarea');
//        $f->attr('name+id', 'stop_terms_soft');
//        $f->label($m->_('Soft stop noise words'));
//        $f->notes("Phrases the bot should give less weight but not drop\nComma separated.");
//        $value = $data['stop_terms_soft'] ?? 'services, products, solutions, resources, articles, blog, page, section, today, now, latest, recent, get, have, make, do, provide, offer, use, help, support, can, could, would';
//        $f->attr('value', $value);
//        $f->stripTags = true;
//        $f->useLanguages = true;
//        $f->columnWidth(50);
//        $fieldset->add($f);
//
//
//        $inputfields->add($fieldset);
//
//

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

    public function getTemplateOptions() {
        $templatesOptions = [];
        $templates = \ProcessWire\Wire('templates');

        if ($templates) {
            foreach ($templates as $template) {
                if (in_array($template->name, self::$adminTemplates)) {
                    continue;
                }

                if (str_starts_with($template->name, 'field-')) {
                    continue;
                }

                if (str_starts_with($template->name, 'repeater_')) {
                    continue;
                }

                $label = $template->label ? $template->label.' ('.$template->name.')' : $template->name;
                $templatesOptions[$template->id] = $label;
            }
        }

        return $templatesOptions;
    }

}