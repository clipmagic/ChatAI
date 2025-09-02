/* Prefer HTML lane, safe text fallback; no double-render */
let chatai = {
    connectPost: async (data, url) => {
        const body = JSON.stringify(data);
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body });
        const raw = await res.text();
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${raw.slice(0,120)}`);
        let json; try { json = raw ? JSON.parse(raw) : {}; } catch { throw new Error('Invalid JSON from server'); }
        return json;
    },
    appendMessage: (role, obj) => {
        const messages = document.getElementById('chatbot-messages');
        const msg = document.createElement('div');
        msg.className = 'chatbot-msg ' + role;
        if (obj?.error?.warning) msg.classList.add('chatbot-warning');

        let text;
        if (typeof obj === 'string' && role === 'user') text = obj;
        else if (obj?.error?.msg)                      text = obj.error.msg;
        else if (obj?.reply?.msg)                      text = obj.reply.msg; // already plain text
        else if (typeof obj === 'string')              text = obj;
        else                                           text = '…';

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
    },
    go: () => {
        document.getElementById('chatbot-toggle').addEventListener('click', () => {
            document.getElementById('chatbot-dialog').showModal();
        });
        const submit = document.getElementById('chatbot-form');
        submit.addEventListener('submit', async function(e) {
            e.preventDefault();
            const input = document.getElementById('chatbot-input');
            const message = input.value.trim();
            if (!message) return;
            chatai.appendMessage('user', message);
            input.value = '';
            try {
                const url = '/chatai-api/';
                const data = { msg: message, ln: input.dataset.ln };
                const res = await chatai.connectPost(data, url);
console.log(res)
                if (res) {
                    if (res.html) {
                        chatai.appendHTML('bot', res.html, res.name);               // preferred path
                    } else if ((res.error && res.error.msg) || (res.reply && res.reply.msg)) {
                        chatai.appendMessage('bot', res);                 // fallback/error lane
                    } else {
                        chatai.appendMessage('bot', 'Sorry, no response');
                    }
                    if (res.stop) document.querySelector('.chatbot-form')?.remove();
                } else {
                    chatai.appendMessage('bot', 'Sorry, no response');
                }
            } catch (err) {
                console.error(err);
                chatai.appendMessage('bot', 'Error contacting server.');
            }
        });
    }
};
chatai.go();
