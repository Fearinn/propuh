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
if (!defined('ST_END_GAME')) { // ensure this block is only invoked once, since it is included multiple times
    define("ST_PLAYER_TURN", 2);
    define("ST_BETWEEN_PLAYERS", 3);
    define("ST_RESOLVE_TRICK", 4);
    define("ST_BETWEEN_ROUNDS", 5);
    define("ST_END_GAME", 99);
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

    ST_PLAYER_TURN => [
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card'),
        "descriptionmyturn" => clienttranslate('${you} must play a card'),
        "type" => "activeplayer",
        "args" => "arg_playerTurn",
        "possibleactions" => [
            "actPlayCard",
        ],
        "transitions" => ["nextPlayer" => ST_BETWEEN_PLAYERS],
    ],

    ST_BETWEEN_PLAYERS => [
        "name" => "betweenPlayers",
        "description" => "",
        "descriptionmyturn" => "",
        "type" => "game",
        "action" => "st_betweenPlayers",
        "transitions" => [
            "nextPlayer" => ST_PLAYER_TURN,
            "nextTrick" => ST_RESOLVE_TRICK
        ],
    ],

    ST_RESOLVE_TRICK => [
        "name" => "resolveTrick",
        "description" => clienttranslate("Resolving trick..."),
        "descriptionmyturn" => clienttranslate("Resolving trick..."),
        "type" => "game",
        "action" => "st_resolveTrick",
        "transitions" => ["nextTrick" => ST_PLAYER_TURN, "nextRound" => ST_BETWEEN_ROUNDS],
    ],

    ST_BETWEEN_ROUNDS => [
        "name" => "betweenRounds",
        "description" => clienttranslate("Finishing round..."),
        "descriptionmyturn" => clienttranslate("Finishing round..."),
        "type" => "game",
        "action" => "st_betweenRounds",
        "transitions" => ["nextRound" => ST_PLAYER_TURN],
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    ST_END_GAME => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    ],

];
