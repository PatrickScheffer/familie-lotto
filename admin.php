<?php
require_once('bootstrap.php');

if (!$players->isAdmin()) {
  header('Location: ' . BASE_URL);
}

$db = MysqliDb::getInstance();

if (isset($_GET['delete'])) {
  $db->where('player_id', htmlspecialchars($_GET['delete'], ENT_QUOTES));
  $db->delete('players');
  header('Location: ' . BASE_URL . '/admin.php');
}

if (!empty($_POST['name'])) {
  foreach ($_POST['name'] as $player_id => $name) {
    if (empty(trim($player_id)) || empty(trim($name))) {
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
        $db->insert('players', $new_data);
      }
    }
    else {
      $db->where('player_id', htmlspecialchars($player_id, ENT_QUOTES));
      $db->update('players', $new_data);
    }
  }
}

$lotto = new lotto();
$players = new players();

$all_players = array();

$db = MysqliDb::getInstance();
$db->orderBy('player_id', 'ASC');
$result = $db->get('players');

if (!empty($result)) {
  foreach ($result as $row) {
    $all_players[$row['player_id']] = $row;
  }
}

$current_round = $lotto->getCurrentRound();
?>

<form action="<?php print BASE_URL;?>/admin.php" method="post">
  <table>
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Password</th>
      <th>Admin</th>
      <th></th>
    </tr>
  <?php foreach ($all_players as $player_id => $player_info): ?>
    <tr>
    <td><?php print $player_id; ?></td>
    <td><input type="text" name="name[<?php print $player_id; ?>]" value="<?php print $player_info['name']; ?>" /></td>
    <td><input type="password" name="pass[<?php print $player_id; ?>]" /></td>
    <td><input type="checkbox" name="role[<?php print $player_id; ?>]" value="1"
    <?php if ($player_info['role'] == 1) print ' checked'; ?> /></td>
    <td><a href="<?php print BASE_URL; ?>/admin.php?delete=<?php print $player_id; ?>">Delete</a>
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
<table>
  <?php $player_numbers = $players->getNumbers(); ?>
  <?php foreach ($all_players as $player_id => $player_info): ?>
    <tr>
      <td><?php print $player_id; ?></td>
      <td><?php print $player_info['name']; ?></td>
      <td>
        <?php if (isset($player_numbers[$player_id])): ?>
          <?php foreach ($player_numbers[$player_id] as $key => $number): ?>
            <input type="text" name="player_number[<?php print $player_id; ?>][<?php print $key; ?>" value="<?php print $number['number']; ?>" size="6" />
          <?php endforeach; ?>
        <?php endif;?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
</p>
