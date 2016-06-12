<?php
setlocale(LC_ALL, 'nl_NL.utf8');

require_once ('MysqliDb.php');
require_once('class.lotto.php');
require_once('class.players.php');

$round = 1;
$db = new MysqliDb ('localhost', 'webdev', 'webdev', 'lotto');
$lotto = new lotto();

if (isset($_GET['sync'])) {
  $lotto->sync(1);
}

$results = $lotto->getResultsByRound(1);

$players = new players();
$active_players = $players->getActivePlayers();
$players->checkNumbers(1);

$player_ids = array_keys($active_players);
$player_numbers = $players->getNumbers(1, $player_ids);

foreach ($active_players as $player_id => $player) {
  if (!isset($player_numbers[$player_id])) {
    unset($active_players[$player_id]);
  }
  else {
    $active_players[$player_id]['numbers'] = $player_numbers[$player_id];
  }
}
?>

<!DOCTYPE>
<html>
<head>
  <title>Familie Lotto</title>
  <link rel="stylesheet" type="text/css" href="style.css" />
  <script type="text/javascript" language="javascript" src="jquery-3.0.0.min.js"></script>
  <script type="text/javascript" language="javascript" src="lotto.js"></script>
</head>
<body>

<h1>Familie Lotto</h1>

<section id="main">

  <div class="content">

    <div class="players yellow">
      <h2>Spelers</h2>
      <?php if (empty($active_players)): ?>
        Er zijn geen spelers gevonden.
      <?php else: ?>
        <div class="wrapper">
          <?php foreach ($active_players as $player_id => $player): ?>
          <div class="player">
            <h3><?php print $player['name']; ?></h3>
            <ul class="draw">
              <?php foreach ($player['numbers'] as $numbers): ?>
                <li class="ball <?php if ($numbers['drawn']) print 'drawn'; ?>">
                  <?php print $numbers['number']; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="results yellow">
      <h2>Trekkingen</h2>
      <?php if (empty($results)): ?>
        Er zijn nog geen trekkingen geweest.
      <?php else: ?>
        <div class="wrapper">
          <?php $latest_draw = array_shift($results); ?>
          <div class="draw">
            <h3><?php print strftime('%e %B %Y', $latest_draw['date']); ?></h3>
            <ul class="draw">
              <?php
              $numbers = unserialize($latest_draw['numbers']);
              foreach ($numbers as $number) {
                print '<li class="ball">' . $number . '</li>';
              }
              ?>
            </ul>
          </div>
          <?php if (!empty($results)): ?>
            <div class="more_results">
              <?php foreach ($results as $draw_id => $draw): ?>
                <div class="draw">
                  <h3><?php print strftime('%e %B %Y', $draw['date']); ?></h3>
                  <ul class="draw">
                  <?php
                  $numbers = unserialize($draw['numbers']);
                  foreach ($numbers as $number) {
                    print '<li class="ball">' . $number . '</li>';
                  }
                  ?>
                  </ul>
                </div>
              <?php endforeach; ?>
            </div>
            <a href="#" class="toggle_results"><span class="state_label">Toon alle</span> resultaten van deze ronde</a>
          <?php endif;?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>

</body>
</html>
