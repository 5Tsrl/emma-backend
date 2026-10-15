Buongiorno <?= $user->first_name ?>,<br>
<br>
ti informiamo che il tuo account su <strong><?= $platformName ?></strong> è stato disabilitato poiché la data di scadenza (<?= $expirationDate->i18nFormat('dd/MM/yyyy') ?>) è stata superata.<br>
<br>
Se ritieni che si tratti di un errore o desideri riattivare il tuo account, contatta il tuo mobility manager o l'amministratore della piattaforma.<br>
<br>
Grazie,<br>
Il Team di <?= $platformName ?>
