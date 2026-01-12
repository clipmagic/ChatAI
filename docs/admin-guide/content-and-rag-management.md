## Content and RAG Management

This section describes how ChatAI selects, indexes, and manages site content for use in Retrieval-Augmented Generation (RAG).

It focuses on *what content is eligible*, *how scope is controlled*, and *how indexing is maintained*, rather than how excerpts are rendered.

---

### What Content Is Indexed

ChatAI indexes only content that is explicitly configured for RAG use.

Content eligibility is defined by configuration in the **Prompt** tab, where administrators specify which templates, fields, and selectors are considered suitable for RAG use.

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
- and ProcessWire’s access control rules.

ChatAI respects page visibility and access permissions when indexing content.  
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
- excerpts are rendered in the appropriate language,
- and mixed-language responses are avoided.

Administrators should ensure that content guidance and indexing rules are reviewed for each language as needed.

---

### Reindexing and Performance Notes

Administrators control when the RAG index is updated.

Indexing occurs when triggered via the ChatAI Dashboard or during configured update operations.

Reindexing may be required when:
- content is added, removed, or significantly changed,
- templates or content selection rules are adjusted,
- or language-specific content is updated.

Reindexing is performed in controlled batches and can be tuned to suit site size and hosting environment.  
Administrators are encouraged to adjust batch size and scope as needed and avoid unnecessary reindex operations.

---

### Optimising RAG Results

When RAG output does not behave as expected, common causes include:

- overly broad template inclusion,
- selectors that capture layout or navigation instead of content,
- indexing highly structured or non-textual fields,
- or expecting RAG to replace site navigation or search.

In most cases, improving RAG results involves:
- tightening content scope,
- refining selectors,
- and reindexing curated content.

Changes to excerpt rendering are rarely the first solution.
