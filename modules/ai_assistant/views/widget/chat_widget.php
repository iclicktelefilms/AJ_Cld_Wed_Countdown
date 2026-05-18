<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
$active_provider = $settings['active_provider'] ?? 'gemini';
$provider_labels = [
    'gemini'     => 'Gemini',
    'openai'     => 'OpenAI',
    'claude'     => 'Claude',
    'openrouter' => 'OpenRouter',
    'ollama'     => 'Ollama',
];
$provider_label = $provider_labels[$active_provider] ?? ucfirst($active_provider);
?>

<!-- AI Assistant Floating Widget -->
<div id="ai-assistant-widget" class="ai-widget" aria-label="AI Assistant" role="complementary">

  <!-- Floating Trigger Button -->
  <button id="ai-widget-trigger" class="ai-trigger-btn" aria-label="Open AI Assistant" title="AI Assistant">
    <span class="ai-trigger-icon ai-icon-chat">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.956 9.956 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z" fill="currentColor"/>
        <circle cx="8"  cy="12" r="1.5" fill="white"/>
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

    <!-- Session Sidebar (collapsible) -->
    <div id="aiSessionSidebar" class="ai-sidebar">
      <div class="ai-sidebar-header">
        <span>Conversations</span>
        <button class="ai-sidebar-close" id="aiSidebarClose" aria-label="Close sidebar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <!-- Quick Actions -->
      <div class="ai-sidebar-quick-actions">
        <h5>Quick Actions</h5>
        <button class="ai-quick-action-btn" data-quick-action="Show overdue invoices">
          <?php echo lang('ai_quick_overdue_invoices'); ?>
        </button>
        <button class="ai-quick-action-btn" data-quick-action="List open support tickets">
          <?php echo lang('ai_quick_open_tickets'); ?>
        </button>
        <button class="ai-quick-action-btn" data-quick-action="Show pending tasks assigned to me">
          <?php echo lang('ai_quick_my_tasks'); ?>
        </button>
        <button class="ai-quick-action-btn" data-quick-action="Revenue report for this month">
          <?php echo lang('ai_quick_revenue'); ?>
        </button>
      </div>

      <div class="ai-session-list-header">Recent Conversations</div>
      <div class="ai-session-list" id="aiSessionList">
        <div class="ai-sidebar-loading">Loading...</div>
      </div>
    </div>

    <!-- Header -->
    <div class="ai-panel-header">
      <div class="ai-header-info">
        <div class="ai-avatar">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div>
          <div class="ai-header-name">
            AI Assistant
            <span class="ai-provider-badge" id="aiProviderBadge"><?php echo htmlspecialchars($provider_label); ?></span>
          </div>
          <div class="ai-header-status">
            <span class="ai-status-dot"></span>
            <span id="aiStatusText">Online</span>
          </div>
        </div>
      </div>
      <div class="ai-header-actions">
        <button class="ai-icon-btn" id="aiSidebarToggle" title="Sessions">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6"  x2="21" y2="6"  stroke-linecap="round"/>
            <line x1="3" y1="12" x2="21" y2="12" stroke-linecap="round"/>
            <line x1="3" y1="18" x2="15" y2="18" stroke-linecap="round"/>
          </svg>
        </button>
        <button class="ai-icon-btn" id="aiNewSessionBtn" title="New Conversation">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
          </svg>
        </button>
        <button class="ai-icon-btn" id="aiClearBtn" title="Clear Chat">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

    <!-- Messages + scroll wrapper -->
    <div class="ai-messages-wrap">
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
              <p>Hello <strong><?php echo htmlspecialchars($staff->firstname ?? 'there'); ?></strong>! I&rsquo;m your AI assistant powered by <?php echo htmlspecialchars($provider_label); ?>.</p>
              <p>I can help you with your CRM &mdash; search leads, check invoices, create tasks, generate reports, and much more.</p>
              <div class="ai-suggestions">
                <button class="ai-suggestion-chip" data-msg="Show unpaid invoices"><?php echo lang('ai_chip_unpaid_invoices'); ?></button>
                <button class="ai-suggestion-chip" data-msg="Show pending tasks assigned to me"><?php echo lang('ai_chip_my_tasks'); ?></button>
                <button class="ai-suggestion-chip" data-msg="Revenue this month"><?php echo lang('ai_chip_revenue'); ?></button>
                <button class="ai-suggestion-chip" data-msg="Show open support tickets"><?php echo lang('ai_chip_tickets'); ?></button>
              </div>
            </div>
            <div class="ai-message-meta">AI Assistant &middot; now</div>
          </div>
        </div>
      </div>

      <!-- Scroll to bottom button -->
      <button id="aiScrollToBottom" class="ai-scroll-bottom" style="display:none" title="Scroll to latest" aria-label="Scroll to bottom">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="6 9 12 15 18 9" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
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

    <!-- Confirmation Bar -->
    <div id="aiConfirmBar" class="ai-confirm-bar" style="display:none">
      <div id="aiConfirmMsg" class="ai-confirm-msg">Confirm this action?</div>
      <div class="ai-confirm-actions">
        <button id="aiConfirmYes" class="btn-confirm-yes">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          Confirm
        </button>
        <button id="aiConfirmNo" class="btn-confirm-no">Cancel</button>
      </div>
    </div>

    <!-- Input Area -->
    <div class="ai-input-area">
      <div class="ai-input-row">
        <?php if ($settings['voice_enabled'] ?? false) : ?>
        <button class="ai-icon-btn ai-voice-btn" id="aiVoiceBtn" title="Voice input" aria-label="Start voice recording">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
            <line x1="12" y1="19" x2="12" y2="23"/>
            <line x1="8"  y1="23" x2="16" y2="23"/>
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

        <button class="ai-send-btn" id="aiSendBtn" title="Send (Enter or Ctrl+Enter)" aria-label="Send message" disabled>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="22" y1="2" x2="11" y2="13"/>
            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </button>
      </div>

      <?php if ($settings['voice_enabled'] ?? false) : ?>
      <!-- Voice Recording UI -->
      <div id="aiVoiceRecordingUI" class="ai-voice-recording-ui" style="display:none">
        <div class="ai-voice-waveform">
          <?php for ($i = 0; $i < 16; $i++) : ?>
            <span class="ai-wave-bar" style="animation-delay:<?php echo round($i * 0.06, 2); ?>s"></span>
          <?php endfor; ?>
        </div>
        <span class="ai-voice-timer" id="aiVoiceTimer">0:00</span>
        <button class="ai-voice-stop-btn" id="aiVoiceStopBtn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <rect x="4" y="4" width="16" height="16" rx="2"/>
          </svg>
          Stop
        </button>
        <button class="ai-voice-cancel-btn" id="aiVoiceCancelBtn">Cancel</button>
      </div>
      <?php endif; ?>

      <div class="ai-input-footer">
        <span class="ai-char-count" id="aiCharCount">0 / 4096</span>
        <span class="ai-input-hint">Enter to send &bull; Shift+Enter for newline</span>
      </div>
    </div>

  </div>
</div>

<!-- Widget data injection for JS -->
<script>
window.AI_WIDGET_CONFIG = {
  staffId:       <?php echo (int)get_staff_user_id(); ?>,
  staffName:     <?php echo json_encode(htmlspecialchars(($staff->firstname ?? '') . ' ' . ($staff->lastname ?? ''))); ?>,
  voiceEnabled:  <?php echo ($settings['voice_enabled'] ?? false) ? 'true' : 'false'; ?>,
  baseUrl:       <?php echo json_encode(admin_url('ai_assistant')); ?>,
  csrfToken:     <?php echo json_encode(csrf_token()); ?>,
  provider:      <?php echo json_encode($active_provider); ?>,
};
</script>
