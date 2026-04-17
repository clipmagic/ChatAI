## Content and RAG Management

This guide explains how ChatAI selects, indexes, and manages site content for use in Retrieval-Augmented Generation (RAG).

If you are new to how chatbots assemble responses or how content is supplied to a model, it is helpful to first read **The Anatomy of a Chatbot**, which provides a conceptual overview of the components involved and how they interact.

This section focuses on *what content is eligible*, *how scope and visibility are controlled*, and *how indexing is maintained*. It does not cover how excerpts are rendered or displayed.

---

### What Content Is Indexed

ChatAI indexes only content that is explicitly configured for RAG use.

Content eligibility is defined in the **Prompt** tab, where administrators specify which templates, fields, and selectors are considered suitable for indexing.

Only content that meets these criteria is processed by the indexer and made available for reference during response generation.

This allows site owners to ensure that:

- only curated, intentional content is used,
- internal or administrative pages are excluded,
- and irrelevant layout or navigation markup is ignored.

---

### Controlling Scope and Visibility

Content scope is controlled through a combination of:

- included and excluded templates,
- CSS selectors used for extraction,
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
- selectors that capture layout or navigation instead of content,
- indexing highly structured or non-textual fields,
- or expecting RAG to replace site navigation or search.

In most cases, improving results involves:

- tightening content scope,
- refining selectors,
- and reindexing curated content.

Changes to excerpt rendering are rarely the first step.
