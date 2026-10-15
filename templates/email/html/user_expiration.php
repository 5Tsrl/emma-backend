Buongiorno <?= $user->first_name ?>,<br>
<br>
il tuo account su <?= $platformName ?> scadrà il <strong><?= $expirationDate->i18nFormat('dd/MM/yyyy') ?></strong>.<br>
<br>
Se desideri continuare ad utilizzare il servizio, conferma che sei ancora attivo cliccando sul link qui sotto.<br>
Il tuo account verrà rinnovato automaticamente di un altro anno.<br>
<br>
<a href="<?= "{$referer}/users/confirm-renew/{$token}" ?>" style="display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">
  Conferma che sono ancora attivo
</a>
<br>
<br>
<strong style="color: orange;">Attenzione:</strong> Se non confermi entro la data di scadenza, il tuo account verrà disabilitato automaticamente.<br>
<br>
Se hai domande o hai bisogno di assistenza, non esitare a contattarci.<br>
<br>
Grazie,<br>
Il Team di <?= $platformName ?>