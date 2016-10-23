<?php
require_once('bootstrap.php');

if (isset($_GET['token']) && $_GET['token'] == md5(CRON_TOKEN) && isset($_GET['sync'])) {
  $lotto = new lotto();
  $current_round = $lotto->getCurrentRound();
  $lotto->sync($current_round);
  $players->checkNumbers($current_round);
  $results = $lotto->getResultsByRound($current_round);

  $last_result = array_pop($results);
  $numbers = unserialize($last_result['numbers']);

  $to      = 'theguitarplayer@live.nl';
  $subject = 'Familie lotto update';
  $message = 'De lotto trekking van ' . date('d-m-Y') . ': ' . implode(', ', $numbers) . "\r\n";
  $lotto_messages = $lotto->getMessages();
  if (!empty($lotto_messages)) {
    $message .= implode("\r\n", $lotto_messages);
  }
  $headers = 'From: mail@patrickscheffer.com' . "\r\n" .
    'Reply-To: mail@patrickscheffer.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

  mail($to, $subject, $message, $headers);

  print 'Cron successfully run.';
}

if (isset($_GET['logout'])) {
  $players->logout();
  header('Location: ' . BASE_URL . '/');
  die();
}
elseif (isset($_POST['username']) && isset($_POST['password'])) {
  $username = strtolower(htmlspecialchars($_POST['username'], ENT_QUOTES));
  $password = strtolower(htmlspecialchars($_POST['password'], ENT_QUOTES));
  $players->login($username, $password);
}

if ($players->isLoggedIn()) {
  $lotto = new lotto();

  $current_round = $lotto->getCurrentRound();

  if (isset($_GET['sync'])) {
    $lotto->sync($current_round);
  }

  $last_draw = $lotto->getLastDraw();

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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Familie</title>
  <link rel="stylesheet" type="text/css" href="css/style.css" />
  <script type="text/javascript" language="javascript" src="js/jquery-3.0.0.min.js"></script>
  <script type="text/javascript" language="javascript" src="js/l.js"></script>
</head>
<body>

<?php if ($players->isLoggedIn()): ?>
  <?php include_once('templates/overview.php'); ?>
<?php else: ?>
  <?php include_once('templates/login.php'); ?>
<?php endif; ?>

</body>
</html>
