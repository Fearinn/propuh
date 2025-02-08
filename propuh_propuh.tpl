{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- Propuh implementation : © Matheus Gomes matheusgomesforwork@gmail.com
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------
-->

<div id="pph_gameArea" class="pph_gameArea">
  <div id="pph_discard" class="pph_discard"></div>
  <div id="pph_boards" class="pph_boards">
    <div id="pph_deck" class="pph_deck"></div>
    <div id="pph_goalsContainer" class="pph_goalsContainer whiteblock">
      <ul id="pph_goals-granny" class="pph_goals">
        <li>
          <h4
            id="pph_goalsTitle-granny"
            class="pph_goalsTitle"
            style="color: green"
          ></h4>
        </li>
      </ul>
      <ul id="pph_goals-propuh" class="pph_goals">
        <li>
          <h4
            id="pph_goalsTitle-propuh"
            class="pph_goalsTitle"
            style="color: red"
          ></h4>
        </li>
      </ul>
    </div>
    <div id="pph_house" class="pph_house pph_board">
      <div
        id="pph_locationTable"
        class="pph_locationTable pph_location"
        data-location="1"
      ></div>
      <div
        id="pph_locationStove"
        class="pph_locationStove pph_location"
        data-location="2"
      ></div>
      <div
        id="pph_locationBed"
        class="pph_locationBed pph_location"
        data-location="3"
      ></div>
    </div>
    <div id="pph_table" class="pph_table pph_board" data-board="1">
      <div id="pph_tableCards" class="pph_boardCards"></div>
      <div id="pph_tableTokens"></div>
    </div>
    <div id="pph_stove" class="pph_stove pph_board" data-board="2">
      <div id="pph_stoveCards" class="pph_boardCards"></div>
      <div id="pph_stoveTokens"></div>
    </div>
    <div id="pph_bed" class="pph_bed pph_board" data-board="3">
      <div id="pph_bedCards" class="pph_boardCards"></div>
      <div id="pph_bedTokens"></div>
    </div>
  </div>
</div>

<script type="text/javascript"></script>

{OVERALL_GAME_FOOTER}
