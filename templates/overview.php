<section id="main">

  <div class="content">

    <h1>Familie Lotto</h1>
    <h2>Ronde <?php print $current_round; ?></h2>

    <?php if (!empty($messages)): ?>
      <div class="yellow">
        <?php print implode('<br>', $messages); ?>
      </div>
    <?php endif; ?>

    <div class="players yellow">
      <h2>Spelers</h2>
      <?php if (empty($active_players)): ?>
        Er zijn geen spelers gevonden.
      <?php else: ?>
        <div class="wrapper">
          <?php foreach ($active_players as $player_id => $player): ?>
            <?php
            $won = TRUE;
            foreach ($player['numbers'] as $numbers) {
              if (!$numbers['drawn']) {
                $won = FALSE;
              }
            }
            ?>
            <div class="player<?php if ($won) print ' won'; ?>">
              <h3 <?php if ($player['played_draws'] >= $player['max_draws']) print 'class="expired"'; ?> title="<?php print $player['played_draws']; ?> van <?php print $player['max_draws']; ?> trekkingen gespeeld"><?php print $player['name']; ?></h3>
              <ul class="draw">
                <?php foreach ($player['numbers'] as $numbers): ?>
                  <li class="ball<?php if ($numbers['drawn']) print ' drawn'; ?><?php if ($numbers['draw_id'] == $last_draw) print ' new'; ?>">
                    <?php print $numbers['number']; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="players yellow">
      <h2>Alle nummers van deze ronde</h2>
      <div class="wrapper">
        <div class="player">
          <ul class="draw">
            <?php
            $round_numbers = $lotto->getRoundNumbers($current_round);
            for ($i = 1; $i <= 45; $i++) {
              print '<li class="ball';
              if (isset($round_numbers[$i])) {
                print ' drawn';
              }
              print '">' . $i . '</li>';
            }
            ?>
          </ul>
        </div>
      </div>
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
