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
        $fieldset->label($m->_('Phrase relevance vs noise'));


        // Custom terms
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'custom_terms');
        $f->label($m->_('Custom terms'));
        $f->notes("Phrases relevant to site content.\nOne phrase per line.\nMay be weighted, eg term|2.0");
        $value = $data['custom_terms'] ?? '';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Meta terms
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'meta_terms');
        $f->label($m->_('Meta terms'));
        $value = $data['meta_terms'] ?? 'api, api key, aws, billing, bug, cache, cdn, cloudflare, console, cookie, crash, css, ddev, error, gcp, git, github, html, javascript, latency, login, logout, mail, mailpit, model, module, php, pricing, processwire, prompt, rate limit, refresh, reload, selector, session, smtp, stack trace, stripe, subscription, template, timeout, token, upgrade, version, webhook';
        $f->notes("IT related terms\nComma separated.");
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Hard stop words
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'stop_terms_hard');
        $f->label($m->_('Hard stop noise words'));
        $f->notes("Phrases the bot should ignore\nComma separated.");
        $value = $data['stop_terms_hard_'] ?? 'the, a, an, of, for, to, in, on, at, by, with, from, and, or, but,about, info, information, details, stuff, things, something, anything,hi, hello, hey, please, thanks, thank you, cheers, ok, okay,etc, misc, n/a, tba, tbc';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Soft stop words
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'stop_terms_soft');
        $f->label($m->_('Soft stop noise words'));
        $f->notes("Phrases the bot should give less weight but not drop\nComma separated.");
        $value = $data['stop_terms_soft'] ?? 'services, products, solutions, resources, articles, blog, page, section, today, now, latest, recent, get, have, make, do, provide, offer, use, help, support, can, could, would';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Small talk
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'smalltalk_triggers');
        $f->label($m->_('Small talk'));
        $f->notes("Irrelevant chit chat\nComma separated.");
        $value = $data['smalltalk_triggers'] ?? 'hi, hello, hey, ok, thanks, bonjour, salut';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Question words
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'question_words');
        $f->label($m->_('Questions'));
        $f->notes("Comma separated.");
        $value = $data['question_words'] ?? 'what, when, where, who, why, how, which';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Action verbs
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'action_verbs');
        $f->label($m->_('Action verbs'));
        $f->notes("Comma separated.");
        $value = $data['action_verbs'] ?? 'switch, set, change, enable, disable, remember, forget, translate, summarize, rewrite, draft, compose, make, do, show, list, add,remove';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Followup terms
        $f = $m->get('InputfieldTextarea');
        $f->attr('name+id', 'followup_terms');
        $f->label($m->_('Followup terms'));
        $f->notes("Comma separated.");
        $value = $data['followup_terms'] ?? 'this, that, those, the second one, more, details, expand, …, ...';
        $f->attr('value', $value);
        $f->stripTags = true;
        $f->useLanguages = true;
        $f->columnWidth(50);
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
