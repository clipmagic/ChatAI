## 7. Frontend Integration

ChatAI ships with a default frontend widget (PHP), JavaScript, and CSS.

This chapter explains how the widget is structured, which parts can be customised safely, and which attributes must remain stable because they are used by the JavaScript.

**The Anatomy of a Chatbot** provides a concise overview of how chatbot components interact and how site content is supplied to a model.

---

### 7.1 Recommended Approach for Customising Frontend Assets

Best practice is to copy the default widget, JavaScript, and CSS files into your preferred site location and reference those copies via the module path settings.

This avoids editing module files directly, which may be overwritten during upgrades.

Example target location for the widget:

- `/site/templates/chatai-widget.php`

---

### 7.2 Widget Template Structure

The default widget template contains the following major parts:

- **Toggle button** to open the chatbot dialog
- **Dialog** containing:
    - header and close button
    - welcome message and message stream
    - optional CTA container
    - input form and submit button
    - status (“Thinking…”) region
    - reset button container
    - footer disclaimer and optional footer content

---

### 7.3 Required Markup Contract (IDs and Class Names)

ChatAI’s frontend JavaScript relies on a set of fixed IDs and class names.  
When customising the widget template, keep these identifiers unchanged.

You may add additional wrappers, classes, or data attributes as needed.

#### Required IDs

- `chatbot-toggle`
- `chatbot-dialog`
- `chatbot-form`
- `chatbot-input`
- `chatbot-status`
- `chatbot-messages`
- `chatbot-cta`

#### Required Class Names

- `.chatbot-submit`
- `.chatbot-clear`
- `.chatai-reset`
- `.chatbot-msg-wrapper`
- `.chatbot-form`
- `.chatbot-status-visible`
- `.chatbot-status-sr`

#### Classes Applied by ChatAI

ChatAI applies the following classes at runtime. Your CSS may depend on them:

- `.chatbot-msg` plus a role class (for example `bot` or `user`)
- `.chatbot-name`
- `.chatbot-warning`
- `.chatbot-cutoff`

---

### 7.4 Customisable Markup and Styling

The following parts are safe to change and are intended to be customised:

- Visual layout and wrappers (div structure)
- SVG icons
- Heading and branding presentation
- Footer layout and content areas
- Additional helper text and UI embellishments

You may also add new elements and classes for styling purposes.

When changing markup, preserve the required IDs listed above and ensure the input form still functions as a standard HTML form with a submit button.

---

### 7.5 Server-Side Variables Used by the Widget

The widget template expects the following variables to be available at render time:

- `$intro`  
  Welcome content shown at the start of the conversation.

- `$placeholder`  
  Placeholder text for the input field.

- `$button_text`  
  Label for the submit button.

- `$thinking_text`  
  Text used in the status region while the chatbot is processing.

- `$reset_text`  
  Label for the reset button.

- `$disclaimer_text`  
  Text shown in the footer disclaimer area.

It also references:

- `$page->id`  
  Used as `data-pid` on `#chatbot-cta` to allow page-aware behaviour.

- `$user->language->id`  
  Used as `data-ln` on the input to support language-aware handling.

---

### 7.6 Reset and Status UI

The template includes a reset button container:

- `.chatbot-clear` (initially `hidden`)
- button `.chatai-reset`

The JavaScript may choose to show this control only after one or more messages have been sent, or when a conversation reaches a terminal state (such as a configured message limit).

The status region `#chatbot-status` is designed to be toggled visible/hidden by JavaScript to indicate busy state.

---

### 7.7 CTA Container

The widget includes:

- `#chatbot-cta` with `data-pid="<?=$page->id?>"`

This container is intended for optional injected content such as a call-to-action block, links, or contextual UI.

If unused, it can remain empty. If removed, any JS or hook that targets it will have no insertion point.

---

### 7.8 Common Customisation Pitfalls

- Removing or renaming required IDs, causing JavaScript selectors to fail
- Removing the message container (`#chatbot-messages`), preventing replies from rendering
- Changing the input element type or removing `required`, causing inconsistent submit behaviour
- Removing the status region, reducing accessible feedback during processing
- Editing module-supplied widget/JS/CSS files directly, then losing changes on upgrade

---

### 7.9 Styling Contract and Customisation

