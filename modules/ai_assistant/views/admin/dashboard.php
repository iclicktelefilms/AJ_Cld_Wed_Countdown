<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
  <div class="col-md-12">
    <div class="page-heading">
      <h3><?php echo lang('ai_assistant_dashboard'); ?>
        <small>
          <a href="<?php echo admin_url('ai_assistant/settings'); ?>" class="btn btn-sm btn-default">
            <i class="fa fa-cog"></i> <?php echo lang('ai_assistant_settings'); ?>
          </a>
          <a href="<?php echo admin_url('ai_assistant/logs'); ?>" class="btn btn-sm btn-default mleft5">
            <i class="fa fa-list"></i> <?php echo lang('ai_audit_log'); ?>
          </a>
        </small>
      </h3>
    </div>
  </div>
</div>

<!-- Period selector -->
<div class="row">
  <div class="col-md-12">
    <div class="btn-group mtop5 mbottom15">
      <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $p => $label) : ?>
        <a href="?period=<?php echo $p; ?>"
           class="btn btn-sm <?php echo $period === $p ? 'btn-primary' : 'btn-default'; ?>">
          <?php echo $label; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- KPI Cards -->
<div class="row">
  <div class="col-md-3 col-sm-6">
    <div class="panel_s panel-stats">
      <div class="panel-body">
        <div class="row">
          <div class="col-xs-4 text-center">
            <i class="fa fa-comments fa-3x text-primary"></i>
          </div>
          <div class="col-xs-8">
            <p class="stats-num"><?php echo number_format($stats['total_messages']); ?></p>
            <p class="stats-label"><?php echo lang('ai_total_messages'); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="panel_s panel-stats">
      <div class="panel-body">
        <div class="row">
          <div class="col-xs-4 text-center">
            <i class="fa fa-bolt fa-3x text-success"></i>
          </div>
          <div class="col-xs-8">
            <p class="stats-num"><?php echo number_format($stats['total_actions']); ?></p>
            <p class="stats-label"><?php echo lang('ai_total_actions'); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="panel_s panel-stats">
      <div class="panel-body">
        <div class="row">
          <div class="col-xs-4 text-center">
            <i class="fa fa-database fa-3x text-info"></i>
          </div>
          <div class="col-xs-8">
            <p class="stats-num"><?php echo number_format($stats['total_tokens']); ?></p>
            <p class="stats-label"><?php echo lang('ai_tokens_consumed'); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="panel_s panel-stats">
      <div class="panel-body">
        <div class="row">
          <div class="col-xs-4 text-center">
            <i class="fa fa-exclamation-circle fa-3x text-danger"></i>
          </div>
          <div class="col-xs-8">
            <p class="stats-num"><?php echo number_format($stats['failed_requests']); ?></p>
            <p class="stats-label"><?php echo lang('ai_failed_requests'); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Usage Trend Chart -->
  <div class="col-md-8">
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top">
          <i class="fa fa-bar-chart text-primary"></i>
          Daily Message Volume <small class="text-muted">(Last 30 Days)</small>
        </h4>
        <hr class="hr-panel-heading" />
        <canvas id="dailyTrendChart" height="110"></canvas>
        <p id="dailyTrendEmpty" class="text-center text-muted" style="display:none">No activity data for this period.</p>
      </div>
    </div>
  </div>

  <!-- Top Tools -->
  <div class="col-md-4">
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top">
          <i class="fa fa-wrench text-success"></i>
          <?php echo lang('ai_most_used_tools'); ?>
        </h4>
        <hr class="hr-panel-heading" />
        <?php if (!empty($stats['top_tools'])) : ?>
          <?php
          $max_usage = (int)($stats['top_tools'][0]['usage_count'] ?? 1);
          foreach (array_slice($stats['top_tools'], 0, 8) as $tool) :
            $pct = $max_usage > 0 ? min(100, round($tool['usage_count'] / $max_usage * 100)) : 0;
          ?>
            <div class="mbottom8">
              <div class="clearfix mbottom2">
                <span class="pull-left text-small">
                  <code style="font-size:11px"><?php echo htmlspecialchars($tool['tool_used']); ?></code>
                </span>
                <span class="pull-right text-muted" style="font-size:12px">
                  <strong><?php echo (int)$tool['usage_count']; ?></strong>x
                </span>
              </div>
              <div class="progress" style="height:5px;margin-bottom:0;border-radius:3px">
                <div class="progress-bar progress-bar-primary" style="width:<?php echo $pct; ?>%;border-radius:3px"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else : ?>
          <p class="text-muted text-center">
            <i class="fa fa-inbox fa-2x text-muted mbottom5" style="display:block"></i>
            No tool usage data yet.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Second row: Provider info + Quick Links -->
