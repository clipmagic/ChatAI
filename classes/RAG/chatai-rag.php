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
        if($val) $printBlock($page->render($key));
    }
}

// 2) Then render everything else in a generic way (skip admin/meta fields)
$skip = [
    'name','sort','created','modified','status','id',
    // add any others you never want in RAG context
];

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
    if (in_array($n, $skip, true)) continue;
    if (in_array($n, ['title','headline'], true)) continue; // already rendered
    $renderQueue[] = $field;
}

// 3) Generic renderer that understands common complex fieldtypes
$renderField = function(Page $p, Field $f) use ($printBlock) {
    $name = $f->name;
    $type = $f->type; // Fieldtype instance
    $class = $type->className();
    $val  = $p->get($name);
    if(!$val) return;

    // Repeater / RepeaterMatrix
    if ($class === 'FieldtypeRepeater' || ($class === 'FieldtypeRepeaterMatrix')) {
        foreach ($val as $item) { // each repeater item is a Page-like object
            foreach ($item->fields as $sf) {
                /** @var Field $sf */
                $subName = $sf->name;
                $subVal  = $item->get($subName);
                if(!$subVal) continue;
                // Prefer render() for formatting if available
                try {
                    $printBlock($item->render($subName));
                } catch (\Throwable $e) {
                    $printBlock((string)$subVal);
                }
            }
        }
        return;
    }

    // Page Reference (render titles)
    if ($class === 'FieldtypePage') {
        if($val->className() === 'PageArray') {
            $items = $val;
        } else {
            $pageArrary = new PageArray();
            $pageArrary->add($val);
        }
        foreach ($items as $it) {
            if($it->className() === 'Page') {
                $printBlock($it->get('title') ?: $it->name);
            }
        }
        return;
    }

    // Images / Files (include captions/descriptions if present)
    if ($class === 'FieldtypeImage' || $class == 'FieldtypeFile') {
        $files = $val->className()  === 'Pagefiles' ? $val : null;
        if($files) foreach ($files as $fitem) {
            $desc = $sanitizer->text($fitem->description ?? '');
            if($desc !== '') $printBlock($desc);
            $notes = $sanitizer->text($fitem->notes ?? '');
            if($notes !== '') $printBlock($notes);
        }
        return;
    }

    // Default: let PW render the field (handles textarea/RTE, etc.)
    try {
        $printBlock($p->render($name));
    } catch (\Throwable $e) {
        $printBlock((string)$val);
    }
};

foreach ($renderQueue as $f) {
    $renderField($page, $f);
}

// 4) Optional footer: provenance breadcrumbs
echo "<hr><small>RAG view source: {$page->httpUrl()}</small>";