ChatAI’s default CSS is designed to be fully replaceable while relying on a small, stable set of class names applied by the widget and JavaScript.

Developers are free to provide their own CSS, provided the required class names are retained.

---

#### CSS Variables

The default stylesheet defines a set of CSS custom properties (variables) for colours, spacing, typography, and layout.

These variables provide a simple way to theme the chatbot without modifying structural styles.

Custom CSS may override these variables to adapt ChatAI to site branding.

---

#### Required Structural Classes

The following class names are used as structural or behavioural hooks and should be preserved in any custom widget or stylesheet:

- `.chatbot-toggle`
- `.chatbot-dialog`
- `.chatbot-msg-wrapper`
- `.chatbot-messages`
- `.chatbot-form`
- `.chatbot-input`
- `.chatbot-submit`
- `.chatbot-clear`
- `.chatai-reset`
- `.chatbot-status`
- `.chatbot-status-visible`
- `.chatbot-status-sr`
- `.chatbot-footer`

Removing or renaming these classes may result in broken layout, missing states, or inaccessible behaviour.

---

#### Classes Applied by ChatAI at Runtime

ChatAI applies the following classes dynamically to reflect message roles and states:

- `.chatbot-msg`
- `.user`
- `.bot`
- `.chatbot-warning`
- `.chatbot-cutoff`
- `.chatbot-name`

Custom CSS should account for these classes if message styling is changed.

---

#### Accessibility and Motion Preferences

The default CSS includes:

- focus-visible styles for keyboard navigation,
- `aria-live` compatible status styling,
- reduced-motion handling via `prefers-reduced-motion`,
- and RTL support for message alignment.

If replacing the default CSS, ensure equivalent accessibility considerations are preserved.

---

#### Safe Customisation Guidelines

When customising styles:

- You may add additional classes, wrappers, or data attributes freely.
- You may restructure markup, provided required IDs and class names remain intact.
- Avoid targeting elements solely by tag name; prefer class-based selectors.
- Do not rely on internal DOM order beyond what is documented.

For upgrade safety, do not edit the default CSS file shipped with the module.  
Instead, copy it to a site-managed location and reference it via the module configuration.

---

### Frontend Integration

ChatAI requires three render calls to be included in a template context:

- `renderStyles()` — include in `<head>`
- `renderWidget()` — include before `</body>`
- `renderScripts()` — include before `</body>`, after the widget

These methods output the chatbot CSS, HTML markup, and JavaScript respectively.

Example:

```php
$chatai = $modules->get('ChatAI');

echo $chatai->renderStyles();
echo $chatai->renderWidget();
echo $chatai->renderScripts();
```

No frontend output is injected automatically.

---

## chatai-api.php

The `chatai-api.php` template provides the server-side endpoint used by ChatAI to process chat requests.

This template is created automatically during installation, along with a corresponding page. Together, they act as the conduit between the frontend chatbot and the OpenAI API.

---

### Purpose

The `chatai-api.php` template:

- receives chat requests from the frontend JavaScript,
- validates input and session state,
- applies limits and safeguards,
- forwards approved requests to OpenAI,
- and returns structured responses to the frontend.

This template does not render visible page content and is intended to be accessed programmatically.

---

### Installation Behaviour

During installation, ChatAI:

- creates the `chatai-api.php` template in `site/templates`,
- creates a page using this template,
- and configures it as the internal endpoint for all chat requests.

No manual setup is required.

---

### Public Accessibility

The page using the `chatai-api.php` template must be **publicly accessible**.

This is required because:
- frontend chat requests originate from unauthenticated visitors,
- the JavaScript communicates with this endpoint directly,
- and it must be reachable without user login.

Security is enforced at the application level through:
- input validation,
- rate limiting,
- blacklist and strike handling,
- and server-side checks before any external request is made.

---

### Modification Guidance

In most cases, this template should not be modified.

Customisation is typically handled through:
- ChatAI configuration,
- hooks and extension points,
- or frontend rendering changes.

If this template is changed, ensure that:
- request handling logic remains intact,
- response formats are preserved,
- and no output is sent unintentionally.

---

### Do Not Remove

Removing or restricting access to the page using this template will prevent the chatbot from functioning.

