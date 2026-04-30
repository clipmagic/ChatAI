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

- [Introduction](docs/admin-guide/introduction.md)
- [Installation](docs/admin-guide/installation.md)
- [Admin Configuration](docs/admin-guide/admin-configuration.md)
- [ChatAI Config](docs/admin-guide/chatai-config.md)
- [ProcessChatAI Config](docs/admin-guide/processchatai-config.md)
- [Frontend Integration](docs/admin-guide/frontend-integration.md)
- [Content and RAG Management](docs/admin-guide/content-and-rag-management.md)
- [ChatAI Multilanguage Support](docs/admin-guide/chatai-multilanguage-support.md)
- [Developer Extension Points](docs/admin-guide/developer-extension-points.md)
- [How ChatAI Works](docs/admin-guide/how-chatai-works.md)

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
