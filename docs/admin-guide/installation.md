## 3. Installation

This section describes how to install ChatAI using the standard ProcessWire module workflow.

---

### Requirements and Assumptions

Before installing ChatAI, ensure the following:

- A working ProcessWire installation.
- An OpenAI API key, available before first use.  
  API keys can be created via your OpenAI account:  
  https://platform.openai.com/api-keys

ChatAI uses standard ProcessWire configuration and installation mechanisms.  
If your environment is incompatible, you will be notified during installation.

ChatAI assumes:
- you are familiar with installing ProcessWire modules,
- you have access to the admin interface,
- and you are comfortable reviewing module configuration after install.

No frontend integration is required at install time.

---

### Installing the Module

ChatAI is installed using the standard ProcessWire module workflow.

1. Download or clone the ChatAI repository from GitHub at https://github.com/clipmagic/ChatAI
2. Place the module files into your site’s modules directory: /site/modules/ChatAI/
3. Log in to the ProcessWire admin.
4. Navigate to: Modules → Refresh
5. Locate **ChatAI** in the module list and click **Install**.

During installation, ChatAI will:
- register required database tables,
- initialise default configuration values,
- and prepare internal services.

If installation fails, no partial configuration should remain.

---

### First-Time Setup Checklist

After installation, review the ChatAI admin screens and complete the following minimum steps:

- Confirm the API provider and credentials are configured.
- Review default limits and safeguards.
- Decide whether ChatAI should be enabled site-wide or restricted.

At this stage, ChatAI can operate with default behaviour.

---

### Verifying a Successful Install

A successful installation can be confirmed by:

- ChatAI appearing in the ProcessWire admin menu.
- No errors reported during module install.
- Admin screens loading without warnings.
- Test requests returning responses when ChatAI is enabled.

If ChatAI is enabled but not visible on the frontend, this is expected until frontend integration is configured.

---

### Uninstalling Cleanly

ChatAI can be uninstalled via the standard ProcessWire module interface.

On uninstall:
- configuration data may be removed,
- database tables created by ChatAI may be dropped,
- and frontend integrations must be removed manually.

Uninstall behaviour is intentionally explicit to avoid accidental data loss.

If you intend to reinstall ChatAI later, review uninstall options carefully before proceeding.

---

Later sections cover admin configuration, frontend integration, and optional advanced features in more detail.
