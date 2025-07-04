<table class="table table-striped">
    <?php foreach ($surveys as $s) : ?>
        <tr>
            <td><?= $s->name ?></td>
            <td><?= $s->company->name ?></td>
            <td><a href="<?= $this->Url->build(['action' => 'view', $s->id]) ?>">Visualizza Risposte</a></td>
        </tr>
    <?php endforeach ?>
</table>