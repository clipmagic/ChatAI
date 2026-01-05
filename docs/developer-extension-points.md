## Developer Extension Points

ChatAI is designed to be extended using standard ProcessWire hooks.

This allows developers to adjust behaviour, inspect requests and responses, and integrate ChatAI into site-specific workflows without modifying core module files.

For general background on ProcessWire hooks, refer to the ProcessWire documentation.

---

### Available Hooks and Events

ChatAI exposes a small set of extension points that cover the full request lifecycle:

- before the request is sent to OpenAI
- immediately after a raw response is received
- just before the JSON payload is returned to the frontend widget
- when building the selector used for RAG re-indexing (ProcessChatAI)

These hooks are no-ops by default and only apply if you implement them.

---

#### Hook: `ChatAI::beforeSend(array $messages): array`

Called immediately before a request is sent to OpenAI.

Use this hook to:
- inspect or adjust the message array,
- add or remove system messages,
- enforce site-specific prompt rules,
- or inject additional context.

Return value:
- must be the modified `$messages` array.

Minimal example:

```php
$wire->addHookAfter('ChatAI::beforeSend', function(HookEvent $event) {
    $messages = $event->return;

    // Example: prepend a lightweight system instruction
    array_unshift($messages, [
        'role' => 'system',
        'content' => 'Answer using the site’s tone and terminology.'
    ]);

    $event->return = $messages;
});
