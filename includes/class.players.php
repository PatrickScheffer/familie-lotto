<?php

class players {
  protected $errors = array();

  public function getErrors() {
    return $this->errors;
  }

  private function setError($error) {
    $this->errors[] = $error;
  }

  public function loadPlayer($player_id) {
    $player = array();

    $db = MysqliDb::getInstance();
    $db->where('player_id', $player_id);
    $result = $db->get('players', 1);
    if (!empty($result)) {
      $player = reset($result);
    }

    return $player;
  }

  public function getActivePlayers() {
    $players = array();

    $db = MysqliDb::getInstance();
    $db->orderBy('players.name', 'ASC');
    $db->join('players', 'players.player_id = bets.player_id');
    $db->where('bets.played_draws <= bets.max_draws');
    $result = $db->get('bets');

    if (!empty($result)) {
      foreach ($result as $row) {
        $players[$row['player_id']] = $row;
      }
    }

    return $players;
  }

  public function getNumbers($round_id = 0, $players = array()) {
    $numbers = array();

    $db = MysqliDb::getInstance();
    if (!empty($round_id)) {
      $db->where('player_numbers.round_id', $round_id);
    }
    if (!empty($players)) {
      $db->where('player_id', $players, 'IN');
    }
    $result = $db->get('player_numbers');

    if (!empty($result)) {
      foreach ($result as $row) {
        $numbers[$row['player_id']][] = $row;
      }
    }

    return $numbers;
  }

  public function checkNumbers($round_id = 0, $players = array()) {
    if (empty($players)) {
      $players = $this->getActivePlayers();
    }
    else {
      $players = array_flip($players);
    }
    if (empty($players)) {
      return FALSE;
    }

    $lotto = new lotto();
    $round_numbers = $lotto->getRoundNumbers($round_id);

    $player_ids = array_keys($players);
    $player_numbers = $this->getNumbers($round_id, $player_ids);

    foreach ($player_numbers as $player_id => $numbers) {
      $matching_numbers = array();
      foreach ($numbers as $key => $number) {
        if (isset($round_numbers[$number['number']]) && !$player_numbers[$player_id][$key]['drawn']) {
          $draw_id = $round_numbers[$number['number']];
          $matching_numbers[$draw_id][] = $number['number'];
          $player_numbers[$player_id][$key]['drawn'] = TRUE;
        }
      }

      if (!empty($matching_numbers)) {
        $this->markNumbers($player_id, $matching_numbers, $round_id);
      }
    }

    $players_won = array();
    foreach ($player_numbers as $player_id => $numbers) {
      $all_numbers_drawn = TRUE;
      foreach ($numbers as $key => $number) {
        if (empty($number['drawn'])) {
          $all_numbers_drawn = FALSE;
          break;
        }
      }
      if ($all_numbers_drawn) {
        $player = $this->loadPlayer($player_id);
        $players_won[] = $player['name'];
      }
    }

    if (!empty($players_won)) {
      $message = '';
      if (count($players_won) == 1) {
        if (!strstr($players_won[0], ' en ')) {
          $message = '<b>' . $players_won[0] . '</b> heeft deze ronde gewonnen! Gefeliciteerd!';
        }
        else {
          $message = '<b>' . $players_won[0] . '</b> hebben deze ronde gewonnen! Gefeliciteerd!';
        }
      }
      else {
        sort($players_won);
        for ($i = 0; $i < count($players_won); $i++) {
          $message .= '<b>' . $players_won[$i] . '</b>';
          if ($i < count($players_won) - 1) {
            if ($i == count($players_won) - 2) {
              $message .= ' en ';
            }
            else {
              $message .= ', ';
            }
          }
        }
        $message .= ' hebben deze ronde gewonnen! Gefeliciteerd!';
      }
      $lotto->setMessage('end', $message);
      $lotto->endRound($round_id);
    }

    return TRUE;
  }

  private function markNumbers($player_id, $matching_numbers, $round_id = 0) {
    if (empty($player_id) || empty($matching_numbers)) {
      return FALSE;
    }

    foreach ($matching_numbers as $draw_id => $numbers) {
      $db = MysqliDb::getInstance();
      $db->where('player_id', $player_id);
      $db->where('number', $numbers, 'IN');
      $db->where('round_id', $round_id);
      $result = $db->update('player_numbers', array(
        'drawn' => 1,
        'draw_id' => $draw_id,
      ));

      if (empty($result)) {
        $this->setError('Failed marking the numbers (' . implode(',', $numbers) . ') of player ' . $player_id . ' (error: ' . $db->getLastError() . ')');
        return FALSE;
      }
    }

    return TRUE;
  }

  public static function increasePlayedDraws() {
    $db = MysqliDb::getInstance();
    $db->where('played_draws < max_draws');
    $result = $db->get('bets');

    if (!empty($result)) {
      foreach ($result as $row) {
        $played_draws = $row['played_draws'] + 1;
        $db->where('bet_id', $row['bet_id']);
        $update = $db->update('bets', array(
          'played_draws' => $played_draws,
        ));

        if (empty($update)) {
          self::setError('Failed increasing the played draws for player ' . $row['player_id'] . ' (error: ' . $db->getLastError() . ')');
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  public function isLoggedIn() {
    return isset($_SESSION[md5($_SERVER['SERVER_ADDR'] . $_SERVER['REMOTE_ADDR'])]);
  }

  public function isAdmin() {
    return !empty($_SESSION[md5($_SERVER['SERVER_ADDR'] . $_SERVER['REMOTE_ADDR'])]['role']);
  }

  public function login($username, $password) {
    $db = MysqliDb::getInstance();
    $db->where('name', $username);
    $db->where('password', crypt($password, SECRET));
    $result = $db->get('players', 1);

    if (isset($result[0])) {
      $_SESSION[md5($_SERVER['SERVER_ADDR'] . $_SERVER['REMOTE_ADDR'])] = array(
        'id' => $result[0]['player_id'],
        'name' => $result[0]['name'],
        'role' => $result[0]['role'],
      );
      return TRUE;
    }

    return FALSE;
  }

  public function logout() {
    unset($_SESSION[md5($_SERVER['SERVER_ADDR'] . $_SERVER['REMOTE_ADDR'])]);
  }
}
