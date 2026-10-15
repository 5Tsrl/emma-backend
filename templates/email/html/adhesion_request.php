<?php
/**
 * Notifica ai valutatori: il documento firmato è arrivato, la richiesta è valutabile.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AdhesionRequest $request
 * @var string $companyName
 * @var string $referenceCity
 * @var string $mmName
 * @var string $mmEmail
 * @var string $frontendUrl
 * @var string $adhesionsLink
 * @var int $peopleCount
 * @var string|null $signatureFormat
 */
?>
Buongiorno,<br>
<br>
è stata ricevuta una <strong>richiesta di adesione</strong> alla piattaforma, completa del
documento firmato digitalmente: la richiesta è ora <strong>in valutazione</strong>.<br>
<br>
<strong>Azienda/Ente:</strong> <?= h($companyName) ?><br>
<strong>Comune di riferimento:</strong> <?= h($referenceCity) ?><br>
<strong>Mobility Manager:</strong> <?= h($mmName) ?><br>
<strong>Email Mobility Manager:</strong> <?= h($mmEmail) ?><br>
<strong>Persone da abilitare:</strong> <?= h($peopleCount) ?><br>
<?php if (!empty($signatureFormat)) : ?>
    <strong>Formato della firma:</strong> <?= h(strtoupper($signatureFormat)) ?><br>
<?php endif; ?>
<br>
<strong>Sono necessarie due valutazioni indipendenti</strong>: quella dell'amministratore
della piattaforma e quella del Mobility Manager d'Area competente per territorio. Gli
account delle persone indicate vengono creati <strong>solo</strong> quando entrambe le
valutazioni sono positive.<br>
<br>
Puoi esprimere la tua valutazione dalla sezione adesioni:<br>
<a href="<?= h($adhesionsLink) ?>"><?= h($adhesionsLink) ?></a><br>
<br>
Grazie.
