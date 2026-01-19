// chatai.js - frontend widget
/*
|--------------------------------------------------------------------------
| Message limit & warning flow (OpenAI-call based)
|--------------------------------------------------------------------------
|
| Important: The message limit ONLY applies to requests that actually
| go to OpenAI. Small talk, blacklist warnings, and other local replies
| do NOT consume credits.
|
| Credit consumption happens server-side and is returned in the API
| response meta (count / remaining / stop).
|
| Priority order (highest → lowest):
|
| 1. Cutoff
|    - Occurs when OpenAI response is truncated.
|    - Always takes precedence over near-limit warnings.
|    - Uses dedicated cutoff styling.
|    - Input is removed/disabled.
|
| 2. Hard limit reached (Error 4 / 11)
|    - Triggered when remaining === 0.
|    - Displays final message and disables input.
|
| 3. Near-limit warning (Error 10)
|    - Triggered when remaining === 1.
|    - Rendered as a warning bubble BELOW the bot reply.
|    - Input remains enabled.
|
| 4. Normal reply
|    - No warnings.
|
| Frontend must NOT infer limits itself.
| It only reacts to explicit error metadata from the API.
|
|--------------------------------------------------------------------------
*/

// client-side transcript persistence (per tab)
const CHAT_KEY = 'chatai:thread';
const CHAT_API_URL = '/chatai-api/';
const chataiStore = {
    load() {
        try { return JSON.parse(sessionStorage.getItem(CHAT_KEY) || '[]'); } catch { return []; }
    },
    save(arr) {
        try { sessionStorage.setItem(CHAT_KEY, JSON.stringify(arr)); } catch {}
    },
    push(rec) {
        const arr = chataiStore.load();
        arr.push(rec);
        chataiStore.save(arr);
    },

    /*
    |--------------------------------------------------------------------------
    | Reset chat
    |--------------------------------------------------------------------------
    |
    | Clears:
    | - Frontend sessionStorage (conversation history, UI state)
    | - PHP session data via API (message counter, strikes, etc.)
    |
    | After reset, the next message MUST behave as a fresh session:
    | no residual warnings, no premature limits.
    |
    |--------------------------------------------------------------------------
    */

    clear() {
        try { sessionStorage.removeItem(CHAT_KEY); } catch {}
    }
};

