## 4. Admin Configuration Overview

This section describes where and how ChatAI is configured within the ProcessWire admin.

ChatAI configuration is split across two areas with different responsibilities:

- *what ChatAI is allowed to do*, and
- *how the chatbot behaves in practice*.

This separation follows standard ProcessWire conventions and helps prevent misconfiguration while keeping day-to-day management clear.

ChatAI is configured through two distinct admin locations:

- **Modules → ChatAI**  
  Defines global and technical settings such as AgentTools model selection, frontend file paths, permissions, and response limits.  
  These settings are typically configured once.

- **Setup → ChatAI**  
  Provides the ChatAI process interface used to control chatbot behaviour, prompts, limits, and diagnostics.  
  This area is used for ongoing operation and tuning.

Detailed configuration guidance is covered in the following sections:

- **ChatAI Module Configuration**  
  Covers all settings available under **Modules → ChatAI**.

- **ChatAI Behaviour Configuration (ProcessChatAI)**  
  Covers the Dashboard, Personalise, Prompt, and Dictionary tabs available under **Setup > ChatAI**.