<div class="row">
  <!-- Provider Configuration Status -->
  <div class="col-md-4">
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top">
          <i class="fa fa-plug text-warning"></i> Provider Status
        </h4>
        <hr class="hr-panel-heading" />
        <?php
        $active_provider = get_option('ai_assistant_active_provider') ?: 'gemini';
        $providers = ['gemini' => 'Gemini', 'openai' => 'OpenAI', 'claude' => 'Claude', 'openrouter' => 'OpenRouter', 'ollama' => 'Ollama'];
        foreach ($providers as $slug => $label) :
            $key_option = $slug === 'ollama'
                ? get_option('ai_assistant_ollama_base_url')
                : get_option("ai_assistant_{$slug}_api_key");
            $is_configured = !empty($key_option);
            $is_active = $slug === $active_provider;
        ?>
          <div class="clearfix mbottom5">
            <span class="pull-left">
              <?php if ($is_active) : ?>
                <strong><?php echo htmlspecialchars($label); ?></strong>
                <span class="label label-primary" style="font-size:10px;margin-left:4px">Active</span>
              <?php else : ?>
                <span class="text-muted"><?php echo htmlspecialchars($label); ?></span>
              <?php endif; ?>
            </span>
            <span class="pull-right">
              <?php if ($is_configured) : ?>
                <span class="text-success"><i class="fa fa-check-circle"></i> Configured</span>
              <?php else : ?>
                <span class="text-muted"><i class="fa fa-times-circle"></i> Not set</span>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
        <hr style="margin:12px 0 8px">
        <a href="<?php echo admin_url('ai_assistant/test_connection'); ?>" class="btn btn-sm btn-default btn-block" id="dashTestConn">
          <i class="fa fa-wifi"></i> Test Active Connection
        </a>
        <div id="dashTestResult" style="margin-top:8px;display:none"></div>
      </div>
    </div>
  </div>

  <!-- Quick Links -->
  <div class="col-md-4">
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top">
          <i class="fa fa-link text-info"></i> Quick Links
        </h4>
        <hr class="hr-panel-heading" />
        <div class="list-group" style="margin-bottom:0">
          <a href="<?php echo admin_url('ai_assistant/settings'); ?>" class="list-group-item">
            <i class="fa fa-cog fa-fw text-primary"></i> Module Settings
          </a>
          <a href="<?php echo admin_url('ai_assistant/logs'); ?>" class="list-group-item">
            <i class="fa fa-history fa-fw text-warning"></i> Audit Logs
          </a>
          <a href="#" class="list-group-item" id="dashPurgeBtn">
            <i class="fa fa-trash fa-fw text-danger"></i> Purge Old Data
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Summary numbers -->
  <div class="col-md-4">
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top">
          <i class="fa fa-info-circle text-primary"></i> Module Info
        </h4>
        <hr class="hr-panel-heading" />
        <table class="table table-condensed" style="margin-bottom:0">
          <tr>
            <td class="text-muted">Module Version</td>
            <td><strong><?php echo defined('AI_ASSISTANT_VERSION') ? AI_ASSISTANT_VERSION : '1.0.0'; ?></strong></td>
          </tr>
          <tr>
            <td class="text-muted">Active Provider</td>
            <td><strong><?php echo htmlspecialchars($providers[$active_provider] ?? ucfirst($active_provider)); ?></strong></td>
          </tr>
          <tr>
            <td class="text-muted">Memory Limit</td>
            <td><strong><?php echo (int)(get_option('ai_assistant_memory_limit') ?: 20); ?> messages</strong></td>
          </tr>
          <tr>
            <td class="text-muted">Data Retention</td>
            <td><strong><?php echo (int)(get_option('ai_assistant_retention_days') ?: 30); ?> days</strong></td>
          </tr>
          <tr>
            <td class="text-muted">Voice Enabled</td>
            <td>
              <?php if (get_option('ai_assistant_voice') !== '0') : ?>
                <span class="text-success"><i class="fa fa-check"></i> Yes</span>
              <?php else : ?>
                <span class="text-muted"><i class="fa fa-times"></i> No</span>
              <?php endif; ?>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Recent Action Logs -->
