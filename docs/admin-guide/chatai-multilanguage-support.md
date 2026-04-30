## 9. ChatAI Multilanguage Support

This guide explains how ChatAI behaves on multilingual ProcessWire sites and what administrators should be aware of when configuring ChatAI for multiple languages.

It focuses on **how language affects indexing, retrieval, and responses**, not on general multilingual site setup.

If you are new to how ChatAI assembles responses from site content, it may be helpful to first read **How ChatAI Works**, which provides a conceptual overview.

---

## How ChatAI Handles Language

ChatAI operates in the context of the **active site language**.

When a user interacts with the chatbot:
- the current ProcessWire language is detected,
- prompts are assembled in that language,
- and only content indexed for that language is considered.

ChatAI does not translate content automatically.  
It works with the language variants already defined in your site.

---

## Multilingual Content Indexing

On multilingual sites, ChatAI builds and maintains **separate RAG indexes per language**.

This means:
- each language version of a page is indexed independently,
- language-specific fields are respected,
- and content is retrieved only from the active language.

If a page exists in multiple languages, each version is indexed using the content available for that language.

If a page does not have content in a given language, it will not meaningfully contribute to the index for that language.

If a page is disabled in a given language, that language version is not indexed.

---

## Prompt and Interface Text per Language

Most user-facing ChatAI fields support multilingual values, including:
- welcome message,
- chatbot name,
- tone and role descriptions,
- placeholder and button text,
- disclaimer and footer content.

Administrators should review these fields in each language to ensure:
- messaging is clear and appropriate,
- tone remains consistent across languages,
- and no fallback text appears unintentionally.

---

## Dictionary and Language Context

Dictionary terms (such as relevance phrases, noise words, and triggers) are evaluated **within the current language context**.

This means:
- dictionary entries should be language-specific,
- phrases meaningful in one language should not be assumed to work in another,
- and multilingual sites should define dictionary terms per language where appropriate.

Reusing dictionary terms across languages is rarely effective unless the language itself is shared.

---

## Current Page Context and Language

When **Current Page Reference** is enabled in the Dictionary tab, ChatAI considers the page the user is currently viewing **in the active language**.

This helps resolve vague questions such as:
- “What is this about?”
- “Tell me more about this page”

Language context is preserved, so relevance is evaluated against the correct language version of the page.

---

## Reindexing After Language Changes

Reindexing is required when:
- new language content is added,
- existing translations are updated,
- templates affecting multilingual content change,
- or the configured `chatai-rag.php` output changes.

Reindexing runs page by page. On multilingual sites, each selected page is indexed in every enabled language it has.

Batch size and start ID controls can be used to manage load by limiting how many pages are processed in one run.

---

## Common Multilanguage Pitfalls

When ChatAI behaves unexpectedly on multilingual sites, common causes include:

- missing or incomplete translations,
- dictionary terms defined only in one language,
- overly broad configured `chatai-rag.php` output that includes duplicated layout text across languages,
- or assuming fallback language content will be used automatically.

In most cases, improvements come from:
- reviewing language-specific content coverage,
- refining `chatai-rag.php`,
- and ensuring prompts and dictionary entries exist for each language.

---

## Summary

ChatAI fully supports multilingual ProcessWire sites by:
- respecting the active language,
- indexing content per language,
- and keeping responses language-consistent.

Good multilingual behaviour depends less on automation and more on **intentional configuration per language**.

Administrators are encouraged to review ChatAI settings whenever new languages are added or content structure changes.
