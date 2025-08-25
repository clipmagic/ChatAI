document.getElementById('chat-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  const input = document.getElementById('user-input');
  const message = input.value.trim();
  if (!message) return;

  appendMessage('user', message);
  input.value = '';

  try {
    const res = await fetch('/chatai-api/', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ message })
    });
    const data = await res.json();
    appendMessage('bot', data.reply || 'Sorry, no response.');
  } catch (err) {
    appendMessage('bot', 'Error contacting server.');
  }
});

function appendMessage(sender, text) {
  const msg = document.createElement('div');
  msg.className = sender;
  msg.textContent = text;
  document.getElementById('chat-window').appendChild(msg);
}
