<?php
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert alert-danger" onclick="this.classList.add('hidden')"><?= $message ?></div>
<b-alert dismissible variant="warning">
  <?= $message ?>

  <?php if (isset($params) && isset($params['errors'])) : ?>
    <ul class="">
      <li class="">
        <h5><?= __('The following errors occurred:') ?></h5>
      </li>
      <?php foreach ($params['errors'] as $error) : ?>
        <li class=""><i class="fa fa-edge">error</i><?= h($error) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</b-alert>