<div class="questions view large-9 medium-8 columns content">
  <h3><?= h($question->name) ?></h3>
  <div class="table-responsive">
    <table class="table table-striped">
      <tr>
        <th scope="row"><?= __('Name') ?></th>
        <td><?= h($question->name) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('Description') ?></th>
        <td><?= h($question->description) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('Options') ?></th>
        <td><?= $this->Options->toCheckBox($question->options) ?>
        </td>
      </tr>
      <tr>
        <th scope="row"><?= __('Id') ?></th>
        <td><?= $this->Number->format($question->id) ?></td>
      </tr>
    </table>
  </div>
  <div class="row">
    <h4><?= __('Long Description') ?></h4>
    <?= $this->Text->autoParagraph(h($question->long_description)); ?>
  </div>

</div>