<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * propuh implementation : © Matheus Gomes matheusgomesforwork@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * propuh game states description
 *
 */

// define contants for state ids
if (!defined('ST_GAME_END')) { // ensure this block is only invoked once, since it is included multiple times
    define("ST_GRANNY_MOVE", 2);
    define("ST_PLAYER_TURN", 3);
    define("ST_BETWEEN_PLAYERS", 4);
    define("ST_RESOLVE_TRICK", 5);
    define("ST_BETWEEN_ROUNDS", 6);
    define("ST_SOLO_TURN", 7);
    define("ST_GAME_END", 99);
}

$machinestates = [

    // The initial state. Please do not modify.

    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => 2]
    ),

    ST_GRANNY_MOVE => [
        "name" => "grannyMove",
        "description" => clienttranslate('${actplayer} may move the Granny Standee to a new location'),
        "descriptionmyturn" => clienttranslate('${you} may move the Granny Standee to a new location'),
        "type" => "activeplayer",
        "args" => "arg_grannyMove",
        "possibleactions" => [
            "actMoveGranny",
            "actSkipGrannyMove",
        ],
        "transitions" => [
            "playerTurn" => ST_PLAYER_TURN,
            "skip" => ST_PLAYER_TURN
        ],
        "updateGameProgression" => true,
    ],

    ST_PLAYER_TURN => [
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card'),
        "descriptionmyturn" => clienttranslate('${you} must play a card'),
        "type" => "activeplayer",
        "args" => "arg_playerTurn",
        "possibleactions" => [
            "actPlayCard",
            "actUndoSkipGrannyMove"
        ],
        "transitions" => [
            "nextPlayer" => ST_BETWEEN_PLAYERS,
            "undo" => ST_GRANNY_MOVE,
        ],
        "updateGameProgression" => true,
    ],

    ST_BETWEEN_PLAYERS => [
        "name" => "betweenPlayers",
        "description" => "",
        "descriptionmyturn" => "",
        "type" => "game",
        "action" => "st_betweenPlayers",
        "transitions" => [
            "nextPlayer" => ST_PLAYER_TURN,
            "nextTrick" => ST_RESOLVE_TRICK,
            "soloTurn" => ST_SOLO_TURN,
        ],
    ],

    ST_RESOLVE_TRICK => [
        "name" => "resolveTrick",
        "description" => clienttranslate("Resolving trick..."),
        "descriptionmyturn" => clienttranslate("Resolving trick..."),
        "type" => "game",
        "action" => "st_resolveTrick",
        "transitions" => [
            "nextTrick" => ST_PLAYER_TURN,
            "nextRound" => ST_BETWEEN_ROUNDS,
            "soloTurn" => ST_SOLO_TURN,
        ],
    ],

    ST_BETWEEN_ROUNDS => [
        "name" => "betweenRounds",
        "description" => clienttranslate("Finishing round..."),
        "descriptionmyturn" => clienttranslate("Finishing round..."),
        "type" => "game",
        "action" => "st_betweenRounds",
        "transitions" => [
            "nextRound" => ST_GRANNY_MOVE,
            "gameEnd" => ST_GAME_END
        ],
    ],

    ST_SOLO_TURN => [
        "name" => "soloTurn",
        "description" => clienttranslate("Playing card for Propuh..."),
        "descriptionmyturn" => clienttranslate("Playing card for Propuh..."),
        "type" => "game",
        "action" => "st_soloTurn",
        "transitions" => ["realPlayer" => ST_BETWEEN_PLAYERS],
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    ST_GAME_END => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    ],

];
