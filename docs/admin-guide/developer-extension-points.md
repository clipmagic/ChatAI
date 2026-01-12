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

*Return value*:
- must be the modified `$messages` array.

*Minimal example*:

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
```

*Notes:*

- Keep changes minimal and predictable.
- Do not add sensitive data to the message array unless you fully understand how it will be transmitted and logged.

---

### Hook: `ChatAI::afterSend(array|object $rawResponse): array|object`
Called immediately after the raw vendor response is received, before ChatAI normalises it into the internal response structure.

Use this hook to:

- inspect the vendor response for diagnostics,

- capture metadata (for example usage or status fields),
- or adjust edge cases before normalisation.

*Return value:*

must be the modified $rawResponse in the same general type (array or object) that was received.

*Minimal example:*

```
$wire->addHookAfter('ChatAI::afterSend', function(HookEvent $event) {
    $raw = $event->return;

    // Example: log vendor status (structure will vary by vendor response)
    // $this->wire('log')->save('chatai', print_r($raw, true));

    $event->return = $raw;
});
```
*Notes:*

- This hook is intentionally low-level.
- Prefer afterResponse() for UI-facing changes.

---

### Hook: `ChatAI::afterResponse(array $payload): array`

Called after ChatAI has produced its final response payload, immediately before it is returned as JSON to the frontend widget.

*Use this hook to:*

- adjust final response text,
- add or modify a CTA block,
- add flags or metadata for the widget,
- or implement site-specific presentation logic.

*Return value:*

- must be the modified $payload array.

*Minimal example:*
```
$wire->addHookAfter('ChatAI::afterResponse', function(HookEvent $event) {
    $payload = $event->return;

    // Example: add a simple CTA block
    $payload['cta'] = "<p><a href='/contact/'>Contact us</a></p>";

    $event->return = $payload;
});
```
*Notes:*

- Keep payload changes backwards-compatible with the default widget.
- If you add custom fields, ensure your widget JavaScript handles them safely.

---

### Hook: `ProcessChatAI::buildReindexSelector(string $selector): string`

Called when ProcessChatAI builds the selector used for RAG re-indexing.

*Use this hook to:*

- constrain the index scope for a specific site,
- exclude templates or branches,
- or apply site-specific indexing rules that are not appropriate as defaults.

*Return value:*

- must be the modified selector string.

*Minimal example:*
```$wire->addHookAfter('ProcessChatAI::buildReindexSelector', function(HookEvent $event) {
$selector = $event->return;

    // Example: exclude a branch from indexing
    $selector .= ", has_parent!=/private/";

    $event->return = $selector;
});
```
*Notes:*

- Prefer narrowing scope via the Dashboard controls first.
- Use this hook only when you have stable, site-wide rules that should always apply.