Buongiorno <?= $user->first_name ?><br>
<br>
Per impostare la password del tuo account puoi fare click su questo link e seguire le istruzioni.<br>
<br>
<a href="<?= "{$referer}/users/change-password/$token" ?>">
  Cambia Password
</a>
<br>
Saluti, Lo staff.