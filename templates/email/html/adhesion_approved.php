<?php
/**
 * Esito positivo: doppia approvazione ottenuta, utenti abilitati per 365 giorni.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AdhesionRequest $request
 * @var string $companyName
 * @var int $peopleCount
 * @var \Cake\I18n\FrozenTime|string|null $expirationDate
 * @var string $loginLink
 */
$expiration = null;
if (!empty($expirationDate)) {
    $expiration = is_string($expirationDate)
        ? $expirationDate
        : $expirationDate->i18nFormat('dd/MM/yyyy');
}
?>
Buongiorno,<br>
<br>
la <strong>richiesta di adesione</strong> alla Piattaforma digitale EMMA RE-R per
<strong><?= h($companyName) ?></strong> (richiesta n. <?= h($request->id) ?>) è stata
<strong>approvata</strong>.<br>
<br>
Entrambe le valutazioni previste — quella dell'amministratore della piattaforma e quella del
Mobility Manager d'Area competente — hanno avuto esito positivo.<br>
<br>
<strong>Persone abilitate:</strong> <?= h($peopleCount) ?><br>
<?php if ($expiration !== null) : ?>
    <strong>Abilitazione valida fino al:</strong> <?= h($expiration) ?> (365 giorni)<br>
<?php endif; ?>
<br>
L'accesso alla piattaforma avviene da:<br>
<a href="<?= h($loginLink) ?>"><?= h($loginLink) ?></a><br>
<br>
Gli utenti della Regione Emilia-Romagna accedono tramite identità digitale (SPID o IAM):
non è previsto l'uso di una password dedicata.<br>
<br>
Grazie.
