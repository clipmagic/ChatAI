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

class DictionaryTab
{
    public function build($form, $data): array
    {
        $m = \ProcessWire\wire('modules');
        $user = \ProcessWire\wire('user');
        $userLang = $user->language;

        $inputfields = $m->get('InputfieldWrapper');
        $inputfields->addClass('WireTab');
        $inputfields->attr('name', 'dictionary');

        $TabId = $userLang->_('Dictionary');
        $inputfields->attr('id', $TabId);

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'relevance');
        $fieldset->label($m->_('Phrase relevance'));


        // Phrases used to reference active front end page
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'current_page_refs');
        $f->label($m->_('Current page reference'));
        $f->notes($m->_("Phrases that identify the current page. Comma-separated."));
        $value = $data['current_page_refs'] ?? "this page,current page,on this page,this article,this post,here";
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Custom terms
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'custom_terms');
        $f->label($m->_('Custom terms'));
        $f->placeholder($m->_("pricing\nbook online\nterm|2.0"));
        $f->notes($m->_("Phrases relevant to site content.\nOne phrase per line.\nMay be weighted, eg term|2.0"));
        $value = $data['custom_terms'] ?? '';
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Hard stop words
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'stop_terms_hard');
        $f->label($m->_('Hard stop noise words'));
        $f->notes($m->_("Phrases the bot should ignore\nComma separated."));
        $value = $data['stop_terms_hard'] ?? $m->_('the, a, an, of, for, to, in, on, at, by, with, from, and, or, but,about, info, information, details, stuff, things, something, anything,hi, hello, hey, please, thanks, thank you, cheers, ok, okay,etc, misc, n/a, tba, tbc');
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Soft stop words
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'stop_terms_soft');
        $f->label($m->_('Soft stop noise words'));
        $f->notes($m->_("Phrases the bot should give less weight but not drop\nComma separated."));
        $value = $data['stop_terms_soft'] ?? $m->_('services, products, solutions, resources, articles, blog, page, section, today, now, latest, recent, get, have, make, do, provide, offer, use, help, support, can, could, would');
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'smalltalk');
        $fieldset->label($m->_('Smalltalk'));

        // Small talk
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'smalltalk_triggers');
        $f->label($m->_('Triggers'));
        $f->notes("Irrelevant chit chat\nComma separated.");
        $value = $data['smalltalk_triggers'] ?? $m->_('hi, hello, hey, ok, thanks, salut');
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'smalltalk_reply');
        $f->label($m->_('Reply'));
        $value = $data['smalltalk_reply'] ?? $m->_('Tell me what you’d like to learn about and I’ll find the most relevant pages.');
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $inputfields->add($fieldset);

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'meta');
        $fieldset->label($m->_('Meta'));

        // Meta terms
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'meta_terms');
        $f->label($m->_('Triggers'));
        $value = $data['meta_terms'] ?? 'api, api key, aws, billing, bug, cache, cdn, cloudflare, console, cookie, crash, css, ddev, error, gcp, git, github, html, javascript, latency, login, logout, mail, mailpit, model, module, php, pricing, processwire, prompt, rate limit, refresh, reload, selector, session, smtp, stack trace, stripe, subscription, template, timeout, token, upgrade, version, webhook';
        $f->notes($m->_("IT related terms\nComma separated."));
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'meta_reply');
        $f->label($m->_('Reply'));
        $value = $data['meta_reply'] ?? $m->_("I can help with pages and information on this site. Ask me what you’re looking for.");
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);


        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'actions');
        $fieldset->label($m->_('Actions'));


        // Action verbs
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'action_verbs');
        $f->label($m->_('Triggers'));
        $f->notes("Comma separated.");
        $value = $data['action_verbs'] ?? $m->_('switch, set, change, enable, disable, remember, forget, translate, summarize, rewrite, draft, compose, make, do, show, list, add,remove');
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'action_reply');
        $f->label($m->_('Reply'));
        $value = $data['action_reply'] ?? $m->_("Sure. Tell me what you'd like me to do with the site content.");
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $inputfields->add($fieldset);


        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'followups');
        $fieldset->label($m->_('Follow up'));

        // Followup terms
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'followup_terms');
        $f->label($m->_('Triggers'));
        $f->notes("Comma separated.");
        $value = $data['followup_terms'] ?? $m->_('this, that, those, the second one, more, details, expand, …, ...');
        $f->val( $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'followup_reply');
        $f->label($m->_('Reply'));
        $value = $data['followup_reply'] ?? $m->_("What information should I expand on?");
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $inputfields->add($fieldset);

        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'questions');
        $fieldset->label($m->_('Questions'));

        // Question words
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'question_words');
        $f->label($m->_('Triggers'));
        $value = $data['question_words'] ?? $m->_('what, when, where, who, why, how, which');
        $f->val($value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(100);
        $fieldset->add($f);

        $inputfields->add($fieldset);


        $fieldset = $m->get('InputfieldFieldset');
        $fieldset->attr('name+id', 'ambiguous');
        $fieldset->label($m->_('Ambiguous or no context'));

        $f = $m->get('InputfieldText');
        $f->attr('name+id', 'no_context_reply');
        $f->label($m->_('Reply'));
        $value = $data['no_context_reply'] ?? $m->_("Tell me what you’re looking for and I’ll point you in the right direction.");
        $f->val( $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(100);
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
