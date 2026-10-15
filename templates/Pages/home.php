<?php
use Cake\Routing\Router;
?>
<h2>Funzioni Amministratore</h2>
<ul>
  <li><a href="<?= Router::url('/surveys'); ?>">Questionari</a></li>
  <li><a href="<?= Router::url('/questions'); ?>">Domande</a></li>
  <li><a href="<?= Router::url('/pillars'); ?>">Pilastri, Misure, Servizi</a></li>
  <li><a href="<?= Router::url('/sections'); ?>">Sezioni Questionario</a></li>
  <li><a href="<?= Router::url('/offices/upload_template'); ?>">Carica nuovo modello di PSCL docx (scuola o azienda)</a></li>
  <li><a href="<?= Router::url('/users/upload_guide'); ?>">Carica nuova guida utente (PDF)</a></li>
  <li><a href="<?= Router::url('/users/upload_faq'); ?>">Carica FAQ (PDF)</a></li>
  <li><a href="<?= Router::url('/offices/import'); ?>">Importa numero studenti per scuola o azienda</a></li>
  <!-- <li><a href="<?= Router::url('/areas'); ?>">Gestione Aree per il Mobility Manager d'Area</a></li> -->
  <li><a href="<?= Router::url('/users/anonymize'); ?>">Rendere anonimi tutti gli utenti.</a></li>
  <li><a href="<?= Router::url('/users/logout'); ?>">Logout</a></li>
  <li><a href="<?= Router::url('/offices/export/2025.csv'); ?>">Esporta dati uffici 2025 in CSV</a></li>
  <li><a href="<?= Router::url('/offices/export/2025.xls'); ?>">Esporta dati uffici 2025 in XLS</a></li>

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