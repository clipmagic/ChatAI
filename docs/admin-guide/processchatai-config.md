## 6. ChatAI Behaviour Configuration

*(ProcessChatAI – Setup / Settings → ChatAI)*

This section describes how ChatAI behaviour is configured through the ProcessChatAI interface.

It covers monitoring, indexing, presentation, prompt construction, and input interpretation. These settings control *how ChatAI operates*, not what it is technically allowed to do at a system level.

---

### 6.1 Dashboard

The Dashboard provides an overview of ChatAI activity, health, and indexing status.

It is intended for monitoring and operational insight rather than configuration.

---

#### Update ChatAI on Save

Controls whether eligible site content is processed automatically when pages are saved.

- When enabled, relevant content is updated during normal page save operations.
- When disabled, content updates are deferred until a manual re-index is run.

Disabling this option is recommended when performing bulk page updates or imports. Deferred updates can then be handled using the re-index and reconcile tools.

---

#### Analytics and KPIs

Defines thresholds used to flag potential issues on the dashboard.

These values do not affect chatbot behaviour directly. They are used only to highlight conditions that may require attention.

---

##### Success Rate Warning Threshold (%)

Defines the minimum acceptable success rate before the dashboard flags a warning.

A lower success rate may indicate configuration issues, frequent blocking, cutoffs, or external API failures.

---

##### Average Latency Warning (ms)

Defines the response time above which interactions may feel slow.

This helps identify performance degradation before it becomes user-visible.

---

##### Average Latency High (ms)

Defines the response time above which performance is considered poor.

Sustained values above this threshold may warrant investigation of model selection, request complexity, or external service responsiveness.

---

#### Chat Activity

Summarises recent chatbot usage for the selected time period.

Metrics typically include:

- number of chats,
- total messages,
- errors and cutoffs,
- blocked or rate-limited interactions.

---

#### Message Volume and Event Types

Charts summarise trends over time and the distribution of event types, such as replies, blocked requests, cutoffs, and rate-limited events.

These views help identify unusual patterns or changes in behaviour.

---

#### Insights

Highlights notable conditions based on current metrics and configured thresholds.

Insights are informational only and do not alter system behaviour.

---

#### Update Vector Database (RAG)

Provides tools for maintaining and reconciling indexed site content.

What content is eligible for indexing is defined in the **Prompt** tab under *Content Guidance*.

---

##### Start ID

Defines the page ID from which re-indexing should begin.

This allows administrators to:

- resume a previous re-index operation,
- process content in controlled ranges,
- or limit re-indexing to newer content.

Only pages with an ID greater than this value are processed.

---

##### Batch Size

Controls how many pages are processed per batch during re-indexing.

Smaller batches reduce system load and risk of timeouts.  
Larger batches complete faster but may increase load on larger sites.

---

##### Dry Run

When enabled, the re-index process runs in report-only mode.

No changes are made to stored vectors, allowing administrators to safely preview scope and impact.

---

##### Force All Pages to Be Reindexed

Forces ChatAI to rebuild vector entries for **all eligible pages**, even if they appear unchanged.

When enabled:

- existing vector entries are discarded,
- all eligible pages are reprocessed from scratch.

This is useful when:

- indexing or extraction logic has changed,
- rendering behaviour was adjusted,
- or a clean rebuild is required.

Because this operation can be resource-intensive, it should be used deliberately.

---

##### Default Selector and Overrides

A typical default selector looks like:

`has_parent!=2, include=all, template!=admin, id!=27, template!=http404, sort=id, limit=200`

This default is automatically adjusted based on:
- site configuration,
- the **Start ID** value, and
- the **Batch Size** value.

Developers can override the selector via a hook if site-specific indexing rules are required.

---

##### Run Re-Index and Reconcile

Scans eligible pages, updates missing or outdated entries, and removes entries that no longer qualify.

Progress feedback includes the **last page ID processed**, allowing interrupted runs to be resumed.

