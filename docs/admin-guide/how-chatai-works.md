## 2. How ChatAI Works (High-Level Overview)

This section explains how ChatAI operates at a conceptual level. It is intended to provide orientation, not implementation detail.

It outlines why certain configuration options exist and how the pieces fit together.

---

### Core Concepts

At a high level, ChatAI is a **server-mediated AI assistant** that sits between:

- a user interacting with your site,
- your ProcessWire content and configuration,
- and the configured model provider.

ChatAI does not expose the AI model directly to the browser.  
All requests are handled server-side and are shaped, limited, and contextualised before a response is returned.

Key principles:

- **Context matters**  
  ChatAI responses are generated in the context of the current page, site, and user.

- **Control stays in ProcessWire**  
  Behaviour is defined by admin configuration and hooks, not by the AI model.

- **Safe defaults**  
  Features are opt-in where appropriate, and limits exist to prevent runaway usage.

---

### Request and Response Flow

A typical ChatAI interaction follows this sequence:

1. A user submits a message via a frontend interface.
2. The request is sent to the server and handled by ChatAI.
3. ChatAI gathers relevant context, such as:
    - page information
    - site configuration
    - selected site content defined in the ChatAI configuration
4. The request is evaluated against limits and safeguards.
5. A prompt/request is constructed and sent to the selected model.
6. The model returns a response.
7. ChatAI processes the response before returning it to the frontend.

At each stage, ChatAI can:
- block or modify requests,
- limit output,
- log events,
- or invoke hooks for custom behaviour.

The AI model never has direct access to your site, database, or users.

---

### Usage and Cost Controls

ChatAI is designed to manage usage deliberately and predictably.

It actively considers:
- the size of requests sent to the AI,
- the length of responses returned,
- and the limits defined in configuration.

Rather than sending every request without constraint, ChatAI can:
- decline requests that exceed configured limits,
- shorten responses when necessary,
- and provide clear feedback when limits are reached.

These controls help to:
- manage model API usage and costs,
- maintain consistent response quality,
- and avoid degraded performance under load.

Limits are treated as a normal part of operation, not as errors.

---

### Admin Control and Safety Defaults

ChatAI is designed around **explicit admin control**.

Key behaviours are governed by configuration rather than implicit assumptions, including:

- where ChatAI is available,
- how much it can say,
- how often it can be used,
- and what content it can reference.

Defaults are intentionally conservative and are meant to:
- prevent accidental overuse,
- reduce surprises,
- and provide predictable behaviour out of the box.

Advanced behaviour can be enabled gradually as confidence increases.

---

This overview is intentionally high-level.  
Later sections cover configuration, frontend integration, and developer extension points in more detail.
