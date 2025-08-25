<?php namespace ChatAI;

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

//use ProcessWire\Process;
use ProcessWire;
use ProcessWire\Wire;

class DashboardTab
{
    public function build($form, $data): array {

        $m = \ProcessWire\Wire('modules');
        $languages = \ProcessWire\Wire('languages');
        $sanitizer = \ProcessWire\Wire('sanitizer');


  ///      $sanitizer = $process->wire('sanitizer');
        $tabName = $languages->_('Dashboard');

//        $m = $process->modules;
        $inputfields = $m->get('InputfieldWrapper');
        $inputfields->addClass('WireTab');

        $inputfields->attr('id', $sanitizer->fieldName($tabName) );


        $id =  $sanitizer->fieldName($tabName);
        $inputfields->attr('id', $id);
        $label = $tabName;


        // TODO add dashboard components
        $content = "<p>Dashboard components here</p>";

        $markUp = $m->get('InputfieldMarkup');
        $markUp->attr('value', $content);

        $inputfields->add($markUp);

        $form->add($inputfields);

        $output = [];
        $key = $languages->_('Dashboard');
        $value = $inputfields->render();
        $output[$key] = $value;
        $output['form'] = $form;

        return $output;
    }
}



