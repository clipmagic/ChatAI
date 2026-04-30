# How ChatAI Differs from Other Website Chatbots

This guide explains how ChatAI differs from other website chatbot solutions and why those differences matter.

It is intended to help site owners and developers choose the right type of chatbot for their situation.  

Different approaches are appropriate in different contexts.

---

## Two Broad Approaches to Website Chatbots

Most website chatbots fall into one of two categories:

1. **Site-integrated chatbots**, where chatbot behaviour is governed by the website itself.
2. **SaaS-hosted chatbots**, where behaviour is largely managed by an external service.

ChatAI is a site-integrated chatbot, written as a ProcessWire module for use on ProcessWire websites.

All core behaviour, configuration, and content handling live within the site itself and are managed through the ProcessWire admin. The site communicates directly with the AI model and remains responsible for prompts, limits, safeguards, and content selection.

Importantly, this means the site does not expose live pages or uncontrolled site output directly to the model. *Only selected, curated content is provided as context, under explicit rules defined by the site owner*.

This differs from approaches where a ProcessWire module primarily acts as a connector or wrapper around a third-party, subscription-based chatbot service. In those models, behaviour and content handling are managed externally, and the service may read or process live web pages more broadly.

While automated page ingestion can be convenient, it may also introduce unwanted content, layout noise, or increased susceptibility to prompt manipulation if not carefully constrained. ChatAI’s design favours explicit content selection and application-level decision-making over automated discovery.

---

## Site-Integrated Chatbots (Like ChatAI)

### What This Means

With a site-integrated chatbot:

- chatbot logic runs as part of the website application,
- prompts, limits, and rules live with the site,
- and the site decides what content and context are shared.

The AI model is used as a text-generation tool, not as the controlling system.

---

### Practical Characteristics

Site-integrated chatbots typically offer:

- fine-grained ownership of prompts and behaviour,
- access-aware use of site content,
- predictable cost and usage limits,
- deep integration with site logic and structure,
- extensibility via hooks or code.

This approach assumes access to the site codebase and some technical involvement.

---

### Where This Works Well

A site-integrated chatbot is often a good fit when:

- the site has substantial or structured content,
- access control matters,
- behaviour must be audited or constrained,
- the site will evolve over time,
- or developers need greater scope for behaviour customisation.

---

## SaaS-Hosted Chatbots

### What This Means

With a SaaS-hosted chatbot:

- the chatbot runs on a third-party platform,
- configuration happens in an external dashboard,
- and the site typically embeds a widget or script.

The internal behaviour of the chatbot is largely managed by the vendor.

---

### Practical Characteristics

SaaS chatbots typically offer:

- fast setup with minimal technical effort,
- hosted dashboards and analytics,
- vendor-managed infrastructure,
- predefined workflows and features,
- limited visibility into internal prompt handling.

This approach prioritises convenience and speed.

---

### Where This Works Well

A SaaS chatbot is often a good fit when:

- time-to-launch is critical,
- the site is marketing-focused,
- deep integration is not required,
- or the team prefers a vendor-managed chatbot setup.

---

## Key Differences That Matter

### Site Ownership vs Convenience

- **ChatAI** prioritises site ownership and transparency.
- **SaaS chatbots** prioritise ease of use and speed.

---

### Prompt Ownership

- With ChatAI, prompts are owned and configured by the site.
- With SaaS solutions, prompts are often abstracted or hidden.

This affects predictability and auditability.

---

### Content Awareness

- ChatAI uses curated site content under explicit rules.
- SaaS chatbots may crawl, ingest, or summarise content in less visible ways.

---

### Access and Privacy

- ChatAI respects the site’s access rules by design.
- SaaS solutions may require careful configuration to avoid unintended exposure.

---

### Extensibility

- ChatAI supports hooks and site-specific logic.
- SaaS platforms typically limit extensibility to predefined options.

---

### Cost and Scaling

- ChatAI allows direct oversight of usage and limits.
- SaaS pricing often bundles usage, features, and support.

Neither approach is inherently cheaper. The cost profile is different.

---

## Design Philosophy Differences

ChatAI is built around a few core principles:

- the website is the authority,
- the model is a tool, not an agent,
- behaviour should be explicit and inspectable,
- and safeguards should live in application logic.

SaaS chatbots often prioritise abstraction and managed behaviour.

Both philosophies are valid, depending on goals and constraints.

---

## Choosing the Right Approach

A useful question is not *“Which chatbot is better?”* but:

> *How much responsibility for behaviour and content handling are we willing to hand to a third-party service?*

For some sites, a SaaS chatbot is the right answer.

For others, a site-integrated approach like ChatAI provides the needed ownership and flexibility.

---

## Summary

ChatAI differs from many website chatbots not because it uses a different AI model, but because it places responsibility and decision-making inside the site itself.

Understanding these differences helps ensure the chatbot approach matches the needs of the site and its users.

---

## Further Information

- [The Anatomy of a Website Chatbot](anatomy-of-a-website-chatbot.md)
- [Prompt Injection and Site Security](prompt-injection-and-site-security.md)
