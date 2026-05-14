<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
// Capability icon map
$cap_icons = [
    'chat'         => ['fa-comments',   'Chat'],
    'tool_calling' => ['fa-wrench',     'Tool Calling'],
    'streaming'    => ['fa-bolt',       'Streaming'],
    'vision'       => ['fa-eye',        'Vision'],
    'audio'        => ['fa-microphone', 'Audio / STT'],
    'tts'          => ['fa-volume-up',  'Text-to-Speech'],
];

$active_provider   = $settings['active_provider'] ?? 'gemini';
$fallback_provider = $settings['fallback_provider'] ?? 'none';
$provider_slugs    = array_keys($providers_meta);
?>

<div class="row">
  <div class="col-md-12">
    <div class="page-heading">
      <h3><?php echo lang('ai_assistant_settings'); ?></h3>
    </div>
  </div>
</div>

<div class="row">

  <!-- ── Left column: AI provider + general settings ──────────────────── -->
  <div class="col-md-8">

    <!-- Active Provider Selection -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-robot"></i> AI Provider</h4>
        <hr class="hr-panel-heading" />

        <div class="form-group">
          <label>Active Provider <span class="text-danger">*</span></label>
          <div class="row" id="providerCards">
            <?php foreach ($providers_meta as $slug => $meta) :
              $is_active = ($slug === $active_provider);
              $has_key   = !empty($settings['providers'][$slug]['api_key']);
              $configured_cls = $has_key ? 'border-success' : '';
            ?>
            <div class="col-sm-4 col-xs-6 mbot10">
              <label class="provider-card <?php echo $is_active ? 'selected' : ''; ?>"
                     data-provider="<?php echo $slug; ?>">
                <input type="radio" name="active_provider" value="<?php echo $slug; ?>"
                       <?php echo $is_active ? 'checked' : ''; ?> style="display:none" />
                <div class="provider-card-inner">
                  <strong><?php echo htmlspecialchars($meta['label']); ?></strong>
                  <small class="text-muted d-block"><?php echo htmlspecialchars($meta['company']); ?></small>
                  <?php if ($has_key || $slug === 'ollama') : ?>
                    <span class="label label-success label-xs mtop5">Configured</span>
                  <?php else : ?>
                    <span class="label label-default label-xs mtop5">Not set</span>
                  <?php endif; ?>
                </div>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Per-provider config tabs -->
        <ul class="nav nav-tabs" id="providerTabs" role="tablist">
          <?php foreach ($providers_meta as $slug => $meta) : ?>
          <li role="presentation" class="<?php echo $slug === $active_provider ? 'active' : ''; ?>">
            <a href="#tab-<?php echo $slug; ?>" aria-controls="tab-<?php echo $slug; ?>"
               role="tab" data-toggle="tab" data-provider="<?php echo $slug; ?>">
              <?php echo htmlspecialchars($meta['label']); ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>

        <div class="tab-content" style="padding-top:15px">
          <?php foreach ($providers_meta as $slug => $meta) :
            $p_settings     = $settings['providers'][$slug] ?? [];
            $saved_model    = $p_settings['model'] ?? $meta['default_model'];
            $saved_api_key  = $p_settings['api_key'] ?? '';
            $masked_key     = $saved_api_key ? str_repeat('*', 28) : '';
            $is_free_text   = !empty($meta['model_is_free_text']);
            $is_active_tab  = ($slug === $active_provider);
          ?>
          <div role="tabpanel"
               class="tab-pane <?php echo $is_active_tab ? 'active' : ''; ?>"
               id="tab-<?php echo $slug; ?>">

            <!-- Description + capabilities -->
            <p class="text-muted"><?php echo htmlspecialchars($meta['description']); ?></p>
            <div class="mbot10">
              <?php foreach ($meta['capabilities'] as $cap) :
                [$icon, $label] = $cap_icons[$cap] ?? ["fa-check", $cap];
              ?>
                <span class="label label-info mleft0 mright3">
                  <i class="fa <?php echo $icon; ?>"></i> <?php echo $label; ?>
                </span>
              <?php endforeach; ?>
            </div>

            <!-- API key -->
            <?php if ($meta['requires_key']) : ?>
            <div class="form-group">
              <label>API Key</label>
              <div class="input-group">
                <input type="password" class="form-control provider-api-key"
                       id="<?php echo $slug; ?>_api_key"
                       name="<?php echo $slug; ?>_api_key"
                       placeholder="<?php echo htmlspecialchars($meta['key_placeholder']); ?>"
                       value="<?php echo $masked_key; ?>"
                       autocomplete="new-password" />
                <span class="input-group-btn">
                  <button type="button" class="btn btn-default btn-toggle-key"
                          data-target="<?php echo $slug; ?>_api_key" title="Show / Hide">
                    <i class="fa fa-eye"></i>
                  </button>
                </span>
              </div>
              <p class="text-muted">
                <small>Get your key from
                  <a href="<?php echo htmlspecialchars($meta['docs_url']); ?>" target="_blank" rel="noopener">
                    <?php echo htmlspecialchars($meta['company']); ?> Console
                  </a>. Never share this key.
                </small>
              </p>
            </div>
            <?php else : ?>
            <div class="form-group">
              <label>API Key</label>
              <input type="text" class="form-control" disabled
                     placeholder="Not required for Ollama" />
            </div>
            <?php endif; ?>

            <!-- Base URL (Ollama only) -->
            <?php if ($meta['requires_url']) :
              $saved_url = $p_settings['base_url'] ?? $meta['default_url'];
            ?>
            <div class="form-group">
              <label>Base URL <span class="text-danger">*</span></label>
              <input type="url" class="form-control"
                     id="<?php echo $slug; ?>_base_url"
                     name="<?php echo $slug; ?>_base_url"
                     placeholder="<?php echo htmlspecialchars($meta['url_placeholder']); ?>"
                     value="<?php echo htmlspecialchars($saved_url); ?>" />
              <p class="text-muted"><small>URL where your <?php echo htmlspecialchars($meta['label']); ?> instance is running.</small></p>
            </div>
            <?php endif; ?>

            <!-- Model selection -->
            <div class="form-group">
              <label>Model</label>
              <?php if ($is_free_text) : ?>
                <input type="text" class="form-control"
                       id="<?php echo $slug; ?>_model"
                       name="<?php echo $slug; ?>_model"
                       value="<?php echo htmlspecialchars($saved_model); ?>"
                       placeholder="<?php echo htmlspecialchars($meta['model_hint'] ?? ''); ?>" />
                <?php if (!empty($meta['model_hint'])) : ?>
                  <p class="text-muted"><small><?php echo htmlspecialchars($meta['model_hint']); ?></small></p>
                <?php endif; ?>
              <?php else : ?>
                <select class="form-control"
                        id="<?php echo $slug; ?>_model"
                        name="<?php echo $slug; ?>_model">
                  <?php foreach ($meta['models'] as $m) : ?>
                    <option value="<?php echo htmlspecialchars($m['id']); ?>"
                            <?php echo $saved_model === $m['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($m['label']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>

            <!-- Test connection -->
            <button type="button" class="btn btn-info btn-sm btn-test-connection"
                    data-provider="<?php echo $slug; ?>">
              <i class="fa fa-plug"></i> Test Connection
            </button>
            <span class="connection-status mleft5" id="status-<?php echo $slug; ?>"></span>

          </div><!-- /.tab-pane -->
          <?php endforeach; ?>
        </div><!-- /.tab-content -->

      </div><!-- /.panel-body -->
    </div><!-- /.panel_s -->

    <!-- General Settings -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-sliders"></i> General Settings</h4>
        <hr class="hr-panel-heading" />

        <!-- Fallback provider -->
        <div class="form-group">
          <label>Fallback Provider</label>
          <select class="form-control" name="fallback_provider" id="fallback_provider">
            <option value="none" <?php echo $fallback_provider === 'none' ? 'selected' : ''; ?>>None (disabled)</option>
            <?php foreach ($providers_meta as $slug => $meta) : ?>
            <option value="<?php echo $slug; ?>"
                    <?php echo $fallback_provider === $slug ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($meta['label']); ?>
            </option>
            <?php endforeach; ?>
          </select>
          <p class="text-muted"><small>Used when the active provider is unavailable.</small></p>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label><?php echo lang('ai_assistant_temperature'); ?> <small class="text-muted">(0.0 – 1.0)</small></label>
              <input type="number" class="form-control" name="temperature" id="temperature"
                     min="0" max="1" step="0.1"
                     value="<?php echo htmlspecialchars((string)$settings['temperature']); ?>" />
              <p class="text-muted"><small>Lower = more precise; Higher = more creative</small></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label><?php echo lang('ai_assistant_max_tokens'); ?></label>
              <input type="number" class="form-control" name="max_tokens" id="max_tokens"
                     min="256" max="32768" step="256"
                     value="<?php echo htmlspecialchars((string)$settings['max_tokens']); ?>" />
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label"><?php echo lang('ai_assistant_enabled'); ?></label>
              <div>
                <label class="switch">
                  <input type="checkbox" name="enabled" id="enabled" value="1"
                         <?php echo !empty($settings['api_key']) ? 'checked' : ''; ?> />
                  <span class="slider round"></span>
                </label>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label"><?php echo lang('ai_assistant_streaming'); ?></label>
              <div>
                <label class="switch">
                  <input type="checkbox" name="streaming" id="streaming" value="1"
                         <?php echo !empty($settings['streaming_enabled']) ? 'checked' : ''; ?> />
                  <span class="slider round"></span>
                </label>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label"><?php echo lang('ai_assistant_voice_enabled'); ?></label>
              <div>
                <label class="switch">
                  <input type="checkbox" name="voice" id="voice" value="1"
                         <?php echo !empty($settings['voice_enabled']) ? 'checked' : ''; ?> />
                  <span class="slider round"></span>
                </label>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Memory & Retention -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-brain"></i> Memory &amp; Retention</h4>
        <hr class="hr-panel-heading" />

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label><?php echo lang('ai_assistant_memory_limit'); ?></label>
              <input type="number" class="form-control" name="memory_limit" id="memory_limit"
                     min="5" max="100"
                     value="<?php echo htmlspecialchars((string)$settings['memory_limit']); ?>" />
              <p class="text-muted"><small>Messages kept in AI context window</small></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label><?php echo lang('ai_assistant_retention_days'); ?></label>
              <input type="number" class="form-control" name="retention_days" id="retention_days"
                     min="1" max="365"
                     value="<?php echo htmlspecialchars((string)$settings['retention_days']); ?>" />
              <p class="text-muted"><small>Days to keep chat history</small></p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Save -->
    <div class="panel_s">
      <div class="panel-body">
        <button type="button" class="btn btn-primary btn-lg" id="saveSettingsBtn">
          <i class="fa fa-save"></i> <?php echo lang('ai_save_settings'); ?>
        </button>
        <span id="saveStatus" class="mleft5"></span>
      </div>
    </div>

  </div><!-- /.col-md-8 -->

  <!-- ── Right column: Modules + Permissions + Maintenance ──────────────── -->
  <div class="col-md-4">

    <!-- Allowed Modules -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-toggle-on"></i> <?php echo lang('ai_assistant_allowed_modules'); ?></h4>
        <hr class="hr-panel-heading" />
        <?php
        $all_modules = [
            'leads'     => 'Leads',
            'clients'   => 'Clients / Customers',
            'invoices'  => 'Invoices',
            'estimates' => 'Estimates',
            'proposals' => 'Proposals',
            'tasks'     => 'Tasks',
            'projects'  => 'Projects',
            'tickets'   => 'Support Tickets',
            'contracts' => 'Contracts',
            'expenses'  => 'Expenses',
            'payments'  => 'Payments',
        ];
        $allowed = $settings['allowed_modules'] ?? [];
        foreach ($all_modules as $module_key => $module_name) :
        ?>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="allowed_modules[]" value="<?php echo $module_key; ?>"
                     <?php echo in_array($module_key, $allowed) ? 'checked' : ''; ?> />
              <?php echo $module_name; ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Tool Permissions -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-lock"></i> <?php echo lang('ai_assistant_tool_permissions'); ?></h4>
        <hr class="hr-panel-heading" />
        <p class="text-muted"><small>Define what each role can do with AI tools.</small></p>
        <?php
        $roles = [
            'admin'   => 'Administrator',
            'manager' => 'Manager',
            'staff'   => 'Staff',
            'viewer'  => 'Viewer (Read-only)',
        ];
        $perms_opts = [
            'read'   => 'Read Data',
            'write'  => 'Write / Create',
            'delete' => 'Delete',
            'report' => 'Reports',
        ];
        $current_perms = $settings['tool_permissions'] ?? [];
        foreach ($roles as $role_key => $role_name) :
            $role_perms = $current_perms[$role_key] ?? [];
        ?>
          <div class="mtop10">
            <strong><?php echo $role_name; ?></strong>
            <?php foreach ($perms_opts as $perm_key => $perm_label) : ?>
              <div class="checkbox">
                <label>
                  <input type="checkbox"
                         name="tool_permissions[<?php echo $role_key; ?>][]"
                         value="<?php echo $perm_key; ?>"
                         <?php echo in_array($perm_key, $role_perms) ? 'checked' : ''; ?> />
                  <?php echo $perm_label; ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <hr />
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Maintenance -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top text-danger"><i class="fa fa-exclamation-triangle"></i> Maintenance</h4>
        <hr class="hr-panel-heading" />
        <button type="button" class="btn btn-danger btn-sm" id="purgeDataBtn">
          <i class="fa fa-trash"></i> Purge Old Chat Data
        </button>
        <p class="text-muted mtop5"><small>Removes chat history older than the retention period.</small></p>
      </div>
    </div>

  </div><!-- /.col-md-4 -->

</div><!-- /.row -->

<style>
.provider-card {
  display: block;
  border: 2px solid #ddd;
  border-radius: 6px;
  padding: 10px 8px;
  cursor: pointer;
  text-align: center;
  transition: border-color 0.2s, background 0.2s;
  margin-bottom: 0;
  width: 100%;
  font-weight: normal;
}
.provider-card:hover {
  border-color: #5bc0de;
  background: #f0faff;
}
.provider-card.selected {
  border-color: #337ab7;
  background: #eaf3fd;
}
.provider-card-inner strong { display: block; font-size: 13px; }
.provider-card-inner small  { font-size: 11px; }
.label-xs { font-size: 10px; padding: 2px 5px; display: inline-block; margin-top: 4px; }
.mleft0 { margin-left: 0 !important; }
.mright3 { margin-right: 3px; }
</style>

<script>
(function(){
  'use strict';

  const base = window.AI_ASSISTANT_BASE_URL + '/admin/ai_assistant';
  const csrf = window.AI_CSRF_TOKEN;

  // ── Provider card selection ───────────────────────────────────────────────
  document.querySelectorAll('.provider-card').forEach(function(card) {
    card.addEventListener('click', function() {
      document.querySelectorAll('.provider-card').forEach(function(c) { c.classList.remove('selected'); });
      this.classList.add('selected');
      this.querySelector('input[type=radio]').checked = true;

      // Switch to corresponding tab
      var slug = this.dataset.provider;
      var tabLink = document.querySelector('#providerTabs a[data-provider="' + slug + '"]');
      if (tabLink) tabLink.click();
    });
  });

  // Sync tab click → highlight provider card
  document.querySelectorAll('#providerTabs a[data-toggle="tab"]').forEach(function(tab) {
    tab.addEventListener('shown.bs.tab', function() {
      var slug = this.dataset.provider;
      document.querySelectorAll('.provider-card').forEach(function(c) { c.classList.remove('selected'); });
      var card = document.querySelector('.provider-card[data-provider="' + slug + '"]');
      if (card) {
        card.classList.add('selected');
        card.querySelector('input[type=radio]').checked = true;
      }
    });
  });

  // ── Toggle API key visibility ─────────────────────────────────────────────
  document.querySelectorAll('.btn-toggle-key').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var inp = document.getElementById(this.dataset.target);
      if (!inp) return;
      inp.type = inp.type === 'password' ? 'text' : 'password';
      this.querySelector('i').classList.toggle('fa-eye');
      this.querySelector('i').classList.toggle('fa-eye-slash');
    });
  });

  // ── Test connection (per-provider) ────────────────────────────────────────
  document.querySelectorAll('.btn-test-connection').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var provider = this.dataset.provider;
      var statusEl = document.getElementById('status-' + provider);
      btn.disabled = true;
      statusEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';

      var form = new FormData();
      form.append('provider', provider);
      form.append('csrf_token', csrf);

      fetch(base + '/test_connection', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: form
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          statusEl.innerHTML = '<span class="text-success"><i class="fa fa-check-circle"></i> Connected'
            + (data.model ? ' — ' + data.model : '') + '</span>';
        } else {
          statusEl.innerHTML = '<span class="text-danger"><i class="fa fa-times-circle"></i> '
            + (data.error || 'Failed') + '</span>';
        }
      })
      .catch(function() {
        statusEl.innerHTML = '<span class="text-danger"><i class="fa fa-times-circle"></i> Network error</span>';
      })
      .finally(function() { btn.disabled = false; });
    });
  });

  // ── Save all settings ─────────────────────────────────────────────────────
  document.getElementById('saveSettingsBtn').addEventListener('click', function() {
    var btn    = this;
    var status = document.getElementById('saveStatus');
    btn.disabled = true;
    status.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

    var form = new FormData();

    // Active provider
    var activeRadio = document.querySelector('input[name="active_provider"]:checked');
    if (activeRadio) form.append('active_provider', activeRadio.value);

    // Fallback provider
    form.append('fallback_provider', document.getElementById('fallback_provider').value);

    // General
    form.append('temperature',    document.getElementById('temperature').value);
    form.append('max_tokens',     document.getElementById('max_tokens').value);
    form.append('memory_limit',   document.getElementById('memory_limit').value);
    form.append('retention_days', document.getElementById('retention_days').value);
    form.append('enabled',    document.getElementById('enabled').checked    ? '1' : '0');
    form.append('streaming',  document.getElementById('streaming').checked  ? '1' : '0');
    form.append('voice',      document.getElementById('voice').checked      ? '1' : '0');

    // Per-provider keys + models
    <?php foreach ($provider_slugs as $slug) : ?>
    (function() {
      var keyEl   = document.getElementById('<?php echo $slug; ?>_api_key');
      var modelEl = document.getElementById('<?php echo $slug; ?>_model');
      var urlEl   = document.getElementById('<?php echo $slug; ?>_base_url');
      if (keyEl && keyEl.value && !keyEl.value.startsWith('***'))
        form.append('<?php echo $slug; ?>_api_key', keyEl.value.trim());
      if (modelEl && modelEl.value)
        form.append('<?php echo $slug; ?>_model', modelEl.value.trim());
      if (urlEl && urlEl.value)
        form.append('<?php echo $slug; ?>_base_url', urlEl.value.trim());
    })();
    <?php endforeach; ?>

    // Allowed modules
    document.querySelectorAll('[name="allowed_modules[]"]:checked').forEach(function(el) {
      form.append('allowed_modules[]', el.value);
    });

    // Tool permissions
    document.querySelectorAll('[name^="tool_permissions"]:checked').forEach(function(el) {
      form.append(el.name, el.value);
    });

    form.append('csrf_token', csrf);

    fetch(base + '/save_settings', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: form
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.status === 'success') {
        status.innerHTML = '<span class="text-success"><i class="fa fa-check"></i> ' + data.message + '</span>';
      } else {
        status.innerHTML = '<span class="text-danger"><i class="fa fa-times"></i> Save failed</span>';
      }
    })
    .catch(function() {
      status.innerHTML = '<span class="text-danger">Network error</span>';
    })
    .finally(function() { btn.disabled = false; });
  });

  // ── Purge old data ────────────────────────────────────────────────────────
  document.getElementById('purgeDataBtn').addEventListener('click', function() {
    if (!confirm('Purge old chat history? This cannot be undone.')) return;
    var btn = this;
    btn.disabled = true;

    var form = new FormData();
    form.append('csrf_token', csrf);

    fetch(base + '/purge_old_data', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: form
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { alert(data.message); })
    .finally(function() { btn.disabled = false; });
  });

})();
</script>
