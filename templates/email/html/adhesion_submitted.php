<?php
/**
 * Notifica al referente: richiesta ricevuta, serve il documento firmato.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AdhesionRequest $request
 * @var string $companyName
 * @var string $mmName
 * @var int $peopleCount
 * @var string $completeLink
 * @var \Cake\I18n\FrozenTime|null $expiresAt
 */
?>
Buongiorno <?= h($mmName) ?>,<br>
<br>
abbiamo ricevuto la <strong>richiesta di adesione</strong> alla Piattaforma digitale EMMA RE-R
per <strong><?= h($companyName) ?></strong> (richiesta n. <?= h($request->id) ?>).<br>
<br>
<strong>La richiesta non è ancora in valutazione.</strong> Per completarla occorre:<br>
<br>
1. scaricare il modulo di riepilogo in formato PDF;<br>
2. firmarlo digitalmente (formato PAdES, file <em>.pdf</em>, oppure CAdES, file <em>.p7m</em>);<br>
3. ricaricarlo sulla piattaforma.<br>
<br>
Tutti e tre i passaggi si effettuano da questa pagina:<br>
<a href="<?= h($completeLink) ?>"><?= h($completeLink) ?></a><br>
<br>
<?php if (!empty($expiresAt)) : ?>
    Il link resta valido fino al <strong><?= h($expiresAt->i18nFormat('dd/MM/yyyy')) ?></strong>.<br>
    <br>
<?php endif; ?>
Persone indicate da abilitare: <strong><?= h($peopleCount) ?></strong>.<br>
<br>
Dopo il caricamento del documento firmato la richiesta verrà valutata dall'amministratore
della piattaforma e dal Mobility Manager d'Area competente. Riceverà una comunicazione
con l'esito.<br>
<br>
Grazie.
