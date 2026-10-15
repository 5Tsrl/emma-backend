<?php
/**
 * Avviso ai valutatori (admin + moma_area di territorio): il backoffice ha rigenerato
 * o reinviato il link della richiesta di adesione al referente.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AdhesionRequest $request
 * @var string $companyName
 * @var string $actorFullName
 * @var string $status
 * @var \Cake\I18n\FrozenTime|null $expiresAt
 */
?>
Buongiorno,<br>
<br>
<?php if ($status === 'pdf_pending') : ?>
    <strong><?= h($actorFullName) ?></strong> ha rigenerato il link di accesso della richiesta
    di adesione alla Piattaforma digitale EMMA RE-R per <strong><?= h($companyName) ?></strong>
    (richiesta n. <?= h($request->id) ?>) e lo ha inviato al referente.<br>
    <br>
    Il link precedente non è più valido.
    <?php if (!empty($expiresAt)) : ?>
        Il nuovo link resta valido fino al <strong><?= h($expiresAt->i18nFormat('dd/MM/yyyy')) ?></strong>.
    <?php endif; ?>
    <br>
<?php else : ?>
    <strong><?= h($actorFullName) ?></strong> ha reinviato al referente il link di accesso della
    richiesta di adesione alla Piattaforma digitale EMMA RE-R per <strong><?= h($companyName) ?></strong>
    (richiesta n. <?= h($request->id) ?>).<br>
<?php endif; ?>
<br>
Questa è una comunicazione informativa: non è richiesta alcuna azione.<br>
<br>
Grazie.
