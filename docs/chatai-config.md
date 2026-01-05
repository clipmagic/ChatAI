## 5. ChatAI Module Configuration 

### 5.1 OpenAI Settings

This section covers the settings used to connect ChatAI to the OpenAI API and control how requests and responses are handled at a technical level.

These settings apply site-wide and affect all ChatAI interactions, regardless of where or how the chatbot is used.

They are typically configured once and changed infrequently.

---

#### OpenAI API Key

The OpenAI API key is required for ChatAI to communicate with the OpenAI service.

- This key is issued by OpenAI and is associated with your OpenAI account.
- It is used for all requests sent by ChatAI.
- Without a valid API key, ChatAI cannot generate responses.

If the API key is missing or invalid, ChatAI will fail gracefully and report the issue in diagnostics.

---

#### OpenAI Model

This setting determines which OpenAI model is used to generate responses.

Different models vary in:
- response quality,
- reasoning capability,
- latency,
- and cost.

Changing the model affects all ChatAI responses across the site.  
Model selection is a trade-off between quality, performance, and cost.

---

#### Verbosity

Verbosity controls how detailed ChatAI responses are.

- Lower verbosity produces shorter, more concise replies.
- Higher verbosity allows for more detailed explanations.

Verbosity works in combination with prompt configuration and response limits.  
It does not guarantee a specific response length but influences how much detail the model attempts to provide.

---

#### Reasoning Effort

Reasoning effort controls how much effort the model applies when generating a response.

- Higher values encourage more deliberate reasoning.
- Lower values favour faster, more direct responses.

Increasing reasoning effort can improve response quality for complex questions but may increase response time and cost.

---

#### Max Tokens

This setting defines the maximum number of tokens ChatAI may use for a single response.

Tokens are the units used by the OpenAI API to measure text length.  
Both prompts and responses consume tokens, and token usage directly affects API cost.

This limit helps to:
- prevent excessively long responses,
- control usage costs,
- and ensure predictable behaviour.

If a response would exceed the configured limit, ChatAI will shorten it and provide clear feedback rather than failing.

---

#### Maximum Questions

This setting limits the number of questions allowed per conversation.

It is used to:
- prevent excessively long chat sessions,
- control overall usage,
- and reduce the risk of runaway costs.

Once the limit is reached, ChatAI will stop accepting new questions for that conversation and inform the user accordingly.

---

### 5.2 Blacklist and Blocking

This section controls how ChatAI responds when prohibited terms are detected in user input.

Blacklist and blocking settings apply site-wide and are enforced **before** a request is sent to the OpenAI API.

They are designed to provide a simple, predictable safeguard against misuse.

---

#### Maximum Blacklist Strikes

This setting defines the maximum number of blacklist strikes allowed before a conversation is blocked.

A blacklist strike occurs when a user message matches a term defined in the ChatAI configuration.

- Each strike is recorded against the current conversation.
- Once the configured limit is reached, further messages in that conversation are blocked.
- The user is informed that the conversation can no longer continue.

This setting controls **how many violations are tolerated**, not which terms are considered prohibited.

---

#### Source of Blacklist Terms

Blacklist terms are defined in the ChatAI process module under **Setup / Settings → ChatAI**, using a multi-language field on the **Prompt** tab.

This makes blacklist behaviour:
- explicit,
- site-controlled,
- and independent of any filtering performed by the OpenAI service.

ChatAI does not rely on OpenAI-provided blacklists for this functionality.

---

#### Request Handling

Messages containing blacklisted terms are **not sent to the OpenAI API**.

Detection and blocking occur entirely within ChatAI before any external request is made.  
This prevents unnecessary API usage and ensures prohibited content is handled locally.

---

#### Behaviour and Scope

Blacklist strikes and blocking are enforced at the conversation level.

This means:
- blocking affects only the current chat session,
- other conversations are not impacted,
- and no site-wide lockout occurs.

This approach prevents repeated misuse within a single conversation while avoiding unnecessary restriction of legitimate users.

---

#### Why This Setting Matters

Blacklist and blocking limits help to:

- prevent repeated submission of prohibited content,
- reduce unnecessary API usage,
- and provide clear boundaries for acceptable interaction.

The default value is intentionally conservative and can be adjusted based on site requirements.

---

### 5.3 Roles and Access Control

This section controls which ProcessWire roles are allowed to access and modify ChatAI behaviour through the ChatAI process module.

These settings affect **administrative access**, not frontend placement or visibility.

---

#### Access to the ChatAI Admin Screens

Access to the ChatAI process module under **Setup / Settings → ChatAI** is controlled by the ProcessWire permission:

- `chatai`

Only:
- superusers, and
- users whose roles include the `chatai` permission

can access the ChatAI admin screens (Dashboard, Prompt, Dictionary, and related tabs).

To grant access to a non-superuser admin, assign the `chatai` permission to an existing role or to a dedicated role created for ChatAI administration.

ChatAI does not manage roles internally. Access control is handled entirely through standard ProcessWire roles and permissions.

---

### 5.4 File and Asset Paths

This section defines the filesystem paths used by ChatAI for its frontend widget, JavaScript, and CSS assets.

These settings allow developers to customise the chatbot’s appearance and behaviour **without modifying the default files shipped with the module**.

Custom files are preserved across module upgrades.

---

#### Recommended Approach

Best practice is to copy the default widget, JavaScript, or CSS files provided by ChatAI into your preferred site location and reference those copies via the path settings.

For example: /site/templates/chatai-widget.php

This approach allows you to start from a known, working baseline while making site-specific changes safely.

Avoid modifying the default files shipped with the module, as these may be overwritten during upgrades.

---

#### Path to Widget File

This setting defines the path to the frontend widget markup used by ChatAI.

By default, ChatAI uses the widget file provided with the module.  
Developers can override this path to use a custom widget file instead.

Using a custom widget file allows:
- full control over markup structure,
- layout changes,
- and integration with site-specific HTML patterns.

---

#### Path to JavaScript File

This setting defines the path to the JavaScript file used to power the chatbot frontend.

A custom JavaScript file may be supplied to:
- extend or modify frontend behaviour,
- integrate with other site scripts,
- or adjust interaction logic.

---

#### Path to CSS File

This setting defines the path to the CSS file used to style the chatbot.

Providing a custom CSS file allows complete visual control without modifying the module’s default styles.

---

#### Upgrade Safety

The default widget, JavaScript, and CSS files provided by ChatAI may be updated during module upgrades.

Custom files referenced via these path settings are **not** modified or overwritten during upgrades.

This approach allows developers to customise ChatAI safely while continuing to receive module updates.

---

#### Required Markup and Selectors

When supplying custom widget, JavaScript, or CSS files, certain class and ID attributes **must be retained**.

These attributes are referenced internally by ChatAI’s JavaScript logic and are required for correct operation.

Developers are free to:
- change structure,
- add additional markup,
- and apply custom styling,

provided the required identifiers remain intact.

Failure to retain required classes or IDs may result in the chatbot not functioning correctly.

---

These settings are intended for developers who want full control over frontend presentation while keeping ChatAI’s core logic unchanged.

---

### 5.5 Uninstall Behaviour

Uninstalling ChatAI follows standard ProcessWire module behaviour.

Only files and data created by the module itself are affected.  
Any custom widget, JavaScript, or CSS files referenced via custom paths are **not** removed during uninstall.

---

These settings define the technical boundaries within which ChatAI operates.  
Behavioural tuning and content guidance are configured separately via the ChatAI process module.
