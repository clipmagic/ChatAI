// client-side transcript persistence (per tab)
const CHAT_KEY = 'chatai:thread';
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
    clear() { try { sessionStorage.removeItem(CHAT_KEY); } catch {} }
};

let chatai = {
    connectPost: async (data, url) => {
        const body = JSON.stringify(data);
        const res = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json'}, body});
        const raw = await res.text();
        console.log(raw)
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${raw.slice(0, 120)}`);
        let json;
        try {
            json = raw ? JSON.parse(raw) : {};
        } catch {
            throw new Error('Invalid JSON from server');
        }
        return json;
    },

    isNearLimitWarning: (err) => {
        return !!(err && err.warning === true && ((parseInt(err.number) === 10) || parseInt(err.number) === 4));
    },


  /*  appendMessage: (role, obj) => {
        const messages = document.getElementById('chatbot-messages');
        const msg = document.createElement('div');
        msg.className = 'chatbot-msg ' + role;
        //if (obj?.error?.warning) msg.classList.add('chatbot-warning');

        // Only style as warning if this bubble is an error-only bubble
        const hasReply = (typeof obj?.reply === 'string') || (obj?.reply?.msg);
        if (!hasReply && (obj?.error?.warning || obj?.blacklisted === true)) {
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

        // Optional: show name label only when no HTML is being rendered
        if (obj?.name && !obj.html) {
            const name = document.createElement('div');
            name.className = 'chatbot-name';
            name.textContent = obj.name;
            messages.append(name);
        }
        messages.append(msg);
        messages.scrollTop = messages.scrollHeight;

        chataiStore.push({
            role,
            type: 'text',
            name: obj?.name || null,
            content: msg.textContent,
            warning: !!(obj?.error?.warning && obj?.blacklisted !== true)
        });
    },
*/

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
        messages.scrollTop = messages.scrollHeight;

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
        wrap.scrollTop = wrap.scrollHeight;

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

        document.querySelector('.chatai-reset')?.addEventListener('click', (e) => {
            e.preventDefault()
            chataiStore.clear();
            location.reload();
        });

        document.getElementById('chatbot-toggle').addEventListener('click', () => {
            document.getElementById('chatbot-dialog').showModal();
        });

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
                const data = {msg: message, ln: input.dataset.ln};

                const res = await chatai.connectPost(data, url);
                console.log(res);

                if (res) {

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
                        chatai.appendMessage('bot', res, chataiStore);
                        chatai.appendMessage('bot', { name: res.name, error: res.error }, chataiStore);
                        if (res.stop) {
                            document.querySelector('.chatbot-form')?.remove();
                            chataiStore.clear();
                        }
                        setBusy(false);
                        return;
                    }

                    // 1) Prefer HTML
                    if (typeof res.html === 'string' && res.html.trim()) {
                        chatai.appendHTML('bot', res.html, res.name);
                    }
                    // 2) Plain reply
                    else if (typeof res.reply === 'string' && res.reply.trim()) {
                        chatai.appendMessage('bot', { name: res.name, reply: res.reply });

                        // If this is the near-limit warning (#10), append another bubble
                        if (res.error && res.error.warning === true && res.error.number === 10) {
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