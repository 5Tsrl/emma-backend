<?php
/**
 * Il link è stato REINVIATO: è lo STESSO di prima, nulla è cambiato.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AdhesionRequest $request
 * @var string $companyName
 * @var string $completeLink
 * @var \Cake\I18n\FrozenTime|null $expiresAt
 */
?>
Buongiorno,<br>
<br>
come richiesto, ecco di nuovo il link per la richiesta di adesione alla Piattaforma
digitale EMMA RE-R per <strong><?= h($companyName) ?></strong>
(richiesta n. <?= h($request->id) ?>).<br>
<br>
<strong>È lo stesso link che avevi già ricevuto: nulla è cambiato</strong> e non è
richiesta nessuna nuova azione oltre a quella eventualmente già in corso.<br>
<br>
<a href="<?= h($completeLink) ?>"><?= h($completeLink) ?></a><br>
<br>
<?php if (!empty($expiresAt)) : ?>
    Il link resta valido fino al <strong><?= h($expiresAt->i18nFormat('dd/MM/yyyy')) ?></strong>.<br>
    <br>
<?php endif; ?>
Grazie.