If the endpoint is unavailable, frontend requests will fail and no responses will be generated.

---

### Compatibility Notes

The `chatai-api.php` endpoint is implemented as a standard ProcessWire template.

It can be integrated with alternative routing or API layers, such as AppAPI, if required. Configuration of AppAPI or other routing approaches is outside the scope of this documentation and is left to the site developer.


## chat-rag.php

The chat-rag.php template renders text-first content excerpts used by ChatAI during Retrieval-Augmented Generation (RAG), providing a controlled and predictable rendering layer for chatbot responses.

This template is created automatically during installation and is not intended to be visited directly by site visitors.

---

### Role in ChatAI

When ChatAI determines that site content is relevant to a user’s question, it does not inject raw page output into the response.

Instead, selected content is passed through `chat-rag.php`, which:

- renders a curated, text-oriented version of the page,
- allows site developers to decide what field content is safe for indexing,
- and avoids depending on brittle layout or selector-based DOM extraction.

This keeps responses focused and suitable for display inside chat messages.

---

### Installation Behaviour

During installation, ChatAI:

- creates a `chat-rag.php` template in `site/templates`,
- creates a corresponding page using this template,
- and uses it as the standard renderer for RAG excerpts.

No manual setup is required.

---

### What Is Rendered

`chat-rag.php` renders **curated indexing content**, not full pages.

A typical default output includes:

- prioritised title or headline text,
- selected body content extracted from eligible fields,
- optional descriptive metadata (for example image captions),
- and minimal markup suitable for inline display.

The intent is to provide enough context to support an answer, not to reproduce the original page.

For sites that need stricter control, copy the default module template to `/site/templates/chatai-rag.php` and curate it there. This is also the correct place to skip fields that should not be added to the vector database, including template-specific exceptions.

---

### Field Types Handled by Default

The default `chat-rag.php` template is designed to handle a broad set of commonly used ProcessWire field types, with an emphasis on text-first content.

#### Text-Based Fields

Standard text fields are rendered using ProcessWire’s normal rendering logic, including:

- Text
- Textarea
- CKEditor / TinyMCE (RTE)

Rendered markup is later normalised to plain text by the RAG indexer.

---

#### Title and Headline Fields

If present, the following fields are prioritised and rendered first:

- `title`
- `headline`

This ensures that primary page context is indexed before supporting content.

---

#### Repeater and RepeaterMatrix

Repeater and RepeaterMatrix fields are supported.

For each repeater item:

- subfields are iterated in template order,
- formatted output via `render()` is preferred where available,
- raw values are used as a fallback if rendering fails.

This allows structured content blocks to contribute meaningful text without relying on layout-specific markup.

---

#### Page Reference Fields

Page reference fields are handled in a simplified, text-oriented way.

When encountered:

- referenced pages are iterated,
- page titles are extracted,
- complex relationships are intentionally reduced to readable labels.

This avoids unintentionally pulling full page bodies into the index.

---

#### Image and File Fields

Image and file fields are supported in a descriptive context only.

Where available, the following metadata is extracted:

- image descriptions
- image notes
- file descriptions
- file notes

Binary content itself is not indexed.

---

#### Generic Fallback Rendering

Any field not explicitly handled falls back to generic rendering:

- `render()` is used where possible,
- raw values are used as a final fallback.

This allows most custom or less common field types to contribute text without breaking the indexing process.

---

### Design Intent

The default renderer is intentionally conservative.

It is designed to:

- extract meaningful text,
- minimise layout noise,
- and behave predictably across a wide range of sites.

---

### Customisation and Extension

Sites with complex layouts or specialised field types may require additional handling.

Developers can adapt RAG rendering by:

- modifying the site-level `chat-rag.php` template,
- refining content scope in `chat-rag.php`,
- or introducing site-specific logic via hooks.

For most sites, improving excerpt quality is best achieved by adjusting content selection and indexing rules rather than changing this template.

Developers are encouraged to share approaches for handling complex content structures with the ProcessWire community.

---

### Important Notes

- The `chat-rag.php` template is part of ChatAI’s core RAG pipeline.
- Removing or breaking this template or its associated page may prevent ChatAI from presenting indexed excerpts correctly.
- If RAG rendering fails, responses may lose supporting context or degrade in relevance.
