###ChatAI and ProcessChatAI Change Log
##0.0.4Alpha
- Require AgentTools 7 or newer for chat model configuration.
- Add AgentTools-backed chat model client for OpenAI-compatible and Anthropic models.
- Replace ChatAI's OpenAI model refresh/configuration with an AgentTools model selector.
- Keep ChatAI prompt, RAG context, hooks, message limits, observability logging, and frontend response handling inside ChatAI.
- Keep the existing OpenAI RAG embedding key setting for the current RAG indexer; embedding provider changes are deferred.
- Update module metadata and summaries to describe AgentTools-backed model configuration.
- Add first-pass configurable RAG embedding settings for model, endpoint, and API key, with defaults matching the existing OpenAI embedding path.
- Move the embedding request behind a hookable `ChatAI::embedText()` method so advanced/site-specific integrations can override the built-in OpenAI-compatible behavior.
- Warn admins that changing embedding settings may make existing RAG vectors stale and requires a full reindex.

##0.0.3Alpha
- Add Classifier
- Update prompt settings

##0.0.2Alpha
- Add RAG class
- Add vector db
- Add the ability to add/remove pages from the vector Db

##0.0.1Alpha
- Initial Proof of Concept
- Create structure and outline of modules
- - ChatAI.module is a configurable module
- - ProcessChatAI is an admin module with a page under Settings
- Create frontend chatbot widget
