### Developer documentation
## Accessibility & Call-to-Action (CTA) Guidelines

### Why
ProcessChatAI often drives bookings and enquiries. It should stay accessible (WCAG 2.2 AA) while offering frictionless next steps.

### Accessibility checklist
- Keyboard: all controls operable; visible focus; logical tab order.
- Screen readers: chat log is `aria-live="polite"`; messages labelled (“Assistant says…”, “You said…”); input has a `<label>`; send button has an accessible name.
- Errors: announced to screen readers and shown inline in plain language.
- Contrast & motion: meet WCAG ratios; honour `prefers-reduced-motion`.
- Responsive: reflow to 320px without horizontal scroll; touch targets ≈24px min.
- Timing: warn before any session or booking timeout; allow extend.
- Links: descriptive text (“Book an appointment online”), not “click here”. If opening a new tab, say so in the label.

### CTA policy
Avoid appending “Book now” to every reply. Use contextual CTAs instead:
- Show after value is delivered or when user intent is clear (availability, pricing, location).
- Frequency cap: once per conversation (or once every N turns).
- Page-aware: suppress inside booking flows and on error messages.
- Language-aware: render in the user’s language.
- Use accessible wording and indicate new tabs in the label.

### Hooks (no core changes required)
Sites can customise behaviour via hookable methods:

- `beforeSend(array $request): array`  
  Mutate the outbound API request (headers, system prompts, tenant tags).
- `afterSend(array $rawResponse): array`  
  Inspect/mutate the vendor response before normalisation.
- `composeCTA(array $ctx): ?string`  
  Return an HTML fragment for a CTA, or `null`.
- `afterResponse(array $payload): array`  
  Last-mile tweaks before the JSON leaves the server.

Example (in `/site/ready.php`):

```php
$wire->addHookAfter('ProcessChatAI::composeCTA', function(HookEvent $e) {
    $ctx = $e->arguments(0);
    $session = wire('session');
    if($session->get('ctaShown')) return;
    if(!empty($ctx['isBookingPage'])) return;

    $label = ($ctx['lang_id'] === 2) ? 'Reservar turno en línea' : 'Book an appointment online';
    $session->set('ctaShown', true);
    $e->return = "<p><a href='/book' target='_blank' rel='noopener'>${label} (opens in a new tab)</a></p>";
} 
```
---
## License

This module is released under the MIT License.

It integrates with third-party AI services, and all AI-generated output is provided “as is”.  
Use of this module and any generated content is entirely at your own risk.

See LICENSE.md for full terms.