---

#### Bulk Remove Vectors

Provides tools to **remove vector entries only** from the database.

This section is **collapsed by default** to reduce the risk of accidental use.

---

##### Purpose

Bulk removal is intended for corrective maintenance, such as:

- excluding content that should no longer influence chatbot responses,
- cleaning up vectors for deprecated templates or sections,
- or resetting part of the RAG index.

This operation **does not delete pages**.

---

##### Page Selector

A ProcessWire selector used to identify pages whose vectors should be removed.

Only vector entries associated with matching pages are affected.

---

##### Maximum Pages

Limits how many matching pages are processed in a single run.

This helps prevent long-running operations on large result sets.

---

##### Dry Run

When enabled:

- matching pages are reported,
- no vectors are deleted.

Always use Dry Run first.

---

##### Maximum Pages

Limits how many matching pages are processed in a single run.

This helps prevent long-running operations on large result sets.

---
##### Run Bulk Removal

Removes matching entries from `chatai_vector_chunks`.

- Pages themselves are untouched.
- Only chatbot vector data is removed.

**Recommendation:** take a full ProcessWire database backup before running this operation.

---
### 6.2 Personalise

The Personalise tab controls the chatbot’s visible identity, tone, and user-facing interface text.

These settings affect how the chatbot presents itself to users but do not change how answers are generated or what content is used. Behavioural logic and content guidance are configured separately.

Most fields support multiple languages where site language support is enabled.

---

#### Chatbot Persona

This section defines how the chatbot identifies itself to users.

---

##### Chatbot Name

Sets the name used by the chatbot when introducing itself or referencing its identity.

This value may be referenced in other fields using placeholders and is typically shown in the welcome message.

---

##### Welcome Message

Defines the initial message shown to users when the chatbot is opened.

This message is displayed before any user interaction and is intended to orient users to what the chatbot can help with.

---

#### Background

This section provides contextual information that influences how responses are phrased, without changing the underlying logic.

---

##### Role

Describes the role the chatbot should adopt when responding to users.

This is typically a short description such as a site guide, assistant, or helper, and is used to shape tone and framing rather than factual content.

---

##### Employer

Provides optional contextual information about who the chatbot represents.

This field can be used to reinforce organisational context but does not affect content sourcing or permissions.

---

##### Tone of the Answers

Defines the general tone used in responses, such as friendly, professional, or neutral.

Tone settings influence phrasing and style rather than accuracy or scope.

---

#### User Input Form

This section controls the text shown in the chatbot input interface.

These fields affect usability and clarity but do not alter how messages are processed.

---

##### Input Field Placeholder

Defines the placeholder text shown inside the input field before the user starts typing.

---

##### Thinking Text

Defines the message shown while the chatbot is processing a request.

This text is displayed during response generation to indicate activity.

---

##### Send Button Text

Sets the label used for the submit button in the chatbot interface.

---

##### Reset Button Text

Sets the label used for the button that clears the current conversation.

Resetting a chat starts a new conversation and clears any accumulated context.

---

#### Disclaimer Text

Defines optional disclaimer text shown to users.

This can be used to communicate limitations, legal notices, or general guidance about AI-generated responses.

---

#### Additional Information

Provides a rich-text area for supplementary content shown in the chatbot interface, typically in the footer area.

This field can be used to:
- add links,
- include supporting information,
- or provide contextual notes relevant to the site.

Content entered here is not used as input for response generation.

---

The Personalise tab is focused on presentation and user experience.

Controls that affect how content is selected, indexed, or used in responses are covered in the **Prompt** tab.

---

### 6.3 Prompt

The Prompt tab controls how ChatAI interprets user questions, which site content it may use, and how responses are structured.

This tab defines the *instructional and contextual rules* that guide response generation. It is the primary place where administrators shape what the chatbot does with site content.

Changes made here directly affect how questions are answered.

---

