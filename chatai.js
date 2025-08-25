/*
 * Copyright (c) 2025.
 * Clip Magic - Prue Rowland
 * Web: www.clipmagic.com.au
 * Email: admin@clipmagic.com.au
 *
 * ProcessWire 3.x
 * Copyright (C) 2014 by R
 * Licensed under GNU/GPL
 *
 * https://processwire.com
 */

let chatai = {

    connectPost: async (data, url) => {
        const body = JSON.stringify(data);
        console.log('Request body:', body);

        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body
        });

        // Read the body ONCE
        const raw = await res.text();

        if (!res.ok) {
            console.error(`HTTP ${res.status}:`, raw.slice(0, 300));
            throw new Error(`HTTP ${res.status}: ${raw.slice(0, 120)}`);
        }

        let json;
        try {
            json = raw ? JSON.parse(raw) : {};
        } catch (e) {
            console.error('Invalid JSON. Raw response:', raw.slice(0, 300));
            throw new Error(`Invalid JSON from server`);
        }

        return json;
    },

    appendMessage: (role, obj) => {
        console.log('role: ', role)
        console.log('obj: ', obj)
        const msg = document.createElement('div');
        msg.className = 'chatbot-msg ' + role;

        if(obj.error && obj.error.warning)
            msg.className = msg.className + ' chatbot-warning';

        if(typeof obj === "string" && role === 'user') {
            // it's from the user
             msg.textContent = obj
        } else {
             msg.innerHTML = obj.error && obj.error.msg ? obj.error.msg : obj.reply.msg
        }

        const messages = document.getElementById('chatbot-messages');

        if(obj.name) {
            const name = document.createElement('div');
            name.className = 'chatbot-name';
            name.textContent = obj.name
            messages.append(name)
        }
        messages.appendChild(msg);
        messages.scrollTop = document.getElementById('chatbot-messages').scrollHeight;
    },

    go: () => {
        document.getElementById('chatbot-toggle').addEventListener('click', () => {document.getElementById('chatbot-dialog').showModal();
        });
        const submit = document.getElementById('chatbot-form')
        submit.addEventListener('submit', async function(e) {
            e.preventDefault();
            const input = document.getElementById('chatbot-input');
            const message = input.value.trim();
            if (!message) return;
                chatai.appendMessage('user', message);
            input.value = '';
            try {

                let url = '/chatai-api/'
                let data = {
                    msg: message
                }
                const res = await chatai.connectPost(data, url)
                // console.log('message before post: ', message)
                // console.log('res: ', res)

                if(res) {
                    if(res.error && res.error.warning || res.reply) {
                        chatai.appendMessage('bot', res);
                    }
                    // if(res.reply)
                    //     chatai.appendMessage('bot', res.reply);
                    if (res.html)
                        chatai.appendHTML('bot', res.html)
                    if(res.stop) {
                        document.querySelector('.chatbot-form').remove()
                    }
                } else {
                    'Sorry, no response'
                }

            } catch (err) {
                console.log(err)
                chatai.appendMessage('bot', 'Error contacting server.');
            }
        });
    },

    appendHTML: (role, html) => {
        const msg = document.createElement('div');
        msg.className = 'chatbot-msg ' + role;
        msg.innerHTML = html;

        document.getElementById('chatbot-messages').appendChild(msg);
        document.getElementById('chatbot-messages').scrollTop = document.getElementById('chatbot-messages').scrollHeight;
    }

}

chatai.go()


