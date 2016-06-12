<?php

class lotto {
  protected $errors = array();

  public function getErrors() {
    return $this->errors;
  }

  private function setError($error) {
    $this->errors[] = $error;
  }

  public function getResultsByYear($year = 0) {
    $results = array();
    if (empty($year) || $year > date('Y')) {
      $year = date('Y');
    }
    for ($i = 1; $i <= date('m'); $i++) {
      $results[$i] = $this->getResultsByMonth($year, $i);
    }
    return $results;
  }

  public function getResultsByMonth($year = 0, $month = 0) {
    $results = array();
    if (empty($year) || $year > date('Y')) {
      $year = date('Y');
    }
    if (empty($month)) {
      $month = date('m');
    }
    $html = $this->retrieveLottoHtml('https://www.lotto.nl/lotto/async/getDrawsOfMonth.html?firstDrawOfMonth=' . $month . '/1/' . $year . '&gameId=5155');
    preg_match_all('/<option value="(.*)">(.*)<\/option>/m', $html, $matches);
    if (!empty($matches[1])) {
      foreach ($matches[1] as $key => $draw_id) {
        if (!empty($matches[2][$key])) {
          $parts = explode(' ', $matches[2][$key]);
          if (!empty($parts[5])) {
            if (substr($parts[0], 0, 1) == 1) {
              $results[$draw_id] = array(
                'date' => strtotime($parts[5] . '-' . $month . '-' . $year),
                'super' => FALSE,
                'numbers' => $this->getResultsByDrawID($draw_id),
              );
            }
          }
          elseif (!empty($parts[1])) {
            $results[$draw_id] = array(
              'date' => strtotime($parts[1] . '-' . $month . '-' . $year),
              'super' => FALSE,
              'numbers' => $this->getResultsByDrawID($draw_id),
            );
          }
        }
      }
    }
    return $results;
  }

  public function getResultsByDrawID($draw_id) {
    $html = $this->retrieveLottoHtml('https://www.lotto.nl/lotto/async/getResultsForSpecificDraw.html?drawId=' . $draw_id . '&gameId=5155');
    preg_match_all('/<li class="ball">(.*)<\/li>/', $html, $matches);
    if (!empty($matches[1])) {
      return $matches[1];
    }
    return FALSE;
  }

  public function getResultsByRound($round = 0) {
    $results = array();

    $db = MysqliDb::getInstance();
    $db->orderBy('results.draw_id', 'DESC');
    $db->where('results.round_id', $round);
    $result = $db->get('results');

    if (!empty($result)) {
      foreach ($result as $row) {
        $results[$row['draw_id']] = $row;
      }
    }

    return $results;
  }

  private function retrieveLottoHtml($url) {
    return file_get_contents($url);
  }

  public function sync($round = 0) {
    $db = MysqliDb::getInstance();

    $db->orderBy('date');
    $last_did = $db->get('results', 1, array('draw_id'));
    if (!empty($last_did[0]['draw_id'])) {
      $last_did = $last_did[0]['draw_id'];

      $results = $this->getResultsByMonth();
      foreach ($results as $did => $draw_data) {
        if ($did > $last_did) {
          $id = $db->insert('results', array(
            'draw_id' => $did,
            'date' => $draw_data['date'],
            'super' => $draw_data['super'] ? 1 : 0,
            'numbers' => serialize($draw_data['numbers']),
            'round_id' => $round,
          ));

          if (empty($id)) {
            $this->setError('Failed inserting draw ' . $did . ' (error: ' . $db->getLastError() . ')');
          }
          else {
            $this->updateRoundNumbers($round, $draw_data['numbers']);
            players::increasePlayedDraws();
          }
        }
      }
    }
    else {
      $results = $this->getResultsByYear();
      foreach ($results as $month => $dids) {
        foreach ($dids as $did => $draw_data) {
          $id = $db->insert('results', array(
            'draw_id' => $did,
            'date' => $draw_data['date'],
            'super' => $draw_data['super'] ? 1 : 0,
            'numbers' => serialize($draw_data['numbers']),
            'round_id' => $round,
          ));

          if (empty($id)) {
            $this->setError('Failed inserting draw ' . $did . ' (error: ' . $db->getLastError() . ')');
          }
          else {
            $this->updateRoundNumbers($round, $draw_data['numbers']);
          }
        }
      }
    }
  }

  public function getRoundNumbers($round_id = 0) {
    $numbers = array();

    $db = MysqliDb::getInstance();
    $db->where('round_id', $round_id);
    $round = $db->get('rounds', 1);
    if (empty($round)) {
      $this->setError('Round ' . $round_id . ' not found.');
      return $numbers;
    }

    if (!empty($round[0]['drawn_numbers'])) {
      $numbers = unserialize($round[0]['drawn_numbers']);
    }

    return $numbers;
  }

  private function updateRoundNumbers($round_id, $numbers) {
    $db = MysqliDb::getInstance();
    $db->where('round_id', $round_id);
    $round = $db->get('rounds', 1);

    if (empty($round)) {
      $this->setError('Round ' . $round_id . ' not found.');
    }
    else {
      $round = $round[0];
      $round_numbers = array();
      if (!empty($round['drawn_numbers'])) {
        $round_numbers = unserialize($round['drawn_numbers']);
      }

      $diff = array_diff($numbers, $round_numbers);
      if (!empty($diff)) {
        $round_numbers = array_merge($round_numbers, $diff);
        $db->where('round_id', $round_id);
        $update = $db->update('rounds', array(
          'drawn_numbers' => serialize($round_numbers),
        ));
        if ($update) {
          return TRUE;
        }
        else {
          $this->setError('Failed updating round ' . $round_id . ' (error: ' . $db->getLastError() . ')');
        }
      }
    }
    return FALSE;
  }

  public function newRound() {
    $db = MysqliDb::getInstance();
    $round_id = $db->insert('rounds', array(
      'start' => time(),
    ));
    if (empty($round_id)) {
      $this->setError('Failed creating new round (error: ' . $db->getLastError() . ')');
      return FALSE;
    }
    return $round_id;
  }

  public function endRound($round_id = 0) {
    $db = MysqliDb::getInstance();
    $db->where('round_id', $round_id);
    $result = $db->update('rounds', array(
      'end' => time(),
    ));
    if (empty($result)) {
      $this->setError('Failed updating round ' . $round_id . ' (error: ' . $db->getLastError() . ')');
      return FALSE;
    }
    return TRUE;
  }

}
