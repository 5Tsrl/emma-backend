<?php

use Cake\Core\Configure;
use Cake\Routing\Router;
//Uso il footer di default se l'utente non l'ha specificato

// VUE_APP_FOOTER è un percorso relativo al webroot del sito (es. "/img/5T-logo.jpg");
// Router::url(..., true) lo trasforma in URL assoluto usando fullBaseUrl/base
// dell'ambiente corrente, cosi' lo stesso valore funziona su dev/test/prod.
$footerLogoPath = Configure::read('VUE_APP_FOOTER');
$inlineLogoEnabled = Configure::read('MailFooterInlineLogo');
$poweredByEnabled = Configure::read('MailPoweredBy');

// Use CID when inline logo is enabled and CID is available, otherwise use absolute URL
if ($inlineLogoEnabled && $footerLogoCid) {
    $footerLogoSrc = 'cid:' . h($footerLogoCid);
} elseif (!$inlineLogoEnabled && $footerLogoPath) {
    $footerLogoSrc = h(Router::url('/' . ltrim($footerLogoPath, '/'), true));
} else {
    $footerLogoSrc = null;
}
?>

<?php if (empty($footer)) : ?>
    <footer class="footer fixed-bottom" style="z-index: 0">
        <div class="container-fluid d-flex flex-wrap justify-content-between small">
            <?php if ($footerLogoSrc) : ?>
                
                    <div class="d-flex">
                        <img src="<?= $footerLogoSrc ?>" alt="<?= h(Configure::read('VUE_APP_FOOTERALT')) ?>" style="height: 80px" class="manchette" />
                    </div>
                
            <?php endif; ?>
        </div>
    </footer>
<?php else : ?>
    <?= $footer ?>
<?php endif ?>