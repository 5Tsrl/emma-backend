<?php
/**
 * Il link è stato RIGENERATO: quello precedente non funziona più.
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
come richiesto, ecco il <strong>nuovo link</strong> per completare la richiesta di adesione
alla Piattaforma digitale EMMA RE-R per <strong><?= h($companyName) ?></strong>
(richiesta n. <?= h($request->id) ?>).<br>
<br>
<strong>Il link che avevi ricevuto in precedenza non è più valido</strong>: usa solo
quello qui sotto da questo momento in poi.<br>
<br>
<a href="<?= h($completeLink) ?>"><?= h($completeLink) ?></a><br>
<br>
<?php if (!empty($expiresAt)) : ?>
    Il nuovo link resta valido fino al <strong><?= h($expiresAt->i18nFormat('dd/MM/yyyy')) ?></strong>.<br>
    <br>
<?php endif; ?>
Da questa stessa pagina puoi scaricare il modulo di riepilogo, firmarlo digitalmente e
ricaricarlo, ed eventualmente caricare il modulo sul trattamento dei dati personali dei
dipendenti.<br>
<br>
Grazie.
