## Developer Extension Points

ChatAI is designed to be extended using standard ProcessWire hooks.

This allows developers to adjust behaviour, inspect requests and responses, and integrate ChatAI into site-specific workflows without modifying core module files.

For general background on ProcessWire hooks, refer to the ProcessWire documentation.

---

### Available Hooks and Events

ChatAI exposes a small set of extension points that cover the full request lifecycle:

- before the request is sent to the AI model
- immediately after a raw response is received
- just before the JSON payload is returned to the frontend widget
- when providing an embedding vector for indexing
- when building the selector used for RAG re-indexing (ProcessChatAI)
- and, where needed, at the AgentTools provider-request layer before a request is dispatched

These hooks are no-ops by default and only apply if you implement them.

---

### Hook: `ChatAI::beforeSend(array $messages): array`

Called immediately before a request is sent to the AI model.

Use this hook to:
- inspect or adjust the message array,
- add or remove system messages,
- enforce site-specific prompt rules,
- or inject additional context.

**Return value**
- Must return the modified `$messages` array.

**Minimal example**

```php
$wire->addHookAfter('ChatAI::beforeSend', function (HookEvent $event) {
    $messages = $event->return;

    // Example: prepend a lightweight system instruction
    array_unshift($messages, [
        'role' => 'system',
        'content' => 'Answer using the site’s tone and terminology.'
    ]);

    $event->return = $messages;
});
```

**Notes**
- Keep changes minimal and predictable.
- Do not add sensitive data unless you fully understand how it will be transmitted and logged.

---

### Hook: `ChatAI::afterSend(array|object $rawResponse): array|object`

Called immediately after the raw vendor response is received, before ChatAI normalises it into the internal response structure.

Use this hook to:
- inspect the vendor response for diagnostics,
- capture metadata (for example usage or status fields),
- or adjust edge cases before normalisation.

**Return value**
- Must return the modified `$rawResponse` in the same general type (array or object) that was received.

**Minimal example**

```php
$wire->addHookAfter('ChatAI::afterSend', function (HookEvent $event) {
    $raw = $event->return;

    // Example: inspect or log raw response data
    // $this->wire('log')->save('chatai', print_r($raw, true));

    $event->return = $raw;
});
```

**Notes**
- This hook is intentionally low-level.
- Prefer `afterResponse()` for UI-facing changes.

---

### Hook: `ChatAI::afterResponse(array $payload): array`

Called after ChatAI has produced its final response payload, immediately before it is returned as JSON to the frontend widget.

Use this hook to:
- adjust final response text,
- add or modify a CTA block,
- add flags or metadata for the widget,
- or implement site-specific presentation logic.

**Return value**
- Must return the modified `$payload` array.

**Minimal example**

```php
$wire->addHookAfter('ChatAI::afterResponse', function (HookEvent $event) {
    $payload = $event->return;

    // Example: add a simple CTA block
    $payload['cta'] = "<p><a href='/contact/'>Contact us</a></p>";

    $event->return = $payload;
});
```

**Notes**
- Keep payload changes backwards-compatible with the default widget.
- If you add custom fields, ensure your widget JavaScript handles them safely.

---

### Hook: `ChatAI::embedText(string $text): array`

Called when ChatAI needs an embedding vector for text indexing.

By default, ChatAI uses the selected AgentTools embedding entry and sends the request itself. This hook allows developers to replace that embedding path entirely without modifying RAG or the indexer.

Use this hook to:
- route embeddings to another provider,
- apply a custom embedding payload/response format,
- or integrate a site-specific embedding service.

**Return value**
- Must return the embedding vector as a numeric array.

**Minimal example**

```php
$wire->addHookAfter('ChatAI::embedText', function (HookEvent $event) {
    $text = $event->arguments(0);

    // Example only: replace with your own embedding service call
    $event->return = [0.123, 0.456, 0.789];
});
```

**Notes**
- The returned vector should match the embedding model used for the rest of the index.
- If you change embedding behavior materially, a full reindex is required.

---

### Hook: `ProcessChatAI::buildReindexSelector(string $selector): string`

Called when ProcessChatAI builds the selector used for RAG re-indexing.

Use this hook to:
- constrain the index scope for a specific site,
- exclude templates or branches,
- or apply site-specific indexing rules that are not appropriate as defaults.

**Return value**
- Must return the modified selector string.

**Minimal example**

```php
$wire->addHookAfter('ProcessChatAI::buildReindexSelector', function (HookEvent $event) {
    $selector = $event->return;

    // Example: exclude a branch from indexing
    $selector .= ", has_parent!=/private/";

    $event->return = $selector;
});
```

**Notes**
- Prefer narrowing scope via the Dashboard controls first.
- Use this hook only when you have stable, site-wide rules that should always apply.

---

### Hook: `ChatAI::isEligibleForChat(Page $page): bool`

Called during RAG page processing after the normal template/config indexability checks have passed.

This hook allows developers to make a final site-specific decision about whether an otherwise eligible page should be indexed.

By default, this hook does not exclude any pages from indexing.

Use this hook to:
- prevent indexing on specific pages,
- exclude pages based on field values,
- or apply site-specific rules that cannot be expressed through template selection alone.

**Return value**
- Must return `true` to allow indexing to proceed.
- Return `false` to treat the page as not indexable.

**Minimal example**

```php
$wire->addHookAfter('ChatAI::isEligibleForChat', function (HookEvent $event) {
    $page = $event->arguments(0);

    // Example: exclude pages with "Barney" in the title from indexing
    $event->return = !str_contains($page->title, 'Barney');
});
```

**Notes**
- This hook acts as a final exclusion filter after normal page indexability checks pass.
- It does not bypass blacklist checks, limits, or other frontend safeguards.
- Prefer configuration-based content guidance where possible.

---

### AgentTools Request Hook: `AgentToolsEngineer::sendProviderRequest(AgentToolsRequest $request)`

ChatAI now uses AgentTools as the model and credential layer for chat requests. If you need to inspect or adjust the provider request itself, the primary hook point is on the AgentTools side rather than in ChatAI.

Use this hook to:
- inspect the selected provider, endpoint, model, or options,
- adjust provider-specific request options,
- or apply experimental request tweaks before the provider call is sent.

**Hook style**
- Use this as a `before` hook and mutate the `AgentToolsRequest` object in place.

**Minimal example**

```php
$wire->addHookBefore('AgentToolsEngineer::sendProviderRequest', function (HookEvent $event) {
    $request = $event->arguments(0);

    if($request->provider === 'openai') {
        $request->options['timeout'] = 45;
    }
});
```

**Notes**
- This hook belongs to AgentTools, not ChatAI, but it is relevant for ChatAI integrations because ChatAI delegates model transport there.
- Use this layer for provider-request mutation; use ChatAI hooks for message, payload, and frontend-response concerns.
