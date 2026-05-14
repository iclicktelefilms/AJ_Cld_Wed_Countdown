/**
 * AI Assistant Frontend
 *
 * Handles the floating chat widget UI, message rendering,
 * voice recording integration, confirm dialogs, and session management.
 *
 * Requires: voice_recorder.js loaded before this file
 */
(function (window, document) {
  'use strict';

  // ── Config ────────────────────────────────────────────────────────────────
  const CFG = window.AI_WIDGET_CONFIG || {};
  const BASE_URL   = CFG.baseUrl  || '';
  const CSRF_TOKEN = CFG.csrfToken || '';
  const VOICE_EN   = CFG.voiceEnabled || false;

  // ── State ─────────────────────────────────────────────────────────────────
  let sessionId    = localStorage.getItem('ai_session_id') || '';
  let isOpen       = false;
  let isProcessing = false;
  let confirmData  = null;

  // ── DOM References ────────────────────────────────────────────────────────
  const $ = id => document.getElementById(id);

  const widget      = document.getElementById('ai-assistant-widget');
  const trigger     = $('ai-widget-trigger');
  const panel       = $('ai-chat-panel');
  const messages    = $('aiMessages');
  const input       = $('aiMessageInput');
  const sendBtn     = $('aiSendBtn');
  const typingInd   = $('aiTypingIndicator');
  const confirmBar  = $('aiConfirmBar');
  const confirmMsg  = $('aiConfirmMsg');
  const confirmYes  = $('aiConfirmYes');
  const confirmNo   = $('aiConfirmNo');
  const statusText  = $('aiStatusText');
  const charCount   = $('aiCharCount');
  const voiceBtn    = $('aiVoiceBtn');
  const voiceUI     = $('aiVoiceRecordingUI');
  const voiceStopBtn  = $('aiVoiceStopBtn');
  const voiceCancelBtn = $('aiVoiceCancelBtn');
  const newSessionBtn  = $('aiNewSessionBtn');
  const clearBtn       = $('aiClearBtn');
  const minimizeBtn    = $('aiMinimizeBtn');

  // ── Init ──────────────────────────────────────────────────────────────────
  function init() {
    if (!trigger || !panel) return;

    bindEvents();
    restoreSession();
  }

  function bindEvents() {
    // Toggle widget open/close
    trigger.addEventListener('click', toggleWidget);

    // Send on click
    sendBtn.addEventListener('click', handleSend);

    // Send on Enter (Shift+Enter = newline)
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
      }
    });

    // Enable/disable send button + char counter
    input.addEventListener('input', () => {
      const len = input.value.length;
      sendBtn.disabled = len === 0 || isProcessing;
      if (charCount) charCount.textContent = `${len} / 4096`;
      autoResizeTextarea(input);
    });

    // Suggestion chips
    messages.addEventListener('click', (e) => {
      const chip = e.target.closest('.ai-suggestion-chip');
      if (chip) {
        input.value = chip.dataset.msg || chip.textContent;
        input.dispatchEvent(new Event('input'));
        handleSend();
      }
    });

    // Copy button on messages
    messages.addEventListener('click', (e) => {
      if (e.target.closest('.ai-copy-btn')) {
        const bubble = e.target.closest('.ai-message').querySelector('.ai-message-bubble');
        if (bubble) copyToClipboard(bubble.innerText, e.target.closest('.ai-copy-btn'));
      }
    });

    // Session controls
    if (newSessionBtn) newSessionBtn.addEventListener('click', startNewSession);
    if (clearBtn)      clearBtn.addEventListener('click',       clearChat);
    if (minimizeBtn)   minimizeBtn.addEventListener('click',    toggleWidget);

    // Confirmation buttons
    if (confirmYes) confirmYes.addEventListener('click', executeConfirmedAction);
    if (confirmNo)  confirmNo.addEventListener('click',  cancelConfirm);

    // Voice recording
    if (VOICE_EN && voiceBtn) {
      voiceBtn.addEventListener('click', startVoiceRecording);
    }
    if (voiceStopBtn)   voiceStopBtn.addEventListener('click',   stopVoiceRecording);
    if (voiceCancelBtn) voiceCancelBtn.addEventListener('click', cancelVoiceRecording);

    // Close widget on outside click
    document.addEventListener('click', (e) => {
      if (isOpen && !widget.contains(e.target)) {
        // Intentionally not closing on outside click for better UX
      }
    });
  }

  // ── Widget Toggle ─────────────────────────────────────────────────────────
  function toggleWidget() {
    isOpen = !isOpen;

    const iconChat  = trigger.querySelector('.ai-icon-chat');
    const iconClose = trigger.querySelector('.ai-icon-close');

    if (isOpen) {
      panel.style.display = 'flex';
      panel.style.flexDirection = 'column';
      iconChat.style.display  = 'none';
      iconClose.style.display = 'flex';
      panel.setAttribute('aria-hidden', 'false');
      input.focus();
      scrollToBottom();
    } else {
      panel.style.display = 'none';
      iconChat.style.display  = 'flex';
      iconClose.style.display = 'none';
      panel.setAttribute('aria-hidden', 'true');
    }
  }

  // ── Message Sending ───────────────────────────────────────────────────────
  async function handleSend() {
    const text = input.value.trim();
    if (!text || isProcessing) return;

    appendUserMessage(text);
    input.value = '';
    input.dispatchEvent(new Event('input'));
    setProcessing(true);

    try {
      const data = await post('/chat', {
        message:    text,
        session_id: sessionId,
      });

      if (data.session_id) {
        sessionId = data.session_id;
        localStorage.setItem('ai_session_id', sessionId);
      }

      if (data.needs_confirm && data.confirm_data) {
        showConfirmBar(data.message, data.confirm_data);
        appendAssistantMessage(data.message);
      } else {
        appendAssistantMessage(data.message);
        hideConfirmBar();

        // TTS playback if voice enabled
        if (VOICE_EN && data.message) {
          speakResponse(data.message);
        }
      }

    } catch (err) {
      appendErrorMessage(err.message || 'Failed to get a response. Please try again.');
    } finally {
      setProcessing(false);
    }
  }

  // ── DOM Message Builders ──────────────────────────────────────────────────
  function appendUserMessage(text) {
    const html = `
      <div class="ai-message ai-message-user ai-fade-in">
        <div class="ai-message-avatar">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
        </div>
        <div class="ai-message-content">
          <div class="ai-message-bubble">${escapeHtml(text)}</div>
          <div class="ai-message-meta">${formatTime()}</div>
        </div>
      </div>`;

    appendToMessages(html);
  }

  function appendAssistantMessage(text) {
    if (!text) return;

    const rendered = renderMarkdown(text);
    const html = `
      <div class="ai-message ai-message-assistant ai-fade-in">
        <div class="ai-message-avatar">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="ai-message-content">
          <div class="ai-message-bubble">${rendered}</div>
          <div class="ai-message-meta">
            AI Assistant · ${formatTime()}
            <button class="ai-msg-action-btn ai-copy-btn" title="Copy">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
              </svg>
              Copy
            </button>
          </div>
        </div>
      </div>`;

    appendToMessages(html);
  }

  function appendErrorMessage(text) {
    const html = `
      <div class="ai-message ai-message-assistant ai-fade-in">
        <div class="ai-message-avatar" style="background:#fce8e6;color:#ea4335">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
        <div class="ai-message-content">
          <div class="ai-message-bubble ai-text-error">${escapeHtml(text)}</div>
          <div class="ai-message-meta">${formatTime()}</div>
        </div>
      </div>`;

    appendToMessages(html);
  }

  function appendToMessages(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    const el = div.firstElementChild;
    messages.appendChild(el);
    scrollToBottom();
  }

  // ── Typing Indicator ──────────────────────────────────────────────────────
  function showTyping() {
    if (typingInd) typingInd.style.display = 'flex';
    scrollToBottom();
  }

  function hideTyping() {
    if (typingInd) typingInd.style.display = 'none';
  }

  // ── Processing State ──────────────────────────────────────────────────────
  function setProcessing(val) {
    isProcessing = val;
    sendBtn.disabled = val || input.value.trim().length === 0;

    if (val) {
      showTyping();
      if (statusText) statusText.textContent = 'Thinking...';
    } else {
      hideTyping();
      if (statusText) statusText.textContent = 'Online';
    }
  }

  // ── Confirmation ──────────────────────────────────────────────────────────
  function showConfirmBar(message, data) {
    confirmData = data;
    if (confirmBar) {
      confirmBar.style.display = 'block';
      if (confirmMsg) confirmMsg.textContent = 'Confirm this action?';
    }
  }

  function hideConfirmBar() {
    confirmData = null;
    if (confirmBar) confirmBar.style.display = 'none';
  }

  async function executeConfirmedAction() {
    if (!confirmData) return;

    hideConfirmBar();
    setProcessing(true);

    try {
      const data = await post('/confirm_action', {
        tool:       confirmData.tool,
        params:     JSON.stringify(confirmData.params),
        session_id: sessionId,
      });

      appendAssistantMessage(data.message);
    } catch (err) {
      appendErrorMessage(err.message || 'Action failed.');
    } finally {
      setProcessing(false);
    }
  }

  function cancelConfirm() {
    hideConfirmBar();
    appendAssistantMessage('Action cancelled.');
  }

  // ── Session Management ────────────────────────────────────────────────────
  async function restoreSession() {
    if (!sessionId) {
      await startNewSession();
    }
  }

  async function startNewSession() {
    try {
      const data = await post('/new_session', {});
      sessionId = data.session_id;
      localStorage.setItem('ai_session_id', sessionId);
    } catch (e) {
      sessionId = generateId();
      localStorage.setItem('ai_session_id', sessionId);
    }
  }

  async function clearChat() {
    if (!confirm('Clear this conversation? This cannot be undone.')) return;

    try {
      await post('/clear_session', { session_id: sessionId });
    } catch (e) { /* continue */ }

    // Remove all messages except the welcome message
    const allMessages = messages.querySelectorAll('.ai-message:not(.ai-welcome-msg)');
    allMessages.forEach(el => el.remove());

    await startNewSession();
    hideConfirmBar();
    appendAssistantMessage('Started a new conversation. How can I help you?');
  }

  // ── Voice Recording ───────────────────────────────────────────────────────
  async function startVoiceRecording() {
    if (!window.VoiceRecorder) return;

    try {
      if (!VoiceRecorder.isSupported()) {
        appendErrorMessage('Voice recording is not supported in your browser.');
        return;
      }

      await VoiceRecorder.start();
      voiceBtn.classList.add('recording');
      if (voiceUI)  voiceUI.style.display  = 'flex';
      input.placeholder = 'Recording...';

    } catch (err) {
      appendErrorMessage(err.message || 'Could not start recording.');
    }
  }

  async function stopVoiceRecording() {
    if (!window.VoiceRecorder || !VoiceRecorder.isRecording) return;

    voiceBtn.classList.remove('recording');
    if (voiceUI) voiceUI.style.display = 'none';
    input.placeholder = 'Processing voice...';
    setProcessing(true);

    try {
      const { blob, mimeType } = await VoiceRecorder.stop();
      const base64 = await VoiceRecorder.blobToBase64(blob);

      const data = await post('/transcribe', {
        audio_base64: base64,
        mime_type:    mimeType,
        language:     '',
      });

      if (data.transcript) {
        input.value = data.transcript;
        input.dispatchEvent(new Event('input'));
        // Auto-send the transcribed message
        await handleSend();
      } else {
        appendErrorMessage('Could not transcribe audio. Please try again.');
      }

    } catch (err) {
      appendErrorMessage(err.message || 'Voice processing failed.');
    } finally {
      setProcessing(false);
      input.placeholder = 'Ask me anything about your CRM...';
    }
  }

  function cancelVoiceRecording() {
    if (window.VoiceRecorder) VoiceRecorder.cancel();
    voiceBtn.classList.remove('recording');
    if (voiceUI) voiceUI.style.display = 'none';
    input.placeholder = 'Ask me anything about your CRM...';
  }

  // ── Text-to-Speech ────────────────────────────────────────────────────────
  async function speakResponse(text) {
    try {
      const data = await post('/speak', { text, language: 'en-US' });

      if (data.audio_base64) {
        const audio = new Audio('data:' + (data.mime_type || 'audio/wav') + ';base64,' + data.audio_base64);
        audio.play().catch(() => {});
      } else if (data.use_browser_tts && window.speechSynthesis) {
        const utt = new SpeechSynthesisUtterance(data.text || text);
        utt.lang  = data.language || 'en-US';
        utt.rate  = 1.0;
        window.speechSynthesis.speak(utt);
      }
    } catch (e) {
      // TTS is optional — silently ignore failures
    }
  }

  // ── HTTP Helpers ──────────────────────────────────────────────────────────
  async function post(path, data) {
    const form = new FormData();
    for (const [k, v] of Object.entries(data)) {
      form.append(k, v);
    }
    form.append('csrf_token', CSRF_TOKEN);

    const response = await fetch(BASE_URL + path, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token':     CSRF_TOKEN,
      },
      body: form,
    });

    const json = await response.json();

    if (json.status === 'error') {
      throw new Error(json.message || 'Server error');
    }

    return json;
  }

  // ── Markdown Renderer ─────────────────────────────────────────────────────
  function renderMarkdown(text) {
    if (!text) return '';

    let html = escapeHtml(text);

    // Code blocks (```lang\n...\n```)
    html = html.replace(/```([a-z]*)\n([\s\S]*?)```/g, (_, lang, code) => {
      return `<pre><code class="language-${escapeHtml(lang)}">${code}</code></pre>`;
    });

    // Inline code
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Headers
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm,  '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm,   '<h1>$1</h1>');

    // Bold
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Italic
    html = html.replace(/\*([^*]+?)\*/g, '<em>$1</em>');

    // Tables (simplified GFM)
    html = html.replace(/(\|[^\n]+\|\n\|[-| ]+\|\n)((?:\|[^\n]+\|\n?)*)/g, (match, header, body) => {
      const headerCells = header.split('\n')[0].split('|').slice(1, -1).map(c => `<th>${c.trim()}</th>`).join('');
      const bodyRows    = body.trim().split('\n').map(row => {
        const cells = row.split('|').slice(1, -1).map(c => `<td>${c.trim()}</td>`).join('');
        return `<tr>${cells}</tr>`;
      }).join('');
      return `<table><thead><tr>${headerCells}</tr></thead><tbody>${bodyRows}</tbody></table>`;
    });

    // Unordered lists
    html = html.replace(/^\- (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>\n?)+/g, m => `<ul>${m}</ul>`);

    // Numbered lists
    html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');

    // Blockquotes
    html = html.replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>');

    // Horizontal rule
    html = html.replace(/^---$/gm, '<hr>');

    // Paragraphs — convert double newlines
    html = html.replace(/\n\n+/g, '</p><p>');
    html = html.replace(/\n/g, '<br>');
    html = `<p>${html}</p>`;

    // Clean up empty paragraphs
    html = html.replace(/<p>\s*<\/p>/g, '');
    html = html.replace(/<p>(<h[1-6]>)/g, '$1');
    html = html.replace(/(<\/h[1-6]>)<\/p>/g, '$1');
    html = html.replace(/<p>(<ul>)/g, '$1');
    html = html.replace(/(<\/ul>)<\/p>/g, '$1');
    html = html.replace(/<p>(<pre>)/g, '$1');
    html = html.replace(/(<\/pre>)<\/p>/g, '$1');
    html = html.replace(/<p>(<table>)/g, '$1');
    html = html.replace(/(<\/table>)<\/p>/g, '$1');
    html = html.replace(/<p>(<blockquote>)/g, '$1');
    html = html.replace(/(<\/blockquote>)<\/p>/g, '$1');
    html = html.replace(/<p><hr><\/p>/g, '<hr>');

    return html;
  }

  // ── Utilities ─────────────────────────────────────────────────────────────
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#039;');
  }

  function scrollToBottom() {
    requestAnimationFrame(() => {
      messages.scrollTop = messages.scrollHeight;
    });
  }

  function autoResizeTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
  }

  function formatTime() {
    const now = new Date();
    return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 9);
  }

  function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
      const original = btn.innerHTML;
      btn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
      setTimeout(() => { btn.innerHTML = original; }, 2000);
    }).catch(() => {});
  }

  // ── Bootstrap ─────────────────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window, document);
