<?php
$this->assign('title', 'Risposte Questionario utente ' . $user_id);
$this->Breadcrumbs->add([
  ['title' => 'Home', 'url' => '/'],
  ['title' => 'Admin', 'url' => '/pages/admin'],
  ['title' => 'Questionari', 'url' => ['controller' => 'Surveys', 'action' => 'index']],
  ['title' => "Questionario $survey_id", 'url' => ['controller' => 'Surveys', 'action' => 'view', $survey_id]],
  ['title' => 'Risposte Utente', 'url' => ['controller' => 'Answers', 'action' => 'view', $survey_id]]
]);
echo $this->Breadcrumbs->render(
    ['class' => 'breadcrumb'],
    ['separator' => ' &gt; ']
);
?>

<h1>Risposta al questionario dell'utente <?= $user_id ?> </h1>
<?php
$i = array_search($user_id, $user_list);
$prev = (isset($user_list[$i - 1])) ? $user_list[$i - 1] : null;
$next = (isset($user_list[$i + 1])) ? $user_list[$i + 1] : null;
?>

<a href="<?= $this->Url->build(['action' => 'view', $survey_id, $prev]) ?>" class="btn btn-primary"><i class="fa fa-arrow-left" aria-hidden="true"></i> Utente Precedente </a>
<a href="<?= $this->Url->build(['action' => 'view', $survey_id, $next]) ?>" class="btn btn-primary">Utente Successivo <i class="fa fa-arrow-right" aria-hidden="true"></i></a>

<table class="table table-striped">
  <thead>
    <th>Domanda</th>
    <th>Risposta</th>
  </thead>
  <tbody>
    <?php foreach ($answer as $a) : ?>
      <tr>
        <td>
          <a href="<?= $this->Url->build(['controller' => 'Questions', 'action' => 'edit', $a->question->id]) ?>" target="question">
            <?= $a->question->name ?>
          </a>
        </td>
        <td>
          <?php if ($a->question->type == 'multiple') : ?>
            <?= $this->Options->toAnswerList($a->answer) ?>
          <?php else : ?>
            <?= $a->answer ?>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>