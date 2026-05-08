<?php namespace ProcessWire;
/**
 * View used ONLY for RAG indexing.
 *
 * Default site path: /site/modules/ChatAI/classes/RAG/chatai-rag.php
 * Rendered by RAG.php
 * Output whatever content you want the model to see – keep it text‑first.
 */


// Optional: simple helper to print a block safely
$printBlock = function($html) {
    if(!is_string($html) || $html === '') return;
    echo "<div>" . $html . "</div>\n"; // RAG.php will convert markup → plain text
};

// 1) Prioritise key headline fields if present
foreach (["title", "headline"] as $key) {
    if ($page->template->hasField($key)) {
        $val = $page->get($key);
        if($val) $printBlock((string) $val);
    }
}

// 2) Then render everything else in a generic way (skip admin/meta fields)
$skip = [
    'name','sort','created','modified','status','id',
    'delete','publish',
    // add any others you never want in RAG context
];

$isSkippedField = function($name) use (&$skip) {
    $name = (string) $name;
    if (in_array($name, $skip, true)) return true;
    if (preg_match('/^(loaded|publish|delete)_repeater\d+$/', $name)) return true;
    return false;
};

/*
Template-specific skip example:

$skipByTemplate = [
    'basic-page' => ['internal_notes'],
    'product' => ['spec_sheet_private', 'supplier_notes'],
];

foreach($skipByTemplate[$page->template->name] ?? [] as $fieldName) {
    $skip[] = $fieldName;
}

Use this when the same field is safe on one template but should not be indexed on
another. Site-specific curation belongs in the configured RAG view file.
*/

// Collect fields in template order, keeping title/headline first
$renderQueue = [];



foreach ($page->fields as $field) {
    /** @var Field $field */
    $n = $field->name;
    if ($isSkippedField($n)) continue;
    if (in_array($n, ['title','headline'], true)) continue; // already rendered
    $renderQueue[] = $field;
}

// 3) Generic renderer that understands common complex fieldtypes
$renderField = function(Page $p, Field $f) use (&$renderField, $printBlock, $isSkippedField) {
    $name = $f->name;
    if ($isSkippedField($name)) return;

    $type = $f->type; // Fieldtype instance
    $class = $type->className();
    $val  = $p->get($name);
    if(!$val) return;

    // FieldsetPage stores a Page-like value. Render its stored fields, not its admin/input markup.
    if ($class === 'FieldtypeFieldsetPage' && $val instanceof Page) {
        foreach ($val->fields as $sf) {
            $renderField($val, $sf);
        }
        return;
    }

    // Repeater / RepeaterMatrix
    if ($class === 'FieldtypeRepeater' || ($class === 'FieldtypeRepeaterMatrix')) {
        foreach ($val as $item) { // each repeater item is a Page-like object
            foreach ($item->fields as $sf) {
                /** @var Field $sf */
                $renderField($item, $sf);
            }
        }
        return;
    }

    // Page Reference (render titles)
    if ($class === 'FieldtypePage') {
        if(is_object($val) && method_exists($val, 'className') && $val->className() === 'PageArray') {
            $items = $val;
        } else {
            $items = new PageArray();
            $items->add($val);
        }
        foreach ($items as $it) {
            if($it->className() === 'Page') {
                $printBlock($it->get('title') ?: $it->name);
            }
        }
        return;
    }

    // Options: include human labels, never stored numeric IDs.
    if ($class === 'FieldtypeOptions') {
        $items = is_iterable($val) ? $val : [$val];
        foreach ($items as $option) {
            $label = "";
            if(is_object($option)) {
                $label = trim((string) ($option->title ?? $option->value ?? ""));
            } elseif(is_scalar($option)) {
                $label = trim((string) $option);
            }
            if($label !== "" && !is_numeric($label)) {
                $printBlock($label);
            }
        }
        return;
    }

    // Images / Files (include captions/descriptions if present)
    if ($class === 'FieldtypeImage' || $class == 'FieldtypeFile') {
        $files = is_object($val) && method_exists($val, 'className') && $val->className()  === 'Pagefiles' ? $val : null;
        if($files) foreach ($files as $fitem) {
            $desc = $sanitizer->text($fitem->description ?? '');
            if($desc !== '') $printBlock($desc);
            $notes = $sanitizer->text($fitem->notes ?? '');
            if($notes !== '') $printBlock($notes);
        }
        return;
    }

    // Default: use stored values only. Broad Page::render($field) can expose admin labels/notes.
    if (is_scalar($val)) {
        $value = trim((string) $val);
        if($value === '') return;
        if(is_bool($val)) return;
        if(is_numeric($value) && !preg_match('/Fieldtype(Text|Textarea|URL|Email)/', $class)) return;
        $printBlock($value);
        return;
    }

    if (is_object($val) && method_exists($val, '__toString')) {
        $printBlock((string) $val);
    }
};

foreach ($renderQueue as $f) {
    $renderField($page, $f);
}

// 4) Optional footer: provenance breadcrumbs
echo "<hr><small>RAG view source: {$page->httpUrl()}</small>";
