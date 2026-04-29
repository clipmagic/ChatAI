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
    /**
     * @var array|string[]
     */
    public static array $adminTemplates = [
        "admin",
        "language",
        "user",
        "permission",
        "role",
    ];

    /**
     * @param $form
     * @param $data
     * @return array
     * @throws \ProcessWire\Wire404Exception
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    public function build($form, $data): array
    {
        $m = \ProcessWire\Wire("modules");

        $inputfields = $m->get("InputfieldWrapper");
        $inputfields->addClass("WireTab");
        $inputfields->attr("name", "prompt");
        $inputfields->attr("id", "chatai-tab-prompt");
        $inputfields->attr("title", $m->_("Prompt"));

        // auto-generate or write your own
        // Yes or No
        $f = $m->get("InputfieldRadios");
        $f->attr("name+id", "autogen");
        $f->label($m->_("Auto-generate Prompt?"));
        $options = [
            1 => $m->_("Yes"),
            0 => $m->_("No"),
        ];
        $f->setOptions($options);
        $f->required(1);
        $value =
            isset($data["autogen"]) && $data["autogen"] === "0" ? "0" : "1";
        $f->val($value);
        $inputfields->add($f);

        // Blacklist filters
        $fieldset = $m->get("InputfieldFieldset");
        $fieldset->attr("name+id", "filters");
        $fieldset->label($m->_("Filters"));
        $fieldset->showIf("autogen=1");

        // Use blacklist?
        $f = $m->get("InputfieldCheckbox");
        $f->attr("name+id", "use_blacklist");
        $f->label($m->_("Use blacklist"));
        $value = empty($data["use_blacklist"]) ? null : 1;
        $f->val($value);
        if ($value === 1) {
            $f->attr("checked", "checked");
        }
        $fieldset->add($f);

        // Blacklist
        $f = $m->get("InputfieldTextarea");
        $f->attr("name+id", "blacklist");
        $f->label($m->_("Blacklisted terms"));
        $f->showIf("use_blacklist=1");
        $value =
            $data["blacklist"] ??
            $m->_(
                "always respond,end every response,from now on,forget all previous,ignore previous instructions,your name is,call yourself,you are now,act as,system prompt, developer message, reveal the prompt,answer my exam,bomb,buy drugs,cheat,cocaine,ddos,drugs,ecstasy,erotic,exploit,fetish,generate code for me,gun,hack,heroin,how to jailbreak,kill,lsd,marijuana,masturbate,meth,murder,naked,nude,onlyfans,orgasm,penis,porn,proxy,prompt injection,rape,sex,shoot,shell,solve my homework,sql injection,stab,strip,suicide,terrorist,torrent,vagina,violence,vpn,weed,who won the war,write my essay,xxx,xss",
            );
        $f->val($value);
        $f->notes($m->_("Add or remove terms as needed"));
        $f->stripTags = true;
        $fieldset->add($f);
        $inputfields->add($fieldset);

        // Initial Instructions
        $hint =
            " Your single job is to help users find relevant pages and information on this site.";
        $hint .=
            "\nWhen you find relevant content, return HTML: a short intro sentence followed by a list of up to 5 links.";
        $hint .=
            "\nEach link must use the provided source_url and the page title as anchor text.";
        $hint .= "\nAdd a one-sentence snippet under each item.";
        $hint .=
            "\nIf nothing relevant is found, return a short text reply: make the wording match the retrieval scope";
        $hint .=
            " and ask one specific clarifying question. Offer exactly one self-check tip.";
        $hint .=
            "\nIf retrieval was limited to the current page, say that. Only refer to the whole site when the retrieval was site-wide.";
        $hint .=
            "\nNever propose writing, editing, layout, or design unless the user explicitly asks for writing help.";
        $hint .=
            "\nDo not include external links. Only link to the site’s source_url values provided by retrieval.";
        $hint .= "\nKeep answers brief.\n\n";

        $f = $m->get("InputfieldTextarea");
        $f->attr("name+id", "autohint");
        $f->label($m->_("Additional instructions"));
        $f->notes($m->_("Optional extra information"));
        $f->showIf("autogen=1");
        $value = $data["autohint"] ?? $m->_($hint);
        $f->val($value);
        $f->stripTags = true;

        $inputfields->add($f);

        $fieldset = $m->get("InputfieldFieldset");
        $fieldset->attr("name+id", "content");
        $fieldset->label($m->_("Content guidance"));
        $fieldset->showIf("autogen=1");

        $f = $m->get("InputfieldAsmSelect");
        $f->attr("name+id", "rag_include_templates");
        $f->label($m->_("Include templates"));
        $f->notes("Leave blank to include all");
        $f->class = "uk-select";
        $f->options = $this->getTemplateOptions();
        $f->stripTags = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        $f = $m->get("InputfieldAsmSelect");
        $f->attr("name+id", "rag_exclude_templates");
        $f->label($m->_("Exclude templates"));
        $f->notes("Leave blank to exclude none");
        $f->class = "uk-select";
        $f->options = $this->getTemplateOptions();
        $f->stripTags = true;
        $f->columnWidth(50);
        $fieldset->add($f);

        // Allowed HTML tags
        $f = $m->get("InputfieldText");
        $f->attr("name+id", "allowed_tags");
        $f->label($m->_("Allowed HTML tags"));
        $value =
            $data["allowed_tags"] ?? "p,ul,li,ol,strong,em,code,br,a[href]";
        $f->val($value);
        $f->stripTags = true;
        $f->columnWidth(60);
        $f->notes = $m->_(
            "Comma separated list of HTML tags allowed in the Chatbot reply, eg: p,ul,li,ol,strong,em,code,br,a[href]",
        );
        $fieldset->add($f);

        // Max number of page links
        $f = $m->get("InputfieldInteger");
        $f->attr("name+id", "context_limit");
        $f->label($m->_("Max number of page links"));
        $f->notes("");
        $value = $data["context_limit"] ?? 12;
        $f->val($value);
        $f->attr("type", "number");
        $f->stripTags = true;
        $f->columnWidth(20);
        $fieldset->add($f);

        // Approx number of words
        $f = $m->get("InputfieldInteger");
        $f->attr("name+id", "context_snippet_len");
        $f->label($m->_("Response approx words"));
        $f->notes("");
        $value = $data["context_snippet_len"] ?? 400;
        $f->val($value);
        $f->attr("type", "number");
        $f->stripTags = true;
        $f->columnWidth(20);
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Prompt preview
        $markUp = $m->get("InputfieldMarkup");
        $markUp->attr("name+id", "prompt_preview");
        $markUp->label($m->_("Prompt preview"));
        $promptService = $m->get("ChatAIPromptService");
        $markUp->attr("value", $promptService->getPrompt());
        $markUp->showIf("autogen=1");
        $markUp->textformatters = [
            "TextformatterEntities",
            "TextformatterNewlineBR",
        ];

        $inputfields->add($markUp);

        // Custom system prompt
        $f = $m->get("InputfieldTextarea");
        $f->attr("name+id", "custom_prompt");
        $f->label($m->_("Write your own prompt"));
        $f->description($m->_("Overrides all safety rails. Use with care!"));
        $f->notes($m->_("Give your bot guidance on how it should respond."));
        $f->showIf("autogen=0");
        $value = !empty($data["custom_prompt"])
            ? $data["custom_prompt"]
            : "Write your own prompt";
        $f->val($value);
        $f->required(1);
        $f->requiredIf("autogen=0");
        $f->stripTags = true;

        $inputfields->add($f);

        $form->add($inputfields);

        $output = [];
        $key = $m->_("Prompt");
        $value = $inputfields->render();
        $output[$key] = $value;
        $output["form"] = $form;

        return $output;
    }

    /**
     * @return array
     */
    public function getTemplateOptions()
    {
        $templatesOptions = [];
        $templates = \ProcessWire\Wire("templates");

        if ($templates) {
            foreach ($templates as $template) {
                if (in_array($template->name, self::$adminTemplates)) {
                    continue;
                }

                if (str_starts_with($template->name, "field-")) {
                    continue;
                }

                if (str_starts_with($template->name, "repeater_")) {
                    continue;
                }

                $label = $template->label
                    ? $template->label . " (" . $template->name . ")"
                    : $template->name;
                $templatesOptions[$template->id] = $label;
            }
        }

        return $templatesOptions;
    }
}
