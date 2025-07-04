<?php

use Cake\Core\Configure;
//Uso il footer di default se l'utente non l'ha specificato
?>

<?php if (empty($footer)) : ?>
    <footer class="footer fixed-bottom" style="z-index: 0">
        <div class="container-fluid d-flex flex-wrap justify-content-between small">
            <a href="http://mobilitysquare.eu" target="_blank" class="d-flex">
                <img :src="<?= Configure::read('VUE_APP_FOOTER') ?>" :alt="<?= Configure::read('VUE_APP_FOOTERALT') ?>" style="height: 80px" class="manchette" />
            </a>

            <div class="copyright d-flex flex-wrap">
                Un prodotto di <a href="https://mobilitysquare.eu" target="_blank">MobilitySquare</a>                
            </div>
        </div>
    </footer>
<?php else : ?>
    <?= $footer ?>
<?php endif ?>