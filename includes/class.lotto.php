<?php

class lotto {
  protected $errors = array();

  protected $messages = array();

  public function getErrors() {
    return $this->errors;
  }

  private function setError($error) {
    $this->errors[] = $error;
  }

  public function getMessages() {
    return $this->messages;
  }

  public function setMessage($message) {
    $this->messages[] = $message;
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

  public function getCurrentRound() {
    $round_id = 0;

    $db = MysqliDb::getInstance();
    $db->where('end = 0');
    $result = $db->get('rounds', 1);
    if (!empty($result)) {
      $round_id = $result[0]['round_id'];
    }

    return $round_id;
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

    $db->where('round_id', $round);
    $results = $db->get('rounds', 1);

    if (empty($results)) {
      return FALSE;
    }

    $round = $results[0];
    $start_year = date('Y', $round['start']);
    $start_month = date('m', $round['start']);

    $results = array();
    if (date('Y') == $start_year) {
      for ($i = $start_month; $i <= date('m'); $i++) {
        $results += $this->getResultsByMonth($start_year, $i);
      }
    }
    else {
      for ($i = $start_month; $i <= 12; $i++) {
        $results += $this->getResultsByMonth($start_year, $i);
      }
      for ($i = $start_year; $i <= date('Y'); $i++) {
        if ($i == date('Y')) {
          for ($j = 1; $j <= date('m'); $j++) {
            $results += $this->getResultsByMonth($i, $j);
          }
        }
        else {
          for ($j = 1; $j <= 12; $j++) {
            $results += $this->getResultsByMonth($i, $j);
          }
        }
      }
    }

    $all_results = $db->get('results');
    $results_by_id = array();
    foreach ($all_results as $result) {
      $results_by_id[$result['draw_id']] = $result;
    }

    foreach ($results as $did => $draw_data) {
      if (isset($results_by_id[$did])) {
        continue;
      }

      $id = $db->insert('results', array(
        'draw_id' => $did,
        'date' => $draw_data['date'],
        'super' => $draw_data['super'] ? 1 : 0,
        'numbers' => serialize($draw_data['numbers']),
        'round_id' => $round['round_id'],
      ));

      if (empty($id)) {
        $this->setError('Failed inserting draw ' . $did . ' (error: ' . $db->getLastError() . ')');
      }
      else {
        if ($this->updateRoundNumbers($round['round_id'], $draw_data['numbers'])) {
          players::increasePlayedDraws();
        }
      }
    }

    return TRUE;
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
    $db->where('end = 0');
    $round = $db->get('rounds', 1);

    if (empty($round)) {
      $this->setError('Round ' . $round_id . ' not found.');
      return FALSE;
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
        if (empty($update)) {
          $this->setError('Failed updating round ' . $round_id . ' (error: ' . $db->getLastError() . ')');
          return FALSE;
        }
      }
    }
    return TRUE;
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
