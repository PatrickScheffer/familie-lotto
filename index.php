<?php
session_start();
setlocale(LC_ALL, 'nl_NL.utf8');

require_once ('includes/MysqliDb.php');
require_once('includes/class.lotto.php');
require_once('includes/class.players.php');

$round = 1;
$db = new MysqliDb ('localhost', 'webdev', 'webdev', 'lotto');
$players = new players();

if (isset($_GET['logout'])) {
  $players->logout();
}
elseif (isset($_POST['username']) && isset($_POST['password'])) {
  $players->login(htmlspecialchars($_POST['username'], ENT_QUOTES), htmlspecialchars($_POST['password'], ENT_QUOTES));
}

if ($players->isLoggedIn()) {
  $lotto = new lotto();

  $current_round = $lotto->getCurrentRound();

  if (isset($_GET['sync'])) {
    $lotto->sync($current_round);
  }

  $results = $lotto->getResultsByRound($current_round);

  $active_players = $players->getActivePlayers();
  $players->checkNumbers($current_round);

  $player_ids = array_keys($active_players);
  $player_numbers = $players->getNumbers($current_round, $player_ids);

  foreach ($active_players as $player_id => $player) {
    if (!isset($player_numbers[$player_id])) {
      unset($active_players[$player_id]);
    }
    else {
      $active_players[$player_id]['numbers'] = $player_numbers[$player_id];
    }
  }

  $messages = $lotto->getMessages();
}
?>

<!DOCTYPE>
<html>
<head>
  <title>Familie Lotto</title>
  <link rel="stylesheet" type="text/css" href="css/style.css" />
  <script type="text/javascript" language="javascript" src="js/jquery-3.0.0.min.js"></script>
  <script type="text/javascript" language="javascript" src="js/lotto.js"></script>
</head>
<body>

<?php if ($players->isLoggedIn()): ?>
  <?php include_once('templates/lotto.php'); ?>
<?php else: ?>
  <?php include_once('templates/login.php'); ?>
<?php endif; ?>

</body>
</html>
