## 7. Frontend Integration

ChatAI ships with a default frontend widget (PHP), JavaScript, and CSS.

This chapter explains how the widget is structured, which parts can be customised safely, and which attributes must remain stable because they are used by the JavaScript.

**The Anatomy of a Chatbot** provides a concise overview of how chatbot components interact and how site content is supplied to a model.

---

### 7.1 Recommended Approach for Customising Frontend Assets

Best practice is to copy the default widget, JavaScript, and CSS files into your preferred site location and reference those copies via the module path settings.

This avoids editing module files directly, which may be overwritten during upgrades.

After copying the files, update the corresponding paths in **Modules > ChatAI** so ChatAI loads your custom widget, JavaScript, and CSS files instead of the module defaults.

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

### 7.8 Styling Contract and Customisation

ChatAI’s default CSS is designed to be fully replaceable while relying on a small, stable set of class names applied by the widget and JavaScript.

Developers may use their own CSS as long as the required class names are retained.

---

#### CSS Variables

The default stylesheet defines CSS custom properties for colours, spacing, typography, and layout.

You may override these to adapt ChatAI to site branding without changing structural styles.

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

This template is created automatically during installation, along with a corresponding page. Together, they act as the conduit between the frontend chatbot and the configured model provider.

---

### Purpose

The `chatai-api.php` template:

- receives chat requests from the frontend JavaScript,
- validates input and session state,
- applies limits and safeguards,
- forwards approved requests to the selected model API,
- and returns structured responses to the frontend.

This template does not render visible page content and is intended to be accessed programmatically.

---

### Installation Behaviour

During installation, ChatAI:

- creates the `chatai-api.php` template in `site/templates`,
- creates a page using this template with **published** and **hidden** statuses,
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

---

### Compatibility Notes

The `chatai-api.php` endpoint is implemented as a standard ProcessWire template.

It can be integrated with alternative routing or API layers, such as AppAPI, if required. Configuration of AppAPI or other routing approaches is outside the scope of this documentation and is left to the site developer.


## chatai-rag.php

`chatai-rag.php` is not part of the frontend widget contract.

It is the indexing view used to curate what site content enters the vector database for content lookup.

For details on:

- where that file lives,
- how to override its path,
- what it renders,
- and how it affects indexed content,

see **Content and RAG Management**.
