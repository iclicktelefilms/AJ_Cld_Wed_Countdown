<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- AI Assistant Floating Widget -->
<div id="ai-assistant-widget" class="ai-widget" aria-label="AI Assistant" role="complementary">

  <!-- Floating Trigger Button -->
  <button id="ai-widget-trigger" class="ai-trigger-btn" aria-label="Open AI Assistant" title="AI Assistant">
    <span class="ai-trigger-icon ai-icon-chat">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.956 9.956 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z" fill="currentColor"/>
        <circle cx="8" cy="12" r="1.5" fill="white"/>
        <circle cx="12" cy="12" r="1.5" fill="white"/>
        <circle cx="16" cy="12" r="1.5" fill="white"/>
      </svg>
    </span>
    <span class="ai-trigger-icon ai-icon-close" style="display:none">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="ai-pulse-ring"></span>
  </button>

  <!-- Chat Panel -->
  <div id="ai-chat-panel" class="ai-panel" style="display:none" role="dialog" aria-modal="false" aria-label="AI Chat Assistant">

    <!-- Header -->
    <div class="ai-panel-header">
      <div class="ai-header-info">
        <div class="ai-avatar">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div>
          <div class="ai-header-name">AI Assistant</div>
          <div class="ai-header-status" id="aiStatusText">Online</div>
        </div>
      </div>
      <div class="ai-header-actions">
        <button class="ai-icon-btn" id="aiNewSessionBtn" title="New Conversation">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
          </svg>
        </button>
        <button class="ai-icon-btn" id="aiClearBtn" title="Clear Chat">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
          </svg>
        </button>
        <button class="ai-icon-btn" id="aiMinimizeBtn" title="Minimize">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Messages Area -->
    <div class="ai-messages" id="aiMessages" role="log" aria-live="polite" aria-label="Chat messages">
      <!-- Welcome message -->
      <div class="ai-message ai-message-assistant ai-welcome-msg">
        <div class="ai-message-avatar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="ai-message-content">
          <div class="ai-message-bubble">
            <p>Hello <?php echo htmlspecialchars($staff->firstname ?? 'there'); ?>! 👋 I'm your AI assistant.</p>
            <p>I can help you with your CRM — search leads, check invoices, create tasks, generate reports, and much more.</p>
            <div class="ai-suggestions">
              <button class="ai-suggestion-chip" data-msg="Show unpaid invoices">Unpaid invoices</button>
              <button class="ai-suggestion-chip" data-msg="Show pending tasks assigned to me">My pending tasks</button>
              <button class="ai-suggestion-chip" data-msg="Revenue this month">Revenue this month</button>
              <button class="ai-suggestion-chip" data-msg="Show open support tickets">Open tickets</button>
            </div>
          </div>
          <div class="ai-message-meta">AI Assistant · now</div>
        </div>
      </div>
    </div>

    <!-- Typing Indicator -->
    <div class="ai-typing-indicator" id="aiTypingIndicator" style="display:none">
      <div class="ai-message-avatar">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2L2 7l10 5 10-5-10-5z" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="ai-typing-dots">
        <span></span><span></span><span></span>
      </div>
    </div>

    <!-- Confirmation Bar (shown when AI wants to confirm an action) -->
    <div id="aiConfirmBar" class="ai-confirm-bar" style="display:none">
      <div id="aiConfirmMsg" class="ai-confirm-msg"></div>
      <div class="ai-confirm-actions">
        <button id="aiConfirmYes" class="btn-confirm-yes">Confirm</button>
        <button id="aiConfirmNo"  class="btn-confirm-no">Cancel</button>
      </div>
    </div>

    <!-- Input Area -->
    <div class="ai-input-area">
      <div class="ai-input-row">
        <?php if ($settings['voice_enabled'] ?? false) : ?>
        <button class="ai-icon-btn ai-voice-btn" id="aiVoiceBtn" title="Voice input" aria-label="Start voice recording">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>
          </svg>
        </button>
        <?php endif; ?>
        <div class="ai-textarea-wrap">
          <textarea
            id="aiMessageInput"
            class="ai-input"
            placeholder="<?php echo lang('ai_assistant_chat_placeholder'); ?>"
            rows="1"
            maxlength="4096"
            aria-label="Message input"
          ></textarea>
        </div>
        <button class="ai-send-btn" id="aiSendBtn" title="Send message" aria-label="Send message" disabled>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </button>
      </div>
      <?php if ($settings['voice_enabled'] ?? false) : ?>
      <!-- Voice Recording UI -->
      <div id="aiVoiceRecordingUI" class="ai-voice-recording-ui" style="display:none">
        <div class="ai-voice-waveform">
          <?php for ($i = 0; $i < 20; $i++) : ?>
            <span class="ai-wave-bar" style="animation-delay:<?php echo $i * 0.05; ?>s"></span>
          <?php endfor; ?>
        </div>
        <span class="ai-voice-timer" id="aiVoiceTimer">0:00</span>
        <button class="ai-voice-stop-btn" id="aiVoiceStopBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
            <rect x="4" y="4" width="16" height="16" rx="2"/>
          </svg>
          Stop
        </button>
        <button class="ai-voice-cancel-btn" id="aiVoiceCancelBtn">Cancel</button>
      </div>
      <?php endif; ?>
      <div class="ai-input-footer">
        <span class="ai-char-count" id="aiCharCount">0 / 4096</span>
        <span class="ai-powered-by">Powered by Gemini</span>
      </div>
    </div>

  </div>
</div>

<!-- Widget data injection for JS -->
<script>
window.AI_WIDGET_CONFIG = {
  staffId:       <?php echo (int)get_staff_user_id(); ?>,
  staffName:     <?php echo json_encode(htmlspecialchars($staff->firstname . ' ' . $staff->lastname)); ?>,
  voiceEnabled:  <?php echo ($settings['voice_enabled'] ?? false) ? 'true' : 'false'; ?>,
  baseUrl:       <?php echo json_encode(admin_url('ai_assistant')); ?>,
  csrfToken:     <?php echo json_encode(csrf_token()); ?>,
};
</script>
