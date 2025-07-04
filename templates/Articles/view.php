<?php

use Cake\Controller\Component\AuthComponent;

?>

<?php $this->assign('title', $article->title); ?>


<!--
=============================================
    Blog Details
==============================================
-->
<div class="blog-list">
    <div class="container">

        <?php if ($user) : //Solo se sono loggato posso vedere questo blocco
        ?>
            <ul class="nav nav-tabs">
                <li class="nav-item"><?= $this->Html->link(__('Edit Article'), ['action' => 'edit', $article->id], ['class' => 'nav-link']) ?> </li>
                <li class="active nav-item"><a href="#" class="nav-link active">View Article</a></li>
                <li class="nav-item"><?= $this->Form->postLink(__('Delete Article'), ['action' => 'delete', $article->id], ['confirm' => __('Are you sure you want to delete # {0}?', $article->id), 'class' => 'nav-link']) ?> </li>
            </ul>
<?php endif; ?>
        <br>

        <div class="row">
            <div class="col-lg-9 col-md-8 col-xs-12 blog-details-content">
                <div class="single-blog-list">
                    <div class="image"><img src="/images<?= $article->copertina ?>?w=800&fit=crop" alt="<?= $article->title ?>" class="img-responsive img-rounded"></div>

                    <h1><?= h($article->title) ?></h1>
                    <?= preg_replace('/font.+?;/', "", preg_replace("#<font[^>]*>#is", '', $article->body)); ?>
                    <br>
                    <p class="text-muted">Ultimo Aggiornamento: <?= $article->modified ?></p>
                </div> <!-- /.single-blog-list -->

                <!--
                =============================================
                    Our Gallery
                ==============================================
                -->
                <div class="our-gallery section-margin-top">
                    <?= $this->element('img-gallery', ['images' => $article->gallery]); ?>
                </div> <!-- /.our-gallery -->

            </div> <!-- /.blog-list-content -->

            <!-- ************************ Theme Sidebar *************************** -->
            <div class="col-lg-3 col-md-4 col-sm-6 col-xs-12 theme-sidebar">
                <?php if ($article->allegati) : ?>
                    <div class="sidebar-download">
                        <h5>Allegati</h5>
                        <ul>
                            <?php foreach ($article->allegati as $file) : ?>
                                <li><a href="<?= $file ?>"><i class="fa fa-file-o"></i> <?= basename($file) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div> <!-- /.sidebar-download -->
                <?php endif ?>
            </div> <!-- /.theme-sidebar -->
        </div> <!-- /.row -->
    </div> <!-- /.container -->
</div> <!-- /.blog-list -->
