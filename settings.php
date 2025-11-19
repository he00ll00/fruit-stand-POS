<?php
$cfg = $settings->get();
$email_val = htmlspecialchars($cfg['notify_email'] ?? '');
$enabled_val = !empty($cfg['notify_enabled']);
?>
<h4>Settings</h4>
<div style="margin:10px auto 0; background:white; border:1px solid #4c555e; border-radius:8px; padding:15px; max-width:460px;">
  <form method="post">
    <div class="mb-3">
      <label style="display:block; margin-bottom:6px;">Notification Email (optional)</label>
      <input type="email" name="notify_email" value="<?= $email_val ?>" placeholder="name@example.com" style="width:100%; padding:8px; border:1px solid #ced4da; border-radius:4px;">
      <div class="text-muted" style="font-size:12px; margin-top:6px;">When enabled, the system will send basic email notices (e.g., low stock, sale complete) to this address.</div>
    </div>
    <div class="mb-3" style="margin-top:10px;">
      <label style="display:flex; align-items:center; gap:8px;">
        <input type="checkbox" name="notify_enabled" value="1" <?= $enabled_val ? 'checked' : '' ?>>
        Enable Email Notifications
      </label>
    </div>
    <div style="margin-top:12px; display:flex; gap:8px;">
      <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
      <a href="?page=fruits" class="btn btn-secondary">Back</a>
    </div>
  </form>
</div>