#### Auto-generate Prompt

This setting controls whether ChatAI automatically assembles the prompt used for each request.

- When enabled, ChatAI builds the prompt dynamically using the configured fields on this tab.
- When disabled, prompt construction is handled manually.

Auto-generation is recommended in most cases, as it ensures consistent structure and incorporates updates to related settings automatically.

---

#### Filters

This section controls whether blacklist filtering is applied at the prompt stage.

---

##### Use Blacklist

When enabled, user input is checked against the configured blacklist terms before any request is processed.

Messages containing blacklisted terms are blocked locally and are **not sent to the OpenAI API**.

Blacklist terms themselves are defined below and are fully site-controlled.

---

##### Blacklisted Terms

Defines the list of terms that trigger a blacklist strike when detected in user input.

- Terms are matched before external requests are made.
- Each match contributes to the blacklist strike count defined in the module configuration.
- This list is independent of any filtering performed by OpenAI.

Terms can be added or removed as needed and may be language-specific where multi-language support is enabled.

---

#### Additional Instructions

This field defines high-level instructions that guide how ChatAI responds.

It is typically used to:
- describe the chatbot’s core job,
- constrain the type of answers provided,
- and define formatting or linking rules.

Instructions here influence response structure and intent, not content eligibility.

---

#### Content Guidance

This section defines **which site content may be used** when answering questions.

It controls both:
- what content is indexed into the vector database, and
- what content is eligible to be referenced in responses.

This is the primary mechanism by which ChatAI uses curated site content.

To do this efficiently, ChatAI uses a Retrieval-Augmented Generation (RAG) approach.

Rather than sending full page content directly to the AI model, selected site content is first processed and stored in a searchable vector index. This index contains compact representations of relevant text extracted from your site based on the content guidance rules defined here.

When a user asks a question:
- ChatAI searches the vector index for the most relevant excerpts,
- only those excerpts are included as context for the request,
- and the full page content is never sent wholesale to the model.

This differs from normal ProcessWire page storage, which is optimised for rendering and retrieval, not semantic search. The vector index exists solely to identify and supply the *most relevant fragments* of content needed to answer a specific question.

This approach helps to:
- improve answer relevance,
- reduce unnecessary data sent to the model,
- control costs,
- and keep responses focused on your site’s content.

The index is built and maintained by the ChatAI indexer, which processes eligible pages based on the template, selector, and exclusion rules defined below.

---

##### Include Templates

Defines which page templates are eligible for inclusion.

Only pages using the selected templates are considered when building the vector database.

Leaving this field empty includes all templates.

---

##### Exclude Templates

Defines templates that should be excluded, even if they would otherwise be included.

This allows fine-grained control when only certain sections of the site should be referenced.

---

##### Allowed HTML Tags

Defines which HTML tags may appear in chatbot responses.

Responses are filtered to include only the specified tags, ensuring safe and predictable output.

This setting does not affect content indexing.

---

##### Max Number of Page Links

Defines the maximum number of internal page links that may be included in a single response.

This helps keep answers focused and avoids overwhelming users.

---

##### Response Approx Words

Defines the approximate maximum length of responses.

This value guides response brevity but works in combination with global response limits defined in the module configuration.

---

#### HTML Selectors

These fields control which parts of a page’s markup are considered when extracting content.

---

##### Selectors to Include

Defines CSS selectors that identify content-rich areas of a page.

Only content within these selectors is considered when building the vector database.

This allows irrelevant layout or navigation markup to be ignored.

---

##### Exclude Content Within These Selectors

Defines CSS selectors that should be excluded from content extraction.

This is commonly used to remove headers, footers, navigation, forms, or other non-content elements.

---

#### Prompt Preview

The Prompt Preview shows the fully assembled prompt based on the current configuration.

This preview is read-only and is provided for inspection and troubleshooting.

It reflects:
- current persona and instructions,
- content guidance rules,
- formatting and safety constraints.