let chatai = {
    connectPost: async (data, url) => {
        const body = JSON.stringify(data);
        const res = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json'}, body});
        const raw = await res.text();
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${raw.slice(0, 120)}`);
        let json;
        try {
            json = raw ? JSON.parse(raw) : {};
        } catch {
            throw new Error('Invalid JSON from server');
        }
        return json;
    },


    /*
     * Detects a "near limit" warning (Error 10).
     *
     * This is intentionally separate from hard-stop errors.
     * A warning bubble should be rendered without disabling input.
     *
     * Do NOT treat warning === true as a stop condition.
     */
        isNearLimitWarning: (err) => {
        if (!err) return false;
        const n = parseInt(err.number, 10);
        return err.warning === true && n === 10;
    },
    appendMessage: (role, obj) => {
        const messages = document.getElementById('chatbot-messages');
        const msg = document.createElement('div');
        msg.className = 'chatbot-msg ' + role;

        const hasReply = (typeof obj?.reply === 'string') || (obj?.reply?.msg);

        // Detect cutoff (error number 20, not marked as warning)
        const isCutoff = !!(obj?.error && obj.error.number === 20);

        if (isCutoff) {
            msg.classList.add('chatbot-cutoff');
        } else if (!hasReply && (obj?.error?.warning || obj?.blacklisted === true)) {
            // Only style as warning if this bubble is an error-only bubble
            msg.classList.add('chatbot-warning');
        }

        let text;
        if (typeof obj === 'string' && role === 'user') text = obj;
        else if (typeof obj?.reply === 'string') text = obj.reply;     // reply first
        else if (obj?.reply?.msg) text = obj.reply.msg; // legacy
        else if (obj?.error?.msg) text = obj.error.msg; // error last
        else if (typeof obj === 'string') text = obj;
        else text = '…';

        msg.textContent = text; // safe default (no HTML)

        if (obj?.name && !obj.html) {
            const name = document.createElement('div');
            name.className = 'chatbot-name';
            name.textContent = obj.name;
            messages.append(name);
        }
        messages.append(msg);
        //messages.scrollTop = messages.scrollHeight;
        const wrapper = document.querySelector('.chatbot-msg-wrapper');
        if (wrapper) {
            const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const behavior = prefersReduced ? 'auto' : 'smooth';

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    wrapper.scrollTo({ top: wrapper.scrollHeight, behavior });
                });
            });
        }


        chataiStore.push({
            role,
            type: 'text',
            name: obj?.name || null,
            content: msg.textContent,
            warning: !!(obj?.error?.warning && obj?.blacklisted !== true),
            cutoff: isCutoff
        });
    },

    appendHTML: (role, html, name) => {
        const wrap = document.getElementById('chatbot-messages');
        if (name) {
            const label = document.createElement('div');
            label.className = 'chatbot-name';
            label.textContent = name;
            wrap.appendChild(label);
        }

        const msg = document.createElement('div');
        msg.className = 'chatbot-msg ' + role;
        msg.innerHTML = html; // already sanitized server-side
        wrap.appendChild(msg);
        //wrap.scrollTop = wrap.scrollHeight;

        const wrapper = document.querySelector('.chatbot-msg-wrapper');
        if (wrapper) {
            const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const behavior = prefersReduced ? 'auto' : 'smooth';

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    wrapper.scrollTo({ top: wrapper.scrollHeight, behavior });
                });
            });
        }


        chataiStore.push({role, type: 'html', name: name || null, content: html});

    },

    go: () => {

        // Replay previous thread (if any)
        try {
            const prior = chataiStore.load();
            for (const rec of prior) {
                if (rec.name) {
                    const wrap = document.getElementById('chatbot-messages');
                    const label = document.createElement('div');
                    label.className = 'chatbot-name';
                    label.textContent = rec.name;
                    wrap.appendChild(label);
                }
                if (rec.type === 'html') chatai.appendHTML(rec.role, rec.content, null);
                else chatai.appendMessage(rec.role, rec.content);
            }
        } catch {
        }

        document.querySelector('.chatai-reset')?.addEventListener('click', async (e) => {
            e.preventDefault();

            try {
                // Ensure server-side session is cleared too
                const url = (window.CHATAI_API_URL || '/chatai-api/') + '?action=reset';
                await fetch(url, { method: 'POST', credentials: 'same-origin' });
            } catch (err) {
                console.warn('ChatAI: reset API failed', err);
                // Still proceed with local reset
            }

            chataiStore.clear();
            location.reload();
        });

        const dlg = document.getElementById('chatbot-dialog');
        const toggle = document.getElementById('chatbot-toggle');

        if (toggle && dlg && typeof dlg.showModal === 'function') {
            toggle.addEventListener('click', () => dlg.showModal());
        }

        // Click outside dialog to close (popover-style)
        if (dlg) {
            dlg.addEventListener('click', (e) => {
                if (!(dlg.open || dlg.hasAttribute('open'))) return;

                const rect = dlg.getBoundingClientRect();
                const clickedOutside =
                    e.clientX < rect.left ||
                    e.clientX > rect.right ||
                    e.clientY < rect.top ||
                    e.clientY > rect.bottom;

                if (clickedOutside) {
                    if (typeof dlg.close === 'function') dlg.close();
                    else dlg.removeAttribute('open');
                }
            });
        }


        const form = document.getElementById('chatbot-form');
        const status = document.getElementById('chatbot-status');
        const statusVisible = status?.querySelector('.chatbot-status-visible') || null;
        const statusSr = status?.querySelector('.chatbot-status-sr') || null;

        function setBusy(isBusy) {
            const input = document.getElementById('chatbot-input');
            const submitBtn = document.querySelector('.chatbot-submit');
            const status = document.getElementById('chatbot-status');
            const clearWrapper = document.querySelector('.chatbot-clear');

            if (!status) {
                // Fallback: just toggle button and input if status element is missing
                if (submitBtn) submitBtn.disabled = !!isBusy;
                if (input) input.disabled = !!isBusy;
                // Optional: still hide reset when busy if present
                if (clearWrapper) clearWrapper.hidden = !!isBusy;
                return;
            }

            if (isBusy) {
                form.dataset.busy = '1';
                if (input) input.disabled = true;
                if (submitBtn) submitBtn.disabled = true;

                status.hidden = false;
                status.setAttribute('aria-busy', 'true');

                if (clearWrapper) clearWrapper.hidden = true;
            } else {
                form.dataset.busy = '0';
                if (input) input.disabled = false;
                if (submitBtn) submitBtn.disabled = false;

                status.hidden = true;
                status.setAttribute('aria-busy', 'false'); // or status.removeAttribute('aria-busy');

                if (clearWrapper) clearWrapper.hidden = false;
            }
        }

        function selectResponseView(raw) {
            // Defensive defaults
            const view = {
                status: raw?.status || '',
                stop: !!raw?.stop,
                // Main content (exactly one of these should be used by the renderer)
                contentType: 'text',   // 'text' | 'html'
                content: '',
                // Optional CTA
                ctaHtml: '',
                // Optional error message (for a single consistent error path)
                errorText: ''
            };

            // If server provided a structured error object, prefer its msg for display
            const errMsg = raw?.error && typeof raw.error.msg === 'string' ? raw.error.msg.trim() : '';
            if (errMsg) {
                view.errorText = errMsg;
            }

            // Main content selection: prefer HTML when present
            const html = typeof raw?.html === 'string' ? raw.html.trim() : '';
            const text = typeof raw?.reply === 'string' ? raw.reply.trim() : '';

            if (html) {
                view.contentType = 'html';
                view.content = html;
            } else if (text) {
                view.contentType = 'text';
                view.content = text;
            } else if (view.errorText) {
                // Fall back to error text if no reply/html was provided
                view.contentType = 'text';
                view.content = view.errorText;
            }

            // CTA selection (optional)
            const cta = typeof raw?.cta === 'string' ? raw.cta.trim() : '';
            if (cta) {
                view.ctaHtml = cta;
            }

            return view;
        }


        // Ensure initial state
        if (status) {
            status.hidden = true;
            status.setAttribute('aria-busy', 'false');
        }
        form.dataset.busy = '0';

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            // Prevent double submit while busy
            if (form.dataset.busy === '1') {
                return;
            }

            const input = document.getElementById('chatbot-input');
            const submitBtn = document.querySelector('.chatbot-submit')

            const message = input.value.trim();
            if (!message) return;

            chatai.appendMessage('user', message);
            input.value = '';

            // At this point we know we are sending a real request
            setBusy(true);

            try {
                const url = '/chatai-api/';
                const data = {
                    msg: message,
                    ln: input.dataset.ln,
                    pid: document.getElementById('chatbot-cta').dataset.pid,
                    url: window.location.href
                };

                const res = await chatai.connectPost(data, url);
                console.log(res);

                if (res) {

                    // Populate CTA div if the html exists from the hook
                    if(res.cta && typeof res.cta === 'string' && res.cta.trim()) {
                        document.getElementById('chatbot-cta').innerHTML = res.cta.trim()
                    }


                    // 0) Cutoff: partial answer + soft notice, then disable form
                    if (res.cutoff === true) {

                        // Partial answer first, same as any bot reply
                        if (typeof res.html === 'string' && res.html.trim()) {
                            chatai.appendHTML('bot', res.html, res.name);
                        } else if (typeof res.reply === 'string' && res.reply.trim()) {
                            chatai.appendMessage('bot', { name: res.name, reply: res.reply });
                        }

                        // Soft cutoff notice (error 20)
                        const cutoffText =
                            res.error && typeof res.error === 'object' && res.error.msg
                                ? res.error.msg
                                : 'This chat has reached its limit. Please start a new chat to continue.';

                        const cutoffError = (res.error && typeof res.error === 'object')
                            ? res.error
                            : { msg: cutoffText, number: 20 };

                        chatai.appendMessage('bot', { name: res.name, error: cutoffError });

                        // Disable further input but keep history and Reset
                        document.querySelector('.chatbot-form')?.remove();
                        setBusy(false);
                        return;
                    }
                    // Lockout still takes priority
                    if (res.stop === true) {
                        // Show any final message (reply/html) plus the stop error bubble, then disable input.
                        if (typeof res.html === 'string' && res.html.trim()) {
                            chatai.appendHTML('bot', res.html, res.name);
                        } else if (typeof res.reply === 'string' && res.reply.trim()) {
                            chatai.appendMessage('bot', { name: res.name, reply: res.reply });
                        }
                        if (res.error) {
                            chatai.appendMessage('bot', { name: res.name, error: res.error });
                        }
                        document.querySelector('.chatbot-form')?.remove();
                        setBusy(false);
                        return;
                    }

                    // 1) Prefer HTML
                    if (typeof res.html === 'string' && res.html.trim()) {
                        chatai.appendHTML('bot', res.html, res.name);

                        // Near-limit warning (#10): render as a separate warning bubble (do not disable input)
                        if (chatai.isNearLimitWarning(res.error)) {
                            chatai.appendMessage('bot', { name: res.name, error: res.error });
                        }
                    }
                    // 2) Plain reply
                    else if (typeof res.reply === 'string' && res.reply.trim()) {
                        chatai.appendMessage('bot', { name: res.name, reply: res.reply });

                        // Near-limit warning (#10): render as a separate warning bubble (do not disable input)

                        if (chatai.isNearLimitWarning(res.error)) {
                            chatai.appendMessage('bot', { name: res.name, error: res.error });
                        }
                    }
                    // 3) Fallback: structured error with msg
                    else if (res.error && (typeof res.error === 'string' || res.error.msg)) {
                        chatai.appendMessage('bot', { name: res.name, error: res.error });
                    } else {
                        chatai.appendMessage('bot', 'Sorry, no response');
                    }

                } else {
                    chatai.appendMessage('bot', 'Sorry, no response');
                }

                setBusy(false);


            } catch (err) {
                console.error(err);
                chatai.appendMessage('bot', 'Error contacting server.');
                setBusy(false);
            }
        });
    },
};
chatai.go();
