<?php

class players {
  protected $errors = array();

  public function getErrors() {
    return $this->errors;
  }

  private function setError($error) {
    $this->errors[] = $error;
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
      foreach ($numbers as $number) {
        if (in_array($number['number'], $round_numbers)) {
          $matching_numbers[] = $number['number'];
        }
      }

      if (!empty($matching_numbers)) {
        $matching_numbers = array_unique($matching_numbers);
        $this->markNumbers($player_id, $matching_numbers, $round_id);
      }
    }
  }

  private function markNumbers($player_id, $matching_numbers, $round_id = 0) {
    if (empty($player_id) || empty($matching_numbers)) {
      return FALSE;
    }

    $db = MysqliDb::getInstance();
    $db->where('player_id', $player_id);
    $db->where('number', $matching_numbers, 'IN');
    $db->where('round_id', $round_id);
    $result = $db->update('player_numbers', array(
      'drawn' => 1,
    ));

    if (empty($result)) {
      $this->setError('Failed marking the numbers (' . implode(',', $matching_numbers) . ') of player ' . $player_id . ' (error: ' . $db->getLastError() . ')');
      return FALSE;
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
}