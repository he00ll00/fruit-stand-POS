<h4>Restock</h4>
<div class="fruit-grid">
  <?php $all = $fruit->getAllFruits(); foreach($all as $rf): ?>
    <div class="fruit-card">
      <img src="<?= htmlspecialchars(!empty($rf['image_path']) ? $rf['image_path'] : 'images/default.png') ?>" alt="<?= htmlspecialchars($rf['fruit_name']) ?>">
      <h4><?= htmlspecialchars($rf['fruit_name']) ?></h4>
      <p>In stock: <?= number_format((float)$rf['stock_qty'], 3) ?></p>
      <p>
        <?php if ($rf['price_per_piece'] > 0): ?>
          <span>₱<?= number_format($rf['price_per_piece'], 2) ?>/pc</span>
        <?php else: ?>
          <span>₱0.00/pc</span>
        <?php endif; ?>
        <span style="margin-left:8px;"></span>
        <?php if ($rf['price_per_kg'] > 0): ?>
          <span>₱<?= number_format($rf['price_per_kg'], 2) ?>/kg</span>
        <?php else: ?>
          <span>₱0.00/kg</span>
        <?php endif; ?>
      </p>
      <form method="post" style="margin-top:6px;">
        <input type="hidden" name="restock" value="1">
        <input type="hidden" name="fruit_id" value="<?= $rf['fruit_id'] ?>">
        <div style="display:flex; justify-content:center; gap:6px;">
          <input type="number" name="amount" step="0.001" min="0.001" value="1.000" style="width:100px;">
          <button type="submit" class="btn btn-success">+</button>
        </div>
      </form>
      <form method="post" style="margin-top:6px;">
        <input type="hidden" name="update_price_piece" value="1">
        <input type="hidden" name="fruit_id" value="<?= $rf['fruit_id'] ?>">
        <div style="display:flex; justify-content:center; gap:6px;">
          <input type="number" name="new_price_piece" step="0.01" min="0" value="<?= htmlspecialchars($rf['price_per_piece']) ?>" style="width:120px;">
          <button type="submit" class="btn btn-primary">Update /pc</button>
        </div>
      </form>
      <form method="post" style="margin-top:6px;">
        <input type="hidden" name="update_price_kg" value="1">
        <input type="hidden" name="fruit_id" value="<?= $rf['fruit_id'] ?>">
        <div style="display:flex; justify-content:center; gap:6px;">
          <input type="number" name="new_price_kg" step="0.01" min="0" value="<?= htmlspecialchars($rf['price_per_kg']) ?>" style="width:120px;">
          <button type="submit" class="btn btn-primary">Update /kg</button>
        </div>
      </form>
      <form method="post" enctype="multipart/form-data" style="margin-top:6px;">
        <input type="hidden" name="update_image" value="1">
        <input type="hidden" name="fruit_id" value="<?= $rf['fruit_id'] ?>">
        <div style="display:flex; justify-content:center; gap:6px;">
          <input type="file" name="new_image" accept="image/*" style="width:160px;">
          <button type="submit" class="btn btn-primary">Change Photo</button>
        </div>
      </form>
      <form method="post" style="margin-top:6px;">
        <input type="hidden" name="delete_fruit" value="1">
        <input type="hidden" name="fruit_id" value="<?= $rf['fruit_id'] ?>">
        <button type="submit" class="btn btn-danger">Delete Fruit</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
