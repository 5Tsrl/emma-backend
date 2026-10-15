<?php
/**
 * Notifica ai valutatori: è stata ricevuta una richiesta di adesione nel momento di invio del form.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AdhesionRequest $request
 * @var string $companyName
 * @var string $referenceCity
 * @var string $referenceProvince
 * @var string $mmName
 * @var string $mmEmail
 * @var string $frontendUrl
 * @var string $adhesionsLink
 * @var int $peopleCount
 */
?>
Buongiorno,<br>
<br>
è stata ricevuta una <strong>richiesta di adesione</strong> alla piattaforma.<br>
<br>
<strong>Azienda/Ente:</strong> <?= h($companyName) ?><br>
<strong>Comune di riferimento:</strong> <?= h($referenceCity) ?><br>
<strong>Provincia di riferimento:</strong> <?= h($referenceProvince) ?><br>
<strong>Mobility Manager:</strong> <?= h($mmName) ?><br>
<strong>Email Mobility Manager:</strong> <?= h($mmEmail) ?><br>
<strong>Persone da abilitare:</strong> <?= h($peopleCount) ?><br>
<br>
La richiesta non può essere valutata fino a quando il referente non avrà caricato il
documento firmato digitalmente. Riceverai un avviso separato quando il documento sarà
arrivato.<br>
<br>
Puoi consultare la richiesta dalla sezione adesioni:<br>
<a href="<?= h($adhesionsLink) ?>"><?= h($adhesionsLink) ?></a><br>
<br>
Grazie.
