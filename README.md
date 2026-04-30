# ChatAI

ChatAI is a ProcessWire chatbot module. It keeps chatbot behaviour inside the site:

- prompt construction
- blacklist and local reply handling
- RAG context selection
- message limits
- observability logging
- frontend widget responses

Chat generation uses models configured in AgentTools. Configure provider credentials, endpoints, and model choices in AgentTools, then select the AgentTools model in ChatAI module settings.

## Requirements

- ProcessWire 3.0.201 or newer
- PHP 8.0 or newer
- AgentTools 7 or newer
- TextformatterEntities
- TextformatterNewlineBR

## Documentation

Start with the documentation index:

- [Documentation Home](docs/README.md)

### Admin Guides

- [1. Introduction](docs/admin-guide/introduction.md)
- [2. How ChatAI Works (High-Level Overview)](docs/admin-guide/how-chatai-works.md)
- [3. Installation](docs/admin-guide/installation.md)
- [4. Admin Configuration Overview](docs/admin-guide/admin-configuration.md)
- [5. ChatAI Module Configuration](docs/admin-guide/chatai-config.md)
- [6. ChatAI Behaviour Configuration](docs/admin-guide/processchatai-config.md)
- [7. Frontend Integration](docs/admin-guide/frontend-integration.md)
- [8. Content and RAG Management](docs/admin-guide/content-and-rag-management.md)
- [9. ChatAI Multilanguage Support](docs/admin-guide/chatai-multilanguage-support.md)
- [10. Developer Extension Points and Hooks](docs/admin-guide/developer-extension-points.md)

### Guides

- [The Anatomy of a Website Chatbot](docs/guides/anatomy-of-a-website-chatbot.md)
- [How ChatAI Differs from Other Website Chatbots](docs/guides/how-chatai-differs-from-other-website-chatbots.md)
- [Prompt Injection and Site Security](docs/guides/prompt-injection-and-site-security.md)
- [Understanding Tokens and Costs](docs/guides/understanding-tokens-and-costs.md)

### Glossary

- [ChatAI Glossary](docs/glossary.md)

## License

This module is released under the MIT License.

It integrates with third-party AI services, and all AI-generated output is provided “as is”.  
Use of this module and any generated content is entirely at your own risk.

See LICENSE.md for full terms.
