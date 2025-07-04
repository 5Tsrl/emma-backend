Gentile <?= $user->first_name; ?>,
abbiamo ricevuto la tua richiesta di abbonamento.
<br>
Ti chiediamo di verificare che sia tutto corretto, o altrimenti di segnalarci subito eventuali errori.
<br><br>
Ecco i dettagli della tua richiesta:<br>
Nome: <b><?= $dt['nome'] ?></b><br>
Cognome: <b><?= $dt['cognome'] ?></b><br>
<?php if ($dt['origine'] != 'null') : ?>Origine: <b><?= $dt['origine'] ?></b><br><?php endif; ?>
<?php if ($dt['destinazione'] != 'null') : ?>Destinazione: <b><?= $dt['destinazione'] ?></b><br><?php endif; ?>
decorrenza: <b><?= $dt['mese_validita'] ?></b><br>
Operatore: <b><?= $dt['operatore'] ?></b><br>
Tipo di Abbonamento Richiesto: <b><?= $dt['abbonamento_selezionato'] ?></b><br>
<br>
<br>
<br>
La tua richiesta è stata presa in carico. Verrai avvisato appena l'abbonamento è pronto.<br>
Saluti,<br>
Lo Staff di MobilitySquare<br>