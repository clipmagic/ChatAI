# ChatAI

## 1. Introduction

This guide focuses on configuration and behaviour. A conceptual overview of how chatbot components fit together is covered separately in **How ChatAI Works**.

### What ChatAI Is

ChatAI is a **self-hosted AI assistant module for ProcessWire**.

It allows site owners and developers to add an AI-powered chatbot to their site while retaining **full control** over:

- where the chatbot appears
- what content it can access
- how it behaves
- how data is handled

ChatAI is designed to operate **within the context of a ProcessWire site**, using site content, access control, and configuration defined in the admin. It does not function as a standalone AI service and does not attempt to replace your site’s structure or logic.

At its core, ChatAI combines:

- configurable prompts that define how the assistant behaves and responds
- the ability to use relevant site content when answering questions
- built-in controls to manage usage and model API costs
- extensibility via hooks for custom behaviour

The emphasis is on **predictable behaviour, safe defaults, and transparency**, rather than automation magic.

---

### What ChatAI Is Not

ChatAI is deliberately **not** the following:

- **Not a SaaS chatbot platform**  
  ChatAI does not provide a hosted service, external dashboard, or third-party admin interface. It runs entirely within your ProcessWire installation.

- **Not a simple chatbot embed**  
  While ChatAI can use external model providers, it is not an iframe or script injection. All requests are handled server-side and governed by your configuration.

- **Not an autonomous agent**  
  ChatAI does not browse the web, take actions on behalf of users, or make decisions outside the scope you define.

- **Not a replacement for site content or support workflows**  
  ChatAI is intended to assist and guide users, not to act as a source of authority or decision-maker.

---

### Who ChatAI Is For

ChatAI is designed for:

- **Clients and site owners**  
  who want a chatbot that works within the content, structure, and rules of their own ProcessWire site.

- **Site administrators**  
  who are comfortable configuring modules and want visibility into how responses are generated and limited, with dashboard access to live usage and performance insights.

- **ProcessWire developers**  
  who want fine-grained control, extensibility, and the ability to integrate AI behaviour into existing site logic.

- **Projects where control and privacy matter**  
  including sites that prefer self-hosting, explicit behaviour, and minimal data retention.

ChatAI works best when it is configured deliberately and reviewed as part of a broader site strategy.
