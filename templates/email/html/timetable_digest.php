Buongiorno,
<br>
ti informiamo che sulla piattaforma Orari Scolastici sono stati aggiornati i seguenti orari.
<br>
<br>
<br>

<table border="1" cellpadding="3">
  <thead>
    <th>Data Ultima Modifica</th>
    <th>Nome Istituto</th>
    <th>Indirizzo</th>
    <th>Città</th>
    <th>Prov</th>
    <th>Inizio Validità</th>
  </thead>

  <tbody>
    <?php foreach ($timetables as $timetable) : ?>
      <tr>
        <td>
          <?= $timetable->modified ?>
        </td>
        <td>
          <b> <?= $timetable->office->company->name ?></b> <?= $timetable->office->extended_name ?> (<?= $timetable->office->name ?>)
        </td>
        <td>
          <?= $timetable->office->address ?>
        </td>
        <td>
          <?= $timetable->office->city ?>
        </td>
        <td>
          <?= $timetable->office->province  ?>
        </td>
        <td>
          <?= $timetable->valid_from  ?>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<br>
Puoi consultare i nuovi orari accedendo alla tua area riservata.