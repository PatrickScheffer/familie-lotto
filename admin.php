<?php
require_once('bootstrap.php');

if (!$players->isAdmin()) {
  header('Location: ' . BASE_URL);
  die();
}

$lotto = new lotto();
$players = new players();

$db = MysqliDb::getInstance();

if (isset($_GET['new_round'])) {
  $lotto->endRound();
  $lotto->newRound();
  header('Location: ' . BASE_URL . '/admin.php');
  die();
}

if (isset($_GET['delete'])) {
  $db->where('player_id', htmlspecialchars($_GET['delete'], ENT_QUOTES));
  $db->delete('players');

  $db->where('player_id', htmlspecialchars($_GET['delete'], ENT_QUOTES));
  $db->delete('player_numbers');

  $db->where('player_id', htmlspecialchars($_GET['delete'], ENT_QUOTES));
  $db->delete('bets');
  header('Location: ' . BASE_URL . '/admin.php');
  die();
}

if (!empty($_POST['name'])) {
  foreach ($_POST['name'] as $player_id => $name) {
    $player_id = trim($player_id);
    $name = trim($name);
    if (empty($player_id) || empty($name)) {
      continue;
    }

    $new_data = array(
      'name' => htmlspecialchars($name, ENT_QUOTES),
      'role' => 0,
    );
    if (!empty($_POST['pass'][$player_id])) {
      $new_data['password'] = crypt(htmlspecialchars($_POST['pass'][$player_id], ENT_QUOTES), SECRET);
    }
    if (isset($_POST['role'][$player_id])) {
      $new_data['role'] = 1;
    }
    if ($player_id == 'new') {
      if (isset($new_data['password'])) {
        $player_id = $db->insert('players', $new_data);
      }
    }
    else {
      $db->where('player_id', htmlspecialchars($player_id, ENT_QUOTES));
      $db->update('players', $new_data);

      if (!empty($_POST['bet'][$player_id])) {
        $bet = htmlspecialchars($_POST['bet'][$player_id], ENT_QUOTES);
        $new_bet_data = array(
          'bet' => $bet,
        );
        if (!empty($_POST['max_draws'][$player_id])) {
          $new_bet_data['max_draws'] = htmlspecialchars($_POST['max_draws'][$player_id], ENT_QUOTES);
        }
        if (isset($_POST['played_draws'][$player_id])) {
          $new_bet_data['played_draws'] = htmlspecialchars($_POST['played_draws'][$player_id], ENT_QUOTES);
        }

        $db->where('player_id', htmlspecialchars($player_id, ENT_QUOTES));
        $player_bet = $db->get('bets');

        if (empty($player_bet)) {
          $new_bet_data['player_id'] = $player_id;
          $db->insert('bets', $new_bet_data);
        }
        else {
          $db->where('player_id', htmlspecialchars($player_id, ENT_QUOTES));
          $db->update('bets', $new_bet_data);
        }
      }
    }
  }
}

$all_players = array();

$db = MysqliDb::getInstance();
$db->orderBy('player_id', 'ASC');
$result = $db->get('players');

if (!empty($result)) {
  foreach ($result as $row) {
    $all_players[$row['player_id']] = $row;
  }
}

$bets = $db->get('bets');
foreach ($bets as $bet) {
  if (isset($all_players[$bet['player_id']])) {
    $all_players[$bet['player_id']] += $bet;
  }
}

$current_round = $lotto->getCurrentRound();

if (!empty($_POST['player_number'])) {
  $update = FALSE;
  foreach ($_POST['player_number'] as $player_id => $numbers) {
    foreach ($numbers as $number) {
      if (empty($number)) {
        print '<p>Aborting updating player ' . $player_id . ': found an empty number.</p>';
        continue 2;
      }
    }
    foreach ($numbers as $number) {
      $db->insert('player_numbers', array(
        'player_id' => $player_id,
        'round_id' => $current_round,
        'number' => htmlspecialchars($number, ENT_QUOTES),
      ));
      $update = TRUE;
    }
  }
  if ($update) {
    header('Location: ' . BASE_URL . '/admin.php');
  }
}
?>

<!doctype>
<html>
<head>
  <title>Admin</title>
  <script type="text/javascript" language="javascript" src="js/jquery-3.0.0.min.js"></script>
  <script language="JavaScript" type="text/javascript" src="js/l.js"></script>
</head>
<body>

<form action="<?php print BASE_URL;?>/admin.php" method="post">
  <table>
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Password</th>
      <th>Bet</th>
      <th>Draws</th>
      <th>Played</th>
      <th>Admin</th>
      <th></th>
    </tr>
  <?php foreach ($all_players as $player_id => $player_info): ?>
    <tr>
    <td><?php print $player_id; ?></td>
    <td><input type="text" name="name[<?php print $player_id; ?>]" value="<?php print $player_info['name']; ?>" /></td>
    <td><input type="password" name="pass[<?php print $player_id; ?>]" /></td>
    <td>&euro; <input type="text" name="bet[<?php print $player_id; ?>]" value="<?php print isset($player_info['bet']) ? $player_info['bet'] : ''; ?>" size="4" /></td>
    <td><input type="text" name="max_draws[<?php print $player_id; ?>]" value="<?php print isset($player_info['max_draws']) ? $player_info['max_draws'] : ''; ?>" size="4" /></td>
    <td><input type="text" name="played_draws[<?php print $player_id; ?>]" value="<?php print isset($player_info['played_draws']) ? $player_info['played_draws'] : ''; ?>" size="4" /></td>
    <td><input type="checkbox" name="role[<?php print $player_id; ?>]" value="1"
    <?php if ($player_info['role'] == 1) print ' checked'; ?> /></td>
    <td><a href="<?php print BASE_URL; ?>/admin.php?delete=<?php print $player_id; ?>" class="delete">Delete</a>
    </tr>
  <?php endforeach; ?>
  <tr>
  <td>New</td><td><input type="text" name="name[new]" /></td>
  <td><input type="password" name="pass[new]" /></td>
  </tr>
  </table>
  <input type="submit" />
</form>

<p>
<table>
  <tr>
    <th>Current round: <?php print $current_round; ?></th>
  </tr>
  <tr>
    <td><a href="<?php print BASE_URL; ?>/admin.php?new_round">Start new round</a></td>
  </tr>
</table>
</p>

<p>
<form method="post">
  <table>
    <?php $player_numbers = $players->getNumbers($current_round); ?>
    <?php foreach ($all_players as $player_id => $player_info): ?>
      <tr>
        <td><?php print $player_id; ?></td>
        <td><?php print $player_info['name']; ?></td>
        <td>
          <?php if (isset($player_numbers[$player_id])): ?>
            <?php foreach ($player_numbers[$player_id] as $key => $number): ?>
              <input type="text" name="player_number[<?php print $player_id; ?>][<?php print $key; ?>]" value="<?php print $number['number']; ?>" size="6" disabled />
            <?php endforeach; ?>
          <?php else: ?>
            <?php for ($i = 0; $i < 10; $i++): ?>
              <input type="text" name="player_number[<?php print $player_id; ?>][<?php print $i; ?>]" size="6" />
            <?php endfor; ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <input type="submit" />
</form>
</p>

</body>
</html>
