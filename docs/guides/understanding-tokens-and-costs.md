# Understanding Tokens and Costs

This guide explains what tokens are in AI systems and why they matter when using a website chatbot.

It is intended for readers who are new to AI terminology and want a practical understanding of how usage is measured and billed.

---

## What a Token Is

A token is a small unit of text used by an AI model when reading input and generating output.

Tokens are not the same as:
- words,
- characters,
- or sentences.

Depending on the text, a token may represent:
- part of a word,
- a whole short word,
- punctuation,
- numbers,
- or whitespace.

This is why token counts can feel unintuitive at first.

---

## Why Tokens Matter

Most AI models measure usage in tokens.

Tokens affect:
- how much text you can send in a request,
- how much text the model can return,
- how much a request costs,
- and how much conversation history can fit within the model’s limits.

When people talk about AI usage, limits, or pricing, they are often talking about tokens.

---

## Input Tokens and Output Tokens

There are two main token types to keep in mind:

- **Input tokens**: the text sent to the model
- **Output tokens**: the text returned by the model

For a website chatbot, input tokens may include:
- system instructions,
- selected site content,
- recent conversation history,
- and the user’s latest message

Output tokens are the model’s reply.

Both sides usually matter for pricing.

---

## Why a Short Question Can Still Use Many Tokens

A visitor may type a very short message, while the full model request is much larger.

That is because the request may also include:
- hidden instructions,
- retrieved site content,
- prior messages,
- and formatting or structural text used by the application.

In other words, the visible chat message is only part of what the model receives.

---

## How Tokens Influence Cost

Most AI providers charge based on token usage.

In simple terms:
- more input tokens usually means higher cost,
- more output tokens usually means higher cost,
- and longer conversations often become more expensive over time.

Some providers price input and output tokens differently.

This means a request that sends a large amount of site context, or produces a long answer, may cost more than expected even if the user’s visible question is short.

---

## How Tokens Influence Response Size

Models also have token limits.

These limits affect how much total text can fit in a request and response combined.

If too much content is included, the application may need to:
- trim conversation history,
- reduce retrieved context,
- shorten instructions,
- or limit response length.

This is one reason prompt design and content selection matter.

---

## Why Retrieved Content Should Be Curated

For ChatAI, selected site content may be included to help ground responses in real information.

That can improve answer quality, and it also increases token usage.

If too much irrelevant content is sent:
- costs rise,
- useful context may be crowded out,
- and the model may respond less clearly.

Good retrieval is not just about finding more content. It is about finding the most relevant content.

---

## Why Token Counts Can Vary

Token usage is not always obvious from the way text looks on screen.

The same number of words can produce different token counts depending on:
- language,
- punctuation,
- formatting,
- repeated history,
- and the provider’s tokenizer.

This is why token usage should be treated as a measured value, not a guess based on word count.

---

## Practical Takeaway for ChatAI

When working with ChatAI, token usage is influenced by:
- prompt settings,
- message history,
- retrieved site content,
- and the length of the model’s reply.

If responses are costing more than expected, the first things to review are usually:
- how much context is being sent,
- how long replies are allowed to be,
- and whether unnecessary conversation history is being included.

---

## Summary

Tokens are the units AI models use to read and generate text.

They affect pricing, request size, response length, and how much context can fit into a conversation.

Understanding tokens helps explain why a chatbot’s cost is shaped by more than the visitor’s visible message.

---

## Further Information

- [ChatAI Glossary](../glossary.md)
- [The Anatomy of a Website Chatbot](anatomy-of-a-website-chatbot.md)
- [How ChatAI Differs from Other Website Chatbots](how-chatai-differs-from-other-website-chatbots.md)
