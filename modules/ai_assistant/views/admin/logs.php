<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
  <div class="col-md-12">
    <div class="page-heading">
      <h3><?php echo lang('ai_audit_log'); ?>
        <small>
          <a href="<?php echo admin_url('ai_assistant/dashboard'); ?>" class="btn btn-sm btn-default">
            ← Dashboard
          </a>
        </small>
      </h3>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="panel_s">
  <div class="panel-body">
    <form method="get" class="form-inline">
      <div class="form-group mright10">
        <label>Status</label>
        <select name="status" class="form-control input-sm">
          <option value="">All</option>
          <?php foreach (['success', 'error', 'blocked', 'pending'] as $s) : ?>
            <option value="<?php echo $s; ?>" <?php echo ($filters['status'] ?? '') === $s ? 'selected' : ''; ?>>
              <?php echo ucfirst($s); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group mright10">
        <label>Staff</label>
        <select name="user_id" class="form-control input-sm">
          <option value="">All Staff</option>
          <?php foreach ($all_staff as $s) : ?>
            <option value="<?php echo $s['staffid']; ?>"
                    <?php echo ($filters['user_id'] ?? '') == $s['staffid'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group mright10">
        <label>From</label>
        <input type="date" name="date_from" class="form-control input-sm"
               value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" />
      </div>
      <div class="form-group mright10">
        <label>To</label>
        <input type="date" name="date_to" class="form-control input-sm"
               value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>" />
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="<?php echo admin_url('ai_assistant/logs'); ?>" class="btn btn-default btn-sm mleft5">Reset</a>
    </form>
  </div>
</div>

<!-- Log Table -->
<div class="panel_s">
  <div class="panel-body">
    <p class="text-muted">Showing <?php echo count($logs); ?> of <?php echo number_format($total); ?> records</p>
    <div class="table-responsive">
      <table class="table table-hover table-bordered table-striped">
        <thead>
          <tr>
            <th>#</th>
            <th>Staff</th>
            <th>Tool Used</th>
            <th>Status</th>
            <th>IP Address</th>
            <th>Provider</th>
            <th>Timestamp</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($logs)) : ?>
            <?php foreach ($logs as $log) : ?>
              <?php
              $status_class = ['success' => 'success', 'error' => 'danger', 'blocked' => 'warning', 'pending' => 'info'];
              $cls = $status_class[$log['status']] ?? 'default';
              $staff_name = !empty($log['firstname']) ? $log['firstname'] . ' ' . $log['lastname'] : ($log['staff_name'] ?? 'Unknown');
              ?>
              <tr>
                <td><?php echo $log['id']; ?></td>
                <td><?php echo htmlspecialchars($staff_name); ?></td>
                <td><code><?php echo htmlspecialchars($log['tool_used'] ?? $log['action_name']); ?></code></td>
                <td><span class="label label-<?php echo $cls; ?>"><?php echo htmlspecialchars($log['status']); ?></span></td>
                <td><small><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></small></td>
                <td><small><?php echo htmlspecialchars($log['ai_provider'] ?? 'gemini'); ?></small></td>
                <td><small><?php echo date('d M y H:i:s', strtotime($log['created_at'])); ?></small></td>
                <td>
                  <?php if (!empty($log['error_msg'])) : ?>
                    <span class="text-danger" title="<?php echo htmlspecialchars($log['error_msg']); ?>">
                      <i class="fa fa-exclamation-circle"></i>
                    </span>
                  <?php endif; ?>
                  <?php if (!empty($log['payload'])) : ?>
                    <button type="button" class="btn btn-xs btn-default log-detail-btn"
                            data-payload='<?php echo htmlspecialchars($log['payload']); ?>'
                            data-result='<?php echo htmlspecialchars($log['result'] ?? ''); ?>'>
                      <i class="fa fa-search"></i>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="8" class="text-center text-muted">No log entries found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1) : ?>
      <div class="text-center">
        <ul class="pagination">
          <?php for ($i = 1; $i <= $pages; $i++) : ?>
            <li class="<?php echo $i === $page ? 'active' : ''; ?>">
              <a href="?page=<?php echo $i;
              echo !empty($filters['status']) ? '&status=' . $filters['status'] : '';
              echo !empty($filters['user_id']) ? '&user_id=' . $filters['user_id'] : ''; ?>">
                <?php echo $i; ?>
              </a>
            </li>
          <?php endfor; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Log Detail Modal -->
<div class="modal fade" id="logDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Log Detail</h4>
      </div>
      <div class="modal-body">
        <h5>Payload</h5>
        <pre id="logPayload" style="max-height:200px;overflow:auto;background:#f5f5f5;padding:10px;border-radius:4px;"></pre>
        <h5>Result</h5>
        <pre id="logResult" style="max-height:200px;overflow:auto;background:#f5f5f5;padding:10px;border-radius:4px;"></pre>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.log-detail-btn').forEach(btn => {
  btn.addEventListener('click', function(){
    let payload = this.dataset.payload;
    let result  = this.dataset.result;
    try { payload = JSON.stringify(JSON.parse(payload), null, 2); } catch(e){}
    document.getElementById('logPayload').textContent = payload || '(empty)';
    document.getElementById('logResult').textContent  = result  || '(empty)';
    $('#logDetailModal').modal('show');
  });
});
</script>
