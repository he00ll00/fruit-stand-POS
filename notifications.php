<?php
$items = $notification->getAll();
?>
<h4>Notification History</h4>
<div style="margin-top:10px;">
  <?php if (empty($items)): ?>
    <div class="text-muted">No notifications yet.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="width:120px;">Time</th>
          <th style="width:120px;">Type</th>
          <th>Message</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $n): ?>
          <tr>
            <td><?= htmlspecialchars($n['created_at']) ?></td>
            <td><?= htmlspecialchars($n['type']) ?></td>
            <td><?= htmlspecialchars($n['message']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
