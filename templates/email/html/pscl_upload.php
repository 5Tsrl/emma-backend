<?php

use Cake\Routing\Router;
?>
E' stato caricato un nuovo PSCL

dall'utente <b><?= $user ?></b>

dell'azienda <b><?= $company->name ?></b>


Puoi scaricarlo nella sezione PSCL del sito
<a href="<?= Router::url("{$referer}pscl?company_id={$company->id}&office_id={$office_id}&year={$year}", true) ?>">Scarica</a>

Saluti
Lo staff