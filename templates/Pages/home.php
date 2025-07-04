<h2>Funzioni Amministratore</h2>
<ul>
  <li><a href="/surveys">Questionari</a></li>
  <li><a href="/questions">Domande</a></li>
  <li><a href="/pillars">Pilastri, Misure, Servizi</a></li>
  <li><a href="/sections">Sezioni Questionario</a></li>
  <li><a href="/offices/upload_template">Carica nuovo modello di PSCL docx (scuola o azienda)</a></li>
  <li><a href="/users/upload_guide">Carica nuova guida utente (PDF)</a></li>
  <li><a href="/users/upload_faq">Carica FAQ (PDF)</a></li>
  <li><a href="/offices/import">Importa numero studenti per scuola o azienda</a></li>
  <li><a href="/areas">Gestione Aree per il Mobility Manager d'Area</a></li>
  <li><a href="/users/anonymize">Rendere anonimi tutti gli utenti.</a></li>
  <li><a href="/users/logout">Logout</a></li>

</ul>

<?php
// Menu principale fornito dai plugin
use Cake\Core\Plugin;
use Cake\Core\Configure;

$ps = Plugin::loaded();
foreach ($ps as $p) {
  if ($this->elementExists("$p.admin-menu")) {
    echo $this->element("$p.admin-menu");
  }
}
?>