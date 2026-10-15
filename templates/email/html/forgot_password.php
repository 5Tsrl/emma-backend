Buongiorno <?= $user->first_name ?><br>
<br>
Per impostare la password del tuo account <?= $platformName ?> puoi fare click su questo link e seguire le istruzioni.
Ricorda di scegliere una password di almeno 10 caratteri, di cui:

    almeno una lettera maiuscola
    almeno una lettera minuscola
    almeno un numero
    almeno un simbolo speciale (es. ! @ # $ % ^ & * ?)
<br>
<br>
<a href="<?= "{$referer}/users/change-password/$token" ?>">
  Cambia Password
</a>
<br>
<br>
<strong style="color: red;">Nota:</strong> Questo link scade tra 48 ore.
<br>
Grazie.<br>
Il Team di <?= $platformName ?>