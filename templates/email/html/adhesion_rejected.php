<?php
/**
 * Esito negativo (rifiuto di entrambi i valutatori, #538): notifica informale con le
 * motivazioni, una per riga (specifica §9).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AdhesionRequest $request
 * @var string $companyName
 * @var string $reason Motivazioni unite da una riga vuota (nl2br in render).
 */
?>
Buongiorno,<br>
<br>
la <strong>richiesta di adesione</strong> alla Piattaforma digitale EMMA RE-R per
<strong><?= h($companyName) ?></strong> (richiesta n. <?= h($request->id) ?>)
<strong>non è stata accolta</strong>.<br>
<br>
<?php if ($reason !== '') : ?>
    <strong>Motivazione:</strong><br>
    <?= nl2br(h($reason)) ?><br>
    <br>
<?php endif; ?>
La presente è una comunicazione informale: potrà seguire una comunicazione formale da parte
degli uffici regionali competenti.<br>
<br>
Per chiarimenti è possibile contattare il referente regionale per il mobility management.<br>
<br>
Grazie.
