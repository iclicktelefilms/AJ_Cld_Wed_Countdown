<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
  <div class="col-md-12">
    <div class="page-heading">
      <h3><?php echo lang('ai_assistant_dashboard'); ?>
        <small>
          <a href="<?php echo admin_url('ai_assistant/settings'); ?>" class="btn btn-sm btn-default">
            <i class="fa fa-cog"></i> Settings
          </a>
          <a href="<?php echo admin_url('ai_assistant/logs'); ?>" class="btn btn-sm btn-default mleft5">
            <i class="fa fa-list"></i> Audit Logs
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
  <div class="col-md-3">
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
  <div class="col-md-3">
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
  <div class="col-md-3">
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
  <div class="col-md-3">
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
        <h4 class="no-margin-top"><i class="fa fa-bar-chart"></i> Daily Message Volume (Last 30 Days)</h4>
        <hr class="hr-panel-heading" />
        <canvas id="dailyTrendChart" height="120"></canvas>
      </div>
    </div>
  </div>

  <!-- Top Tools -->
  <div class="col-md-4">
    <div class="panel_s">
      <div class="panel-body">
        <h4 class="no-margin-top"><i class="fa fa-wrench"></i> <?php echo lang('ai_most_used_tools'); ?></h4>
        <hr class="hr-panel-heading" />
        <?php if (!empty($stats['top_tools'])) : ?>
          <?php foreach (array_slice($stats['top_tools'], 0, 8) as $tool) : ?>
            <div class="mbottom5">
              <div class="clearfix">
                <span class="pull-left"><small><?php echo htmlspecialchars($tool['tool_used']); ?></small></span>
                <span class="pull-right"><strong><?php echo $tool['usage_count']; ?></strong></span>
              </div>
              <div class="progress" style="height:6px;margin-bottom:0;">
                <?php
                  $max  = $stats['top_tools'][0]['usage_count'] ?? 1;
                  $pct  = min(100, round($tool['usage_count'] / $max * 100));
                ?>
                <div class="progress-bar progress-bar-primary" style="width:<?php echo $pct; ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else : ?>
          <p class="text-muted">No tool usage data yet.</p>
        <?php endif; ?>
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
          <table class="table table-hover table-striped">
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
                    <td><?php echo htmlspecialchars($log['staff_name'] ?? ($log['firstname'] . ' ' . $log['lastname'])); ?></td>
                    <td><code><?php echo htmlspecialchars($log['tool_used'] ?? $log['action_name']); ?></code></td>
                    <td>
                      <?php
                      $status_class = ['success' => 'success', 'error' => 'danger', 'blocked' => 'warning', 'pending' => 'info'];
                      $cls = $status_class[$log['status']] ?? 'default';
                      ?>
                      <span class="label label-<?php echo $cls; ?>"><?php echo htmlspecialchars($log['status']); ?></span>
                    </td>
                    <td><small><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></small></td>
                    <td><small><?php echo date('d M y H:i', strtotime($log['created_at'])); ?></small></td>
                  </tr>
                <?php endforeach; ?>
              <?php else : ?>
                <tr><td colspan="5" class="text-center text-muted">No actions logged yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js and chart rendering -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
  const dailyTrend = <?php echo json_encode($stats['daily_trend'] ?? []); ?>;

  if (dailyTrend.length > 0) {
    const labels = dailyTrend.map(r => {
      const d = new Date(r.date);
      return d.toLocaleDateString('en-IN', {month:'short', day:'numeric'});
    });
    const data = dailyTrend.map(r => parseInt(r.count));

    new Chart(document.getElementById('dailyTrendChart'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Messages',
          data,
          backgroundColor: 'rgba(66, 133, 244, 0.7)',
          borderColor: 'rgba(66, 133, 244, 1)',
          borderWidth: 1,
          borderRadius: 4,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
      }
    });
  } else {
    document.getElementById('dailyTrendChart').insertAdjacentHTML('afterend',
      '<p class="text-center text-muted">No data for this period.</p>');
    document.getElementById('dailyTrendChart').style.display = 'none';
  }
})();
</script>
