## 8. Content and RAG Management

This guide explains how ChatAI selects, indexes, and manages site content for use in Retrieval-Augmented Generation (RAG), meaning it looks up relevant site content and uses those excerpts as context for an answer.

If you are new to how chatbots assemble responses or how content is supplied to a model, it is helpful to first read **The Anatomy of a Chatbot**, which provides a conceptual overview of the components involved and how they interact.

This section focuses on *what content is eligible*, *how scope and visibility are controlled*, and *how indexing is maintained*. It does not cover how excerpts are rendered or displayed.

---

### What Content Is Indexed

ChatAI indexes only content that is explicitly configured for RAG use.

Content eligibility is defined in the **Prompt** tab, where administrators specify which templates are considered suitable for indexing.

Within those eligible templates, the actual indexed content is defined by the configured `chatai-rag.php` rendering layer.

Only content that meets these criteria is processed by the indexer and made available for reference during response generation.

This allows site owners to ensure that:

- only curated, intentional content is used,
- internal or administrative pages are excluded,
- and site developers can prevent specific fields from entering the vector database by curating the configured `chatai-rag.php` view.

---

### The `chatai-rag.php` View

The configured `chatai-rag.php` view is the rendering layer ChatAI uses for indexing.

It does not exist to render a frontend page for visitors. Its purpose is to produce a controlled, text-first version of page content for vector indexing.

The runtime path for this file is configured in **Modules > ChatAI** under:

- **Path to indexing view file**

By default, ChatAI uses:

- `/site/modules/ChatAI/classes/RAG/chatai-rag.php`

Developers may copy that file elsewhere, point the config at the copy, and use it as the source of truth for indexed content.

This is the correct place to:

- decide which field content is safe to index,
- skip fields that should not enter the vector database,
- and add template-specific exclusions where needed.

---

### What the Default `chatai-rag.php` Renders

The default `chatai-rag.php` view is designed to handle a broad set of commonly used ProcessWire field types, with an emphasis on text-first content.

A typical default output includes:

- prioritised title or headline text,
- selected body content extracted from eligible fields,
- optional descriptive metadata such as image captions,
- and minimal markup suitable for later conversion to plain text.

In addition to the main indexed text, ChatAI also stores a separate headings structure for each page/language:

- `h1`
- `h2[]`
- `h3[]`

These headings are extracted from the rendered page output and stored alongside the text chunks. They are used as an additional relevance signal when ranking matches.

The intent is to provide enough context to support an answer, not to reproduce the original page.

#### Text-Based Fields

Standard text fields are rendered using ProcessWire’s normal rendering logic, including:

- Text
- Textarea
- CKEditor / TinyMCE (RTE)

Rendered markup is later normalised to plain text by the indexer.

#### Title and Headline Fields

If present, the following fields are prioritised and rendered first:

- `title`
- `headline`

This ensures that primary page context is indexed before supporting content.

#### Repeater and RepeaterMatrix

Repeater and RepeaterMatrix fields are supported.

For each repeater item:

- subfields are iterated in template order,
- formatted output via `render()` is preferred where available,
- raw values are used as a fallback if rendering fails.

This allows structured content blocks to contribute meaningful text without relying on layout-specific markup.

#### Page Reference Fields

Page reference fields are handled in a simplified, text-oriented way.

When encountered:

- referenced pages are iterated,
- page titles are extracted,
- complex relationships are intentionally reduced to readable labels.

This avoids unintentionally pulling full page bodies into the index.

#### Image and File Fields

Image and file fields are supported in a descriptive context only.

Where available, the following metadata is extracted:

- image descriptions
- image notes
- file descriptions
- file notes

Binary content itself is not indexed.

#### Generic Fallback Rendering

Any field not explicitly handled falls back to generic rendering:

- `render()` is used where possible,
- raw values are used as a final fallback.

This allows most custom or less common field types to contribute text without breaking the indexing process.

### Design Intent

The default renderer is intentionally conservative.

It is designed to:

- extract meaningful text,
- minimise layout noise,
- and behave predictably across a wide range of sites.

### Customisation and Extension

Sites with complex layouts or specialised field types may require additional handling.

Developers can adapt indexing output by:

- modifying the configured `chatai-rag.php` view,
- refining content scope in `chatai-rag.php`,
- or introducing site-specific logic via hooks.

For most sites, improving excerpt quality is best achieved by adjusting content selection and indexing rules rather than changing this template.

---

### Controlling Scope and Visibility

Content scope is controlled through a combination of:

- included and excluded templates,
- the curated content rendered by the configured `chatai-rag.php` view,
- and ProcessWire access control rules.

ChatAI respects page visibility and access permissions when indexing and retrieving content.  
Content that is not viewable to a given user is not made available to the chatbot for that user.

This ensures that:

- public users only receive references to public content,
- authenticated users may benefit from additional content where appropriate,
- and site access rules are not bypassed.

---

### Multilingual Considerations

On multilingual sites, ChatAI indexes content per language.

Language context is preserved during indexing and retrieval so that:

- questions are answered using content from the active language,
- excerpts are provided in the appropriate language,
- and mixed-language responses are avoided.

Administrators should review content guidance and indexing rules for each language as needed.

---

### Reindexing and Performance

Administrators control when the RAG index is updated.

Indexing occurs when triggered via the ChatAI Dashboard or during configured update operations.

Reindexing may be required when:

- content is added, removed, or significantly changed,
- templates or content selection rules are adjusted,
- or language-specific content is updated.

Reindexing is performed in controlled batches and can be tuned to suit site size and hosting environment.  
Adjusting batch size and scope helps balance performance and completeness, especially on larger sites.

---

### Optimising RAG Results

When RAG output does not behave as expected, common causes include:

- overly broad template inclusion,
- overly broad or uncurated `chatai-rag.php` output,
- indexing highly structured or non-textual fields,
- or expecting RAG to replace site navigation or search.

In most cases, improving results involves:

- tightening content scope,
- refining `chatai-rag.php`,
- and reindexing curated content.

Changes to excerpt rendering are rarely the first step.