<div class="row">
  <div class="col-md-12">
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top">
          <i class="fa fa-history"></i> <?php echo lang('ai_recent_actions'); ?>
          <a href="<?php echo admin_url('ai_assistant/logs'); ?>" class="btn btn-xs btn-default pull-right">
            View All
          </a>
        </h4>
        <hr class="hr-panel-heading" />
        <div class="table-responsive">
          <table class="table table-hover table-striped" style="font-size:13px">
            <thead>
              <tr>
                <th>Staff</th>
                <th>Tool Used</th>
                <th>Status</th>
                <th>IP Address</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recent_logs)) : ?>
                <?php foreach ($recent_logs as $log) : ?>
                  <tr>
                    <td>
                      <?php
                      $staff_name = $log['staff_name'] ?? '';
                      if (empty($staff_name) && !empty($log['firstname'])) {
                          $staff_name = $log['firstname'] . ' ' . $log['lastname'];
                      }
                      echo htmlspecialchars(trim($staff_name) ?: 'Unknown');
                      ?>
                    </td>
                    <td>
                      <code style="font-size:11px"><?php echo htmlspecialchars($log['tool_used'] ?? $log['action_name']); ?></code>
                    </td>
                    <td>
                      <?php
                      $status_class = [
                          'success' => 'success',
                          'error'   => 'danger',
                          'blocked' => 'warning',
                          'pending' => 'info',
                      ];
                      $cls = $status_class[$log['status']] ?? 'default';
                      ?>
                      <span class="label label-<?php echo $cls; ?>"><?php echo htmlspecialchars($log['status']); ?></span>
                    </td>
                    <td><small class="text-muted"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></small></td>
                    <td><small><?php echo date('d M y H:i', strtotime($log['created_at'])); ?></small></td>
                  </tr>
                <?php endforeach; ?>
              <?php else : ?>
                <tr>
                  <td colspan="5" class="text-center text-muted" style="padding:24px">
                    <i class="fa fa-inbox fa-2x mbottom5" style="display:block"></i>
                    No actions logged yet. Start using the AI assistant to see activity here.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
  'use strict';

  var dailyTrend = <?php echo json_encode($stats['daily_trend'] ?? []); ?>;

  if (dailyTrend.length > 0) {
    var labels = dailyTrend.map(function(r) {
      var d = new Date(r.date);
      return d.toLocaleDateString('en-US', {month:'short', day:'numeric'});
    });
    var data = dailyTrend.map(function(r) { return parseInt(r.count, 10); });

    new Chart(document.getElementById('dailyTrendChart'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Messages',
          data: data,
          backgroundColor: 'rgba(66, 133, 244, 0.6)',
          borderColor: 'rgba(66, 133, 244, 1)',
          borderWidth: 1,
          borderRadius: 4,
          hoverBackgroundColor: 'rgba(66, 133, 244, 0.85)',
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx) { return ' ' + ctx.raw + ' messages'; }
            }
          }
        },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
          x: { ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 14 } }
        }
      }
    });
  } else {
    document.getElementById('dailyTrendChart').style.display = 'none';
    document.getElementById('dailyTrendEmpty').style.display = 'block';
  }

  // Test connection button
  var testBtn = document.getElementById('dashTestConn');
  if (testBtn) {
    testBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var resultEl = document.getElementById('dashTestResult');
      testBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';
      testBtn.disabled = true;

      fetch('<?php echo admin_url('ai_assistant/test_connection'); ?>', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'csrf_token=' + encodeURIComponent('<?php echo csrf_token(); ?>')
      })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        resultEl.style.display = 'block';
        if (d.success) {
          resultEl.innerHTML = '<div class="alert alert-success" style="padding:6px 10px;margin:0;font-size:12px">'
            + '<i class="fa fa-check"></i> Connected! Model: <strong>' + (d.model || '') + '</strong>'
            + ' (' + (d.latency_ms || 0) + 'ms)'
            + '</div>';
        } else {
          resultEl.innerHTML = '<div class="alert alert-danger" style="padding:6px 10px;margin:0;font-size:12px">'
            + '<i class="fa fa-times"></i> Failed: ' + (d.error || 'Unknown error')
            + '</div>';
        }
      })
      .catch(function(err) {
        resultEl.style.display = 'block';
        resultEl.innerHTML = '<div class="alert alert-danger" style="padding:6px 10px;margin:0;font-size:12px">'
          + 'Request failed: ' + err.message
          + '</div>';
      })
      .finally(function() {
        testBtn.innerHTML = '<i class="fa fa-wifi"></i> Test Active Connection';
        testBtn.disabled = false;
      });
    });
  }

  // Purge old data button
  var purgeBtn = document.getElementById('dashPurgeBtn');
  if (purgeBtn) {
    purgeBtn.addEventListener('click', function(e) {
      e.preventDefault();
      if (!confirm('Purge old chat history based on retention settings? This cannot be undone.')) return;

      fetch('<?php echo admin_url('ai_assistant/purge_old_data'); ?>', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'csrf_token=' + encodeURIComponent('<?php echo csrf_token(); ?>')
      })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.status === 'success') {
          alert('Purged ' + (d.deleted || 0) + ' old records.');
        } else {
          alert('Purge failed: ' + (d.message || 'Unknown error'));
        }
      })
      .catch(function(err) { alert('Request failed: ' + err.message); });
    });
  }

})();
</script>
