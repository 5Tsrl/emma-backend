<h1>Anonimizza gli utenti (tutti!)</h1>
<div class="alert alert-danger" role="alert">
    <?php if ($company_id) : ?>
        <strong>Attenzione Operazione irreversibile</strong>. Verranno cancellati tutti i dati relativi agli utenti dell'azienda selezionata nel database e sostituiti con dati anonimi.
    <?php else : ?>
        <strong>Attenzione Operazione irreversibile</strong>. Verranno cancellati tutti i dati relativi agli utenti nel database e sostituiti con dati anonimi.
    <?php endif; ?>
</div>

<?= $this->Form->create(null,['onsubmit' => 'return confirm("Sei sicuro di voler procedere con l\'anomimizzazione?");']) ?>
        <!-- chiede conferma prima di fare submit -->
        <?= $this->Form->control('company_id', ['value' => $company_id, 'class'=> 'form-control']) ?>
        <?= $this->Form->submit('Anonimizza', ['class' => 'btn btn-danger float-right mt-3']) ?>
<?= $this->Form->end() ?>