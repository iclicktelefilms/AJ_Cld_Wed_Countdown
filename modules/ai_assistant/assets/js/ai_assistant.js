/**
 * AI Assistant Frontend — Enhanced
 *
 * Features:
 * - Markdown rendering (bold, italic, code, headers, lists, tables)
 * - Copy button on every AI message
 * - Typing indicator animation
 * - Ctrl+Enter to send
 * - Session list sidebar
 * - Scroll-to-bottom button
 * - Better error display
 * - Confirmation dialog for destructive actions
 */
(function (window, document) {
  'use strict';

  // ── Config ────────────────────────────────────────────────────────────────
  const CFG = window.AI_WIDGET_CONFIG || {};
  const BASE_URL   = CFG.baseUrl  || '';
  const CSRF_TOKEN = CFG.csrfToken || '';
  const VOICE_EN   = CFG.voiceEnabled || false;

  // ── State ─────────────────────────────────────────────────────────────────
  let sessionId      = localStorage.getItem('ai_session_id') || '';
  let isOpen         = false;
  let isProcessing   = false;
  let confirmData    = null;
  let isSidebarOpen  = false;
  let isScrolledUp   = false;

  // ── DOM References ────────────────────────────────────────────────────────
  const $ = function(id) { return document.getElementById(id); };

  const widget         = document.getElementById('ai-assistant-widget');
  const trigger        = $('ai-widget-trigger');
  const panel          = $('ai-chat-panel');
  const messages       = $('aiMessages');
  const input          = $('aiMessageInput');
  const sendBtn        = $('aiSendBtn');
  const typingInd      = $('aiTypingIndicator');
  const confirmBar     = $('aiConfirmBar');
  const confirmMsg     = $('aiConfirmMsg');
  const confirmYes     = $('aiConfirmYes');
  const confirmNo      = $('aiConfirmNo');
  const statusText     = $('aiStatusText');
  const charCount      = $('aiCharCount');
  const voiceBtn       = $('aiVoiceBtn');
  const voiceUI        = $('aiVoiceRecordingUI');
  const voiceStopBtn   = $('aiVoiceStopBtn');
  const voiceCancelBtn = $('aiVoiceCancelBtn');
  const newSessionBtn  = $('aiNewSessionBtn');
  const clearBtn       = $('aiClearBtn');
  const minimizeBtn    = $('aiMinimizeBtn');
  const sidebarToggle  = $('aiSidebarToggle');
  const sessionSidebar = $('aiSessionSidebar');
  const scrollToBottom = $('aiScrollToBottom');
  const providerBadge  = $('aiProviderBadge');

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
    if (sendBtn) sendBtn.addEventListener('click', handleSend);

    // Send on Enter (Shift+Enter = newline); Ctrl+Enter also sends
    if (input) {
      input.addEventListener('keydown', function(e) {
        if ((e.key === 'Enter' && !e.shiftKey) || (e.key === 'Enter' && e.ctrlKey)) {
          e.preventDefault();
          handleSend();
        }
      });

      // Enable/disable send button + char counter
      input.addEventListener('input', function() {
        var len = input.value.length;
        if (sendBtn) sendBtn.disabled = len === 0 || isProcessing;
        if (charCount) charCount.textContent = len + ' / 4096';
        autoResizeTextarea(input);
      });
    }

    // Suggestion chips
    if (messages) {
      messages.addEventListener('click', function(e) {
        var chip = e.target.closest('.ai-suggestion-chip');
        if (chip) {
          input.value = chip.dataset.msg || chip.textContent;
          input.dispatchEvent(new Event('input'));
          handleSend();
        }

        // Quick actions from sidebar
        var action = e.target.closest('[data-quick-action]');
        if (action) {
          input.value = action.dataset.quickAction;
          input.dispatchEvent(new Event('input'));
          closeSidebar();
          handleSend();
        }
      });

      // Copy button on messages
      messages.addEventListener('click', function(e) {
        var copyBtn = e.target.closest('.ai-copy-btn');
        if (copyBtn) {
          var bubble = copyBtn.closest('.ai-message').querySelector('.ai-message-bubble');
          if (bubble) copyToClipboard(bubble.innerText, copyBtn);
        }
      });

      // Scroll detection for scroll-to-bottom button
      messages.addEventListener('scroll', function() {
        var threshold = 100;
        var atBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < threshold;
        isScrolledUp = !atBottom;
        if (scrollToBottom) {
          scrollToBottom.style.display = isScrolledUp ? 'flex' : 'none';
        }
      });
    }

    // Scroll to bottom button
    if (scrollToBottom) {
      scrollToBottom.addEventListener('click', function() {
        scrollMessagesToBottom();
        scrollToBottom.style.display = 'none';
      });
    }

    // Session controls
    if (newSessionBtn) newSessionBtn.addEventListener('click', startNewSession);
    if (clearBtn)      clearBtn.addEventListener('click',       clearChat);
    if (minimizeBtn)   minimizeBtn.addEventListener('click',    toggleWidget);

    // Session sidebar toggle
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', function() {
        isSidebarOpen ? closeSidebar() : openSidebar();
      });
    }

    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
      if (isSidebarOpen && sessionSidebar && !sessionSidebar.contains(e.target) && e.target !== sidebarToggle) {
        closeSidebar();
      }
    });

    // Confirmation buttons
    if (confirmYes) confirmYes.addEventListener('click', executeConfirmedAction);
    if (confirmNo)  confirmNo.addEventListener('click',  cancelConfirm);

    // Voice recording
    if (VOICE_EN && voiceBtn) {
      voiceBtn.addEventListener('click', startVoiceRecording);
    }
    if (voiceStopBtn)   voiceStopBtn.addEventListener('click',   stopVoiceRecording);
    if (voiceCancelBtn) voiceCancelBtn.addEventListener('click', cancelVoiceRecording);
  }

  // ── Widget Toggle ─────────────────────────────────────────────────────────
  function toggleWidget() {
    isOpen = !isOpen;

    var iconChat  = trigger.querySelector('.ai-icon-chat');
    var iconClose = trigger.querySelector('.ai-icon-close');

    if (isOpen) {
      panel.style.display = 'flex';
      panel.style.flexDirection = 'column';
      if (iconChat)  iconChat.style.display  = 'none';
      if (iconClose) iconClose.style.display = 'flex';
      panel.setAttribute('aria-hidden', 'false');
      if (input) input.focus();
      scrollMessagesToBottom();
    } else {
      panel.style.display = 'none';
      if (iconChat)  iconChat.style.display  = 'flex';
      if (iconClose) iconClose.style.display = 'none';
      panel.setAttribute('aria-hidden', 'true');
      closeSidebar();
    }
  }

  // ── Sidebar ───────────────────────────────────────────────────────────────
  function openSidebar() {
    if (!sessionSidebar) return;
    isSidebarOpen = true;
    sessionSidebar.classList.add('ai-sidebar-open');
    loadSessionList();
  }

  function closeSidebar() {
    if (!sessionSidebar) return;
    isSidebarOpen = false;
    sessionSidebar.classList.remove('ai-sidebar-open');
  }

  async function loadSessionList() {
    if (!sessionSidebar) return;
    var listEl = sessionSidebar.querySelector('.ai-session-list');
    if (!listEl) return;

    listEl.innerHTML = '<div class="ai-sidebar-loading">Loading...</div>';

    try {
      var data = await get('/sessions');
      var sessions = data.sessions || [];

      if (sessions.length === 0) {
        listEl.innerHTML = '<div class="ai-sidebar-empty">No previous conversations</div>';
        return;
      }

      var html = '';
      sessions.forEach(function(s) {
        var active = s.session_id === sessionId ? ' ai-session-item-active' : '';
        var title = escapeHtml(s.title || 'New Conversation');
        var date = s.updated_at ? new Date(s.updated_at).toLocaleDateString() : '';
        html += '<div class="ai-session-item' + active + '" data-session="' + escapeHtml(s.session_id) + '">'
          + '<div class="ai-session-title">' + title + '</div>'
          + '<div class="ai-session-date">' + date + '</div>'
          + '</div>';
      });
      listEl.innerHTML = html;

      // Click to switch session
      listEl.querySelectorAll('.ai-session-item').forEach(function(item) {
        item.addEventListener('click', function() {
          switchSession(item.dataset.session);
          closeSidebar();
        });
      });
    } catch (e) {
      listEl.innerHTML = '<div class="ai-sidebar-error">Failed to load sessions</div>';
    }
  }

  async function switchSession(sid) {
    sessionId = sid;
    localStorage.setItem('ai_session_id', sessionId);

    // Clear current messages and load from server
    var allMessages = messages.querySelectorAll('.ai-message:not(.ai-welcome-msg)');
    allMessages.forEach(function(el) { el.remove(); });

    try {
      var data = await get('/messages?session_id=' + encodeURIComponent(sid));
      var msgs = data.messages || [];
      msgs.forEach(function(m) {
        if (m.message_type === 'user') {
          appendUserMessage(m.message);
        } else if (m.message_type === 'assistant') {
          appendAssistantMessage(m.message);
        }
      });
      if (msgs.length === 0) {
        appendAssistantMessage('This is the beginning of the conversation.');
      }
    } catch (e) {
      appendErrorMessage('Could not load conversation history.');
    }
  }

  // ── Message Sending ───────────────────────────────────────────────────────
  async function handleSend() {
    var text = input.value.trim();
    if (!text || isProcessing) return;

    appendUserMessage(text);
    input.value = '';
    input.dispatchEvent(new Event('input'));
    setProcessing(true);

    try {
      var data = await post('/chat', {
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
    var html = ''
      + '<div class="ai-message ai-message-user ai-fade-in">'
      + '<div class="ai-message-avatar">'
      + '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
      + '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'
      + '</svg>'
      + '</div>'
      + '<div class="ai-message-content">'
      + '<div class="ai-message-bubble">' + escapeHtml(text) + '</div>'
      + '<div class="ai-message-meta">' + formatTime() + '</div>'
      + '</div>'
      + '</div>';

    appendToMessages(html);
  }

  function appendAssistantMessage(text) {
    if (!text) return;

    var rendered = renderMarkdown(text);
    var html = ''
      + '<div class="ai-message ai-message-assistant ai-fade-in">'
      + '<div class="ai-message-avatar">'
      + '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
      + '<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round"/>'
      + '</svg>'
      + '</div>'
      + '<div class="ai-message-content">'
      + '<div class="ai-message-bubble">' + rendered + '</div>'
      + '<div class="ai-message-meta">'
      + 'AI Assistant &middot; ' + formatTime()
      + '<button class="ai-msg-action-btn ai-copy-btn" title="Copy response">'
      + '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
      + '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>'
      + '</svg>'
      + ' Copy'
      + '</button>'
      + '</div>'
      + '</div>'
      + '</div>';

    appendToMessages(html);
  }

  function appendErrorMessage(text) {
    var html = ''
      + '<div class="ai-message ai-message-assistant ai-fade-in">'
      + '<div class="ai-message-avatar" style="background:#fce8e6;color:#ea4335">'
      + '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">'
      + '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
      + '</svg>'
      + '</div>'
      + '<div class="ai-message-content">'
      + '<div class="ai-message-bubble ai-error-bubble">'
      + '<strong>Error:</strong> ' + escapeHtml(text)
      + '</div>'
      + '<div class="ai-message-meta">' + formatTime() + '</div>'
      + '</div>'
      + '</div>';

    appendToMessages(html);
  }

  function appendToMessages(html) {
    var div = document.createElement('div');
    div.innerHTML = html;
    var el = div.firstElementChild;
    messages.appendChild(el);
    if (!isScrolledUp) scrollMessagesToBottom();
  }

  // ── Typing Indicator ──────────────────────────────────────────────────────
  function showTyping() {
    if (typingInd) {
      typingInd.style.display = 'flex';
      if (!isScrolledUp) scrollMessagesToBottom();
    }
  }

  function hideTyping() {
    if (typingInd) typingInd.style.display = 'none';
  }

  // ── Processing State ──────────────────────────────────────────────────────
  function setProcessing(val) {
    isProcessing = val;
    if (sendBtn) sendBtn.disabled = val || (input && input.value.trim().length === 0);

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
      if (confirmMsg) confirmMsg.textContent = 'Confirm this action? This cannot be undone.';
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
      var data = await post('/confirm_action', {
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
      var data = await post('/new_session', {});
      sessionId = data.session_id;
      localStorage.setItem('ai_session_id', sessionId);

      // Clear chat messages except welcome
      var allMessages = messages.querySelectorAll('.ai-message:not(.ai-welcome-msg)');
      allMessages.forEach(function(el) { el.remove(); });
      hideConfirmBar();
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

    var allMessages = messages.querySelectorAll('.ai-message:not(.ai-welcome-msg)');
    allMessages.forEach(function(el) { el.remove(); });

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
      var rec = await VoiceRecorder.stop();
      var blob = rec.blob;
      var mimeType = rec.mimeType;
      var base64 = await VoiceRecorder.blobToBase64(blob);

      var data = await post('/transcribe', {
        audio_base64: base64,
        mime_type:    mimeType,
        language:     '',
      });

      if (data.transcript) {
        input.value = data.transcript;
        input.dispatchEvent(new Event('input'));
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
      var data = await post('/speak', { text: text, language: 'en-US' });

      if (data.audio_base64) {
        var audio = new Audio('data:' + (data.mime_type || 'audio/wav') + ';base64,' + data.audio_base64);
        audio.play().catch(function() {});
      } else if (data.use_browser_tts && window.speechSynthesis) {
        var utt = new SpeechSynthesisUtterance(data.text || text);
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
    var form = new FormData();
    var keys = Object.keys(data);
    for (var i = 0; i < keys.length; i++) {
      form.append(keys[i], data[keys[i]]);
    }
    form.append('csrf_token', CSRF_TOKEN);

    var response = await fetch(BASE_URL + path, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token':     CSRF_TOKEN,
      },
      body: form,
    });

    var json = await response.json();

    if (json.status === 'error') {
      throw new Error(json.message || 'Server error');
    }

    return json;
  }

  async function get(path) {
    var response = await fetch(BASE_URL + path, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token':     CSRF_TOKEN,
      },
    });

    var json = await response.json();

    if (json.status === 'error') {
      throw new Error(json.message || 'Server error');
    }

    return json;
  }

  // ── Markdown Renderer ─────────────────────────────────────────────────────
  function renderMarkdown(text) {
    if (!text) return '';

    var html = escapeHtml(text);

    // Fenced code blocks (```lang\n...\n```) — process before other rules
    html = html.replace(/```([a-zA-Z0-9]*)\n([\s\S]*?)```/g, function(_, lang, code) {
      var langClass = lang ? ' class="language-' + escapeHtml(lang) + '"' : '';
      return '<pre class="ai-code-block"><button class="ai-code-copy-btn" onclick="aiCopyCode(this)" title="Copy code">Copy</button>'
        + '<code' + langClass + '>' + code + '</code></pre>';
    });

    // Inline code
    html = html.replace(/`([^`\n]+)`/g, '<code class="ai-inline-code">$1</code>');

    // Headers
    html = html.replace(/^### (.+)$/gm, '<h3 class="ai-md-h3">$1</h3>');
    html = html.replace(/^## (.+)$/gm,  '<h2 class="ai-md-h2">$1</h2>');
    html = html.replace(/^# (.+)$/gm,   '<h1 class="ai-md-h1">$1</h1>');

    // Bold
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Italic (but not inside **bold**)
    html = html.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');

    // Tables (GFM)
    html = html.replace(/(\|[^\n]+\|\n\|[-:| ]+\|\n)((?:\|[^\n]+\|\n?)*)/g, function(match, header, body) {
      var headerCells = header.split('\n')[0].split('|').slice(1, -1).map(function(c) {
        return '<th>' + c.trim() + '</th>';
      }).join('');
      var bodyRows = body.trim().split('\n').map(function(row) {
        var cells = row.split('|').slice(1, -1).map(function(c) {
          return '<td>' + c.trim() + '</td>';
        }).join('');
        return '<tr>' + cells + '</tr>';
      }).join('');
      return '<div class="ai-table-wrap"><table class="ai-md-table"><thead><tr>' + headerCells + '</tr></thead><tbody>' + bodyRows + '</tbody></table></div>';
    });

    // Unordered lists — group consecutive li items
    html = html.replace(/^[\-\*] (.+)$/gm, function(_, content) {
      return '<li>' + content + '</li>';
    });
    html = html.replace(/(<li>[\s\S]*?<\/li>\n?)+/g, function(m) {
      return '<ul class="ai-md-ul">' + m + '</ul>';
    });

    // Ordered lists
    html = html.replace(/^\d+\. (.+)$/gm, function(_, content) {
      return '<li>' + content + '</li>';
    });

    // Blockquotes
    html = html.replace(/^&gt; (.+)$/gm, '<blockquote class="ai-md-quote">$1</blockquote>');

    // Horizontal rule
    html = html.replace(/^---$/gm, '<hr class="ai-md-hr">');

    // Paragraphs and line breaks
    html = html.replace(/\n\n+/g, '</p><p>');
    html = html.replace(/\n/g, '<br>');
    html = '<p>' + html + '</p>';

    // Clean up empty paragraphs and wrap block elements properly
    html = html.replace(/<p>\s*<\/p>/g, '');
    html = html.replace(/<p>(<h[1-6][^>]*>)/g, '$1');
    html = html.replace(/(<\/h[1-6]>)<\/p>/g, '$1');
    html = html.replace(/<p>(<ul[^>]*>)/g, '$1');
    html = html.replace(/(<\/ul>)<\/p>/g, '$1');
    html = html.replace(/<p>(<ol[^>]*>)/g, '$1');
    html = html.replace(/(<\/ol>)<\/p>/g, '$1');
    html = html.replace(/<p>(<pre[^>]*>)/g, '$1');
    html = html.replace(/(<\/pre>)<\/p>/g, '$1');
    html = html.replace(/<p>(<div[^>]*>)/g, '$1');
    html = html.replace(/(<\/div>)<\/p>/g, '$1');
    html = html.replace(/<p>(<table[^>]*>)/g, '$1');
    html = html.replace(/(<\/table>)<\/p>/g, '$1');
    html = html.replace(/<p>(<blockquote[^>]*>)/g, '$1');
    html = html.replace(/(<\/blockquote>)<\/p>/g, '$1');
    html = html.replace(/<p><hr[^>]*><\/p>/g, '<hr class="ai-md-hr">');

    return html;
  }

  // Global function for code copy button
  window.aiCopyCode = function(btn) {
    var code = btn.nextElementSibling;
    if (code) {
      navigator.clipboard.writeText(code.innerText).then(function() {
        var orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = orig; }, 2000);
      }).catch(function() {});
    }
  };

  // ── Utilities ─────────────────────────────────────────────────────────────
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#039;');
  }

  function scrollMessagesToBottom() {
    requestAnimationFrame(function() {
      messages.scrollTop = messages.scrollHeight;
    });
  }

  function autoResizeTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
  }

  function formatTime() {
    var now = new Date();
    return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 9);
  }

  function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(function() {
      var original = btn.innerHTML;
      btn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
      setTimeout(function() { btn.innerHTML = original; }, 2000);
    }).catch(function() {});
  }

  // ── Bootstrap ─────────────────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window, document);
