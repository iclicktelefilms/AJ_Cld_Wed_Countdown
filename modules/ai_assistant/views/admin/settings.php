<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
  <div class="col-md-12">
    <div class="page-heading">
      <h3><?php echo lang('ai_assistant_settings'); ?></h3>
    </div>
  </div>
</div>

<div class="row">
  <!-- Left column: Core settings -->
  <div class="col-md-8">

    <!-- API Configuration -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-key"></i> API Configuration</h4>
        <hr class="hr-panel-heading" />

        <div class="form-group">
          <label><?php echo lang('ai_assistant_api_key'); ?> <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="password" class="form-control" id="api_key" name="api_key"
                   placeholder="AIza..."
                   value="<?php echo $settings['api_key'] ? str_repeat('*', 30) : ''; ?>" />
            <span class="input-group-btn">
              <button type="button" class="btn btn-default" id="toggleApiKey" title="Show/Hide">
                <i class="fa fa-eye"></i>
              </button>
            </span>
          </div>
          <p class="text-muted">
            <small>Get your key from <a href="https://aistudio.google.com" target="_blank">Google AI Studio</a>. Never share this key.</small>
          </p>
        </div>

        <div class="form-group">
          <label><?php echo lang('ai_assistant_model'); ?></label>
          <select class="form-control" name="model" id="model">
            <?php foreach ($models as $model_id => $model_label) : ?>
              <option value="<?php echo $model_id; ?>" <?php echo $settings['model'] === $model_id ? 'selected' : ''; ?>>
                <?php echo $model_label; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label><?php echo lang('ai_assistant_temperature'); ?> <small class="text-muted">(0.0 – 1.0)</small></label>
              <input type="number" class="form-control" name="temperature" id="temperature"
                     min="0" max="1" step="0.1"
                     value="<?php echo $settings['temperature']; ?>" />
              <p class="text-muted"><small>Lower = more precise; Higher = more creative</small></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label><?php echo lang('ai_assistant_max_tokens'); ?></label>
              <input type="number" class="form-control" name="max_tokens" id="max_tokens"
                     min="256" max="32768" step="256"
                     value="<?php echo $settings['max_tokens']; ?>" />
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label"><?php echo lang('ai_assistant_enabled'); ?></label>
              <div>
                <label class="switch">
                  <input type="checkbox" name="enabled" id="enabled" value="1"
                         <?php echo $settings['api_key'] ? 'checked' : ''; ?> />
                  <span class="slider round"></span>
                </label>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label"><?php echo lang('ai_assistant_streaming'); ?></label>
              <div>
                <label class="switch">
                  <input type="checkbox" name="streaming" id="streaming" value="1"
                         <?php echo $settings['streaming_enabled'] ? 'checked' : ''; ?> />
                  <span class="slider round"></span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <button type="button" class="btn btn-info" id="testConnectionBtn">
          <i class="fa fa-plug"></i> <?php echo lang('ai_test_connection'); ?>
        </button>
        <span id="connectionStatus" class="mleft5"></span>
      </div>
    </div>

    <!-- Memory & Retention -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-brain"></i> Memory & Retention</h4>
        <hr class="hr-panel-heading" />

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label><?php echo lang('ai_assistant_memory_limit'); ?></label>
              <input type="number" class="form-control" name="memory_limit" id="memory_limit"
                     min="5" max="100"
                     value="<?php echo $settings['memory_limit']; ?>" />
              <p class="text-muted"><small>Number of messages kept in AI context window</small></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label><?php echo lang('ai_assistant_retention_days'); ?></label>
              <input type="number" class="form-control" name="retention_days" id="retention_days"
                     min="1" max="365"
                     value="<?php echo $settings['retention_days']; ?>" />
              <p class="text-muted"><small>Days to keep chat history</small></p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Voice Settings -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-microphone"></i> Voice Assistant</h4>
        <hr class="hr-panel-heading" />

        <div class="form-group">
          <label class="control-label"><?php echo lang('ai_assistant_voice_enabled'); ?></label>
          <div>
            <label class="switch">
              <input type="checkbox" name="voice" id="voice" value="1"
                     <?php echo $settings['voice_enabled'] ? 'checked' : ''; ?> />
              <span class="slider round"></span>
            </label>
          </div>
          <p class="text-muted"><small>Enables voice-to-text and text-to-speech in the chat widget</small></p>
        </div>
      </div>
    </div>

    <!-- Save Button -->
    <div class="panel_s">
      <div class="panel-body">
        <button type="button" class="btn btn-primary btn-lg" id="saveSettingsBtn">
          <i class="fa fa-save"></i> <?php echo lang('ai_save_settings'); ?>
        </button>
        <span id="saveStatus" class="mleft5"></span>
      </div>
    </div>

  </div>

  <!-- Right column: Module & Permission settings -->
  <div class="col-md-4">

    <!-- Allowed Modules -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-toggle-on"></i> <?php echo lang('ai_assistant_allowed_modules'); ?></h4>
        <hr class="hr-panel-heading" />
        <?php
        $all_modules = [
            'leads'      => 'Leads',
            'clients'    => 'Clients / Customers',
            'invoices'   => 'Invoices',
            'estimates'  => 'Estimates',
            'proposals'  => 'Proposals',
            'tasks'      => 'Tasks',
            'projects'   => 'Projects',
            'tickets'    => 'Support Tickets',
            'contracts'  => 'Contracts',
            'expenses'   => 'Expenses',
            'payments'   => 'Payments',
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
        $roles = ['admin' => 'Administrator', 'manager' => 'Manager', 'staff' => 'Staff', 'viewer' => 'Viewer (Read-only)'];
        $perms_opts = ['read' => 'Read Data', 'write' => 'Write / Create', 'delete' => 'Delete', 'report' => 'Reports'];
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

    <!-- Danger Zone -->
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top text-danger"><i class="fa fa-exclamation-triangle"></i> Maintenance</h4>
        <hr class="hr-panel-heading" />
        <button type="button" class="btn btn-danger btn-sm" id="purgeDataBtn">
          <i class="fa fa-trash"></i> Purge Old Chat Data
        </button>
        <p class="text-muted mtop5"><small>Removes chat history older than retention period.</small></p>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  const base = window.AI_ASSISTANT_BASE_URL + '/admin/ai_assistant';
  const csrf = window.AI_CSRF_TOKEN;

  // Toggle API key visibility
  document.getElementById('toggleApiKey').addEventListener('click', function(){
    const inp = document.getElementById('api_key');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
  });

  // Save settings
  document.getElementById('saveSettingsBtn').addEventListener('click', function(){
    const btn    = this;
    const status = document.getElementById('saveStatus');
    btn.disabled = true;
    status.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

    const form    = new FormData();
    const apiKey  = document.getElementById('api_key').value;
    if (apiKey && !apiKey.startsWith('***')) form.append('api_key', apiKey);
    form.append('model',           document.getElementById('model').value);
    form.append('temperature',     document.getElementById('temperature').value);
    form.append('max_tokens',      document.getElementById('max_tokens').value);
    form.append('memory_limit',    document.getElementById('memory_limit').value);
    form.append('retention_days',  document.getElementById('retention_days').value);
    form.append('enabled',         document.getElementById('enabled').checked ? '1' : '0');
    form.append('streaming',       document.getElementById('streaming').checked ? '1' : '0');
    form.append('voice',           document.getElementById('voice').checked ? '1' : '0');

    // Allowed modules
    document.querySelectorAll('[name="allowed_modules[]"]:checked').forEach(el => {
      form.append('allowed_modules[]', el.value);
    });

    // Tool permissions
    document.querySelectorAll('[name^="tool_permissions"]').forEach(el => {
      if (el.checked) form.append(el.name, el.value);
    });

    form.append('csrf_token', csrf);

    fetch(base + '/save_settings', { method: 'POST', body: form })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          status.innerHTML = '<span class="text-success"><i class="fa fa-check"></i> ' + data.message + '</span>';
        } else {
          status.innerHTML = '<span class="text-danger"><i class="fa fa-times"></i> Failed</span>';
        }
      })
      .catch(() => {
        status.innerHTML = '<span class="text-danger">Network error</span>';
      })
      .finally(() => { btn.disabled = false; });
  });

  // Test connection
  document.getElementById('testConnectionBtn').addEventListener('click', function(){
    const btn    = this;
    const status = document.getElementById('connectionStatus');
    btn.disabled = true;
    status.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';

    fetch(base + '/test_connection', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        status.innerHTML = '<span class="text-success"><i class="fa fa-check-circle"></i> <?php echo lang("ai_connection_ok"); ?> (' + data.model + ')</span>';
      } else {
        status.innerHTML = '<span class="text-danger"><i class="fa fa-times-circle"></i> <?php echo lang("ai_connection_failed"); ?>' + data.error + '</span>';
      }
    })
    .catch(() => { status.innerHTML = '<span class="text-danger">Network error</span>'; })
    .finally(() => { btn.disabled = false; });
  });

  // Purge old data
  document.getElementById('purgeDataBtn').addEventListener('click', function(){
    if (!confirm('Purge old chat history? This cannot be undone.')) return;
    const btn = this;
    btn.disabled = true;

    fetch(base + '/purge_old_data', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => { alert(data.message); })
    .finally(() => { btn.disabled = false; });
  });
})();
</script>
