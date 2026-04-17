# The Anatomy of a Website Chatbot

This guide explains how a website chatbot works at a conceptual level.

It is intended for readers who are not deeply technical but want a clear mental model of the moving parts involved and why design choices matter.

---

## Purpose of This Guide

This guide describes the anatomy of a **site-integrated (“local”) website chatbot**.

In this model:
- chatbot logic runs as part of the website application,
- the site controls prompts, content, limits, and behaviour,
- and an external AI model is used as a text-generation tool.

This guide does **not** cover:
- SaaS-hosted chatbot platforms,
- third-party widgets with opaque behaviour,
- or fully managed chatbot services where site owners have limited control.

Those approaches are valid in many situations, but they follow a different architecture and set of trade-offs.

---

## The Big Picture

A website chatbot is not a single system.

It is a workflow made up of several distinct parts working together:

1. A visitor types a message.
2. The website prepares context and instructions.
3. An AI model generates a response.
4. The website decides what to show back to the visitor.

Understanding which part is responsible for what is key to understanding safety, reliability, and control.

---

## The User

### What It Is

The user is the person interacting with the chatbot through the website.

They may be:
- anonymous,
- logged in,
- curious,
- confused,
- or deliberately probing the system.

---

### What It Does

The user provides text input in the form of questions, instructions, or statements.

---

### How It Is Used

User input is:
- unpredictable,
- sometimes ambiguous,
- and occasionally hostile or manipulative.

---

### Why That Matters

User input must always be treated as **untrusted**.

A chatbot should never assume:
- good intent,
- technical understanding,
- or compliance with site rules.

---

## The Website (Application Layer)

### What It Is

The website application is the code and configuration that you control.

It sits between the user and the AI model.

---

### What It Does

The website is responsible for:

- deciding whether a request is allowed,
- gathering site content and context,
- applying limits and safeguards,
- instructing the AI model,
- and validating the response.

---

### How It Is Used

Every chatbot interaction passes through the website application.

The AI model does not act independently.

---

### Why That Matters

Security, cost control, access rules, and predictability all live here.

The website is the authority.  
The AI model is a tool.

---

## The AI Model (Large Language Model)

### What It Is

A Large Language Model (LLM) is a **text-generation system** trained to read and produce written language.

This guide focuses specifically on **text-based models**.

It does not cover image, audio, video, or multimodal AI systems.

---

### What It Does

A model (short for Large Language Model, or LLM) is a text-generation system trained to read and produce written language. Throughout this documentation, the term model is used to refer to this text-generation system.
- generates text based on patterns,
- predicts likely next words,
- and responds only to the input it is given.

It does not understand truth, intent, or meaning in a human sense.

---

### How It Is Used

The model receives:
- written instructions,
- selected context,
- and user input.

It returns generated text to the website.

---

### Why That Matters

The model:
- does not know your site,
- does not know your users,
- and does not know what should be private.

All constraints must be applied by the website.

---

## Instructions and Prompts

### What They Are

Instructions (often called prompts) are structured text sent to the AI model.

They are not user-visible content.

---

### What They Do

Instructions define:
- the role of the assistant,
- tone and style,
- boundaries and constraints,
- and how content should be used.

---

### How They Are Used

Instructions are combined with user input on every request.

They shape how the model responds.

---

### Why That Matters

If instructions are poorly controlled, user input may attempt to override them.

This is where prompt injection attempts occur.

---

## Site Content and Context

### What It Is

Site content includes:
- pages,
- text,
- and structured information from the website.

---

### What It Does

Site content helps ground answers in real information rather than generic responses.

---

### How It Is Used

Content is:
- selected intentionally,
- filtered by access rules,
- and provided only when appropriate.

---

### Why That Matters

Not all content should be exposed.

A chatbot must respect the same access rules as the rest of the site.

---

## The Request–Response Flow

At a high level, a chatbot interaction follows this sequence:

1. The user submits a message.
2. The website validates the request.
3. Relevant context and instructions are gathered.
4. A request is sent to the AI model.
5. The model generates a response.
6. The website validates and filters the output.
7. The response is shown to the user.

Each step exists for a reason.

---

## What Can Go Wrong

Without clear boundaries:

- users may try to override instructions,
- private content may be exposed unintentionally,
- costs may grow unexpectedly,
- or responses may be misleading or inappropriate.

These are system design issues, not model failures.

---

## Why This Architecture Matters

A chatbot is only as safe and predictable as the system around the AI model.

Clear separation of responsibilities:
- reduces risk,
- improves reliability,
- and makes behaviour easier to reason about.

---

## How ChatAI Fits Into This Structure

ChatAI follows this site-integrated approach.

It:
- keeps control in the website,
- treats the model as a text generator,
- and provides configuration and safeguards at the application level.

Detailed configuration is covered in the Admin Guide.

---

## Summary

A website chatbot is not a single intelligent entity.

It is a system made up of:
- users,
- site logic,
- instructions,
- curated content,
- and an AI text-generation tool.

Understanding this anatomy helps site owners make safer, more predictable choices when adding AI features to their sites.

---
## Glossary of Commonly Used Terms

This glossary provides brief explanations of terms you may encounter when reading about website chatbots and AI systems.

These are simplified descriptions intended for orientation, not formal definitions.

| Term | Stands For | Meaning |
|-----|------------|---------|
| AI | Artificial Intelligence | A broad term for computer systems designed to perform tasks that normally require human intelligence, such as language processing. |
| Model | — | A text-generation system trained to produce written language based on patterns in data. |
| LLM | Large Language Model | A type of model designed specifically to work with written language. Often shortened to “model” in this documentation. |
| Prompt | — | Instructions and input text sent to the model to guide how it generates a response. |
| RAG | Retrieval-Augmented Generation | A technique where selected site content is provided to the model to help ground responses in real information. |
| Prompt Injection | — | An attempt to manipulate a chatbot by crafting input that interferes with its intended instructions or constraints. |

---
## Further Information

<!--
DOCS TODO:
- Link to “Prompt Injection and Site Security” guide
- Link to “The Anatomy of a Website Chatbot”
- Add references to authoritative external resources (OWASP, OpenAI, IBM Technology)
- Review links for longevity and vendor neutrality
-->
These resources are optional and intended for readers who want a deeper understanding beyond this introductory guide.