Reviewing the prompt preview is useful when diagnosing unexpected responses or validating configuration changes.

---

The Prompt tab defines *how ChatAI reasons about your site*.

Presentation and tone are configured in the **Personalise** tab, while operational monitoring and indexing are handled via the **Dashboard**.

---

### 6.4 Dictionary

The Dictionary tab allows you to influence how ChatAI interprets user input before it is processed further.

Unlike the Prompt tab, which defines what the chatbot should do, the Dictionary focuses on *how incoming messages are interpreted, classified, and filtered*.

---

#### Phrase Relevance vs Noise

Helps ChatAI distinguish between meaningful phrases and background noise.

---

#### Current Page Reference

llows ChatAI to treat the current page context as a relevance signal.

Useful for queries such as:

- “What is this page about?”
- “Tell me more about this”
- “Can you explain this?”

This setting:

- improves relevance for vague or contextual questions,
- does not force answers to come from the current page,
- does not override prompt or safety rules.

It acts as a relevance hint only.

---

##### Custom Terms

Defines phrases that are especially relevant to your site content.

- One phrase per line.
- Terms can optionally be weighted to increase their importance.
- Higher-weighted terms are prioritised during relevance matching.

These terms help ChatAI recognise what is *important* on your site, even when phrasing varies.

---

##### Hard Stop Noise Words

Defines words or phrases that should be ignored entirely when analysing user input.

- These are typically filler words or common language constructs.
- Terms are comma-separated.
- Matches are dropped before relevance analysis.

Use this list to prevent common or meaningless words from influencing intent detection.

---

##### Soft Stop Noise Words

Defines words or phrases that should carry less weight, but not be fully ignored.

- Terms are comma-separated.
- Matches reduce influence rather than eliminating it.

This is useful for words that are common on the site but not meaningful on their own.

---

#### Smalltalk

This section allows ChatAI to recognise and handle casual or conversational input that does not relate to site content.

---

##### Triggers

Defines phrases that indicate informal or non-goal-oriented interaction, such as greetings or acknowledgements.

- Terms are comma-separated.
- Matches are checked before content lookup.

---

##### Reply

Defines the response shown when a smalltalk trigger is detected.

This allows ChatAI to respond politely and redirect the user toward a more meaningful query.

---

#### Actions

This section identifies input that implies the user wants ChatAI to *do something* rather than *find information*.

---

##### Triggers

Defines phrases associated with actions such as changing, enabling, creating, or modifying content.

- Terms are comma-separated.
- These triggers are informational only and do not perform actions.

---

##### Reply

Defines the response shown when an action trigger is detected.

This is typically used to clarify intent or explain limitations, such as guiding users toward supported actions.

---

#### Follow Up

This section helps ChatAI recognise when a user is continuing a previous thread rather than starting a new one.

---

##### Triggers

Defines phrases that indicate continuation, such as requests for more detail or clarification.

- Terms are comma-separated.
- Used to maintain conversational context.

---

##### Reply

Defines the response used when follow-up intent is detected.

This typically prompts the user to clarify or expand on the previous topic.

---

#### Questions

This section identifies common question words used to initiate information requests.

---

##### Triggers

Defines words that signal an explicit question, such as who, what, where, when, why, or how.

These triggers help ChatAI recognise direct information-seeking intent.

---

#### Ambiguous or No Context

This section defines how ChatAI responds when insufficient context is available.

---

##### Reply

Defines the response shown when the user’s input is too vague to match meaningful content or intent.

This response should gently prompt the user to provide more information.

---

#### How the Dictionary Is Used

Dictionary rules are applied early in the request flow.

They influence:
- how input is classified,
- which response path is chosen,
- and whether content lookup is attempted.

They do not:
- generate answers,
- override prompt instructions,
- or bypass blacklist and safety checks.

The Dictionary exists to reduce ambiguity, improve relevance, and handle common conversational patterns predictably.
