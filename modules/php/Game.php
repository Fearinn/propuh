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
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */

declare(strict_types=1);

namespace Bga\Games\propuh;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\Propuh\CardManager;
use Bga\Games\Propuh\TokenManager;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");

const GRANNY = "granny";
const PROPUH = "propuh";
const ATTACK_CARD = "attackCard";
const RESOLVE_TRICK = "resolveTrick";
const PLAY_COUNT = "playCount";
const GRANNY_LOCATION = "grannyLocation";
const MOVED_GRANNY = "movedGranny";
const COMPLETED_GOALS = "completedGoals";

class Game extends \Table
{
    private array $CARDS;
    private array $ROLES;
    private array $RANDOM_DIFFICULTY;
    public string $deckFields = "card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg";

    public function __construct()
    {
        parent::__construct();

        require "material.inc.php";

        $this->initGameStateLabels([
            "soloDifficulty" => 100,
            "randomDifficulty" => 101,
        ]);

        $this->cards = $this->getNew("module.common.deck");
        $this->cards->init("card");

        $this->tokens = $this->getNew("module.common.deck");
        $this->tokens->init("token");

        $this->notify->addDecorator(fn(string $message, array $args) => $this->decoratePlayerNameNotifArg($message, $args));
    }


    /*  UTILITY FUNCTIONS */

    public function isSolo(): bool
    {
        return $this->getPlayersNumber() === 1;
    }

    public function solo_difficulty(): int
    {
        return (int) $this->getGameStateValue("soloDifficulty");
    }

    public function solo_randomDifficulty(): int
    {
        return (int) $this->getGameStateValue("randomDifficulty");
    }

    public function custom_incStat(int $inc, string $name, int $player_id)
    {
        if ($player_id !== 1) {
            $this->incStat($inc, $name, $player_id);
        }
    }

    public function custom_setStat(int $value, string $name, int $player_id)
    {
        if ($player_id !== 1) {
            $this->setStat($value, $name, $player_id);
        }
    }

    public function decoratePlayerNameNotifArg(string $message, array $args): array
    {
        if (isset($args["player_id"])) {
            $player_id = (int) $args["player_id"];
            if (!isset($args["player_name"]) && str_contains($message, '${player_name}')) {
                $args["player_name"] = $player_id === 1 ? "Propuh" : $this->getPlayerNameById($player_id);
            }

            if (!isset($args["role_label"]) && str_contains($message, '${role_label}')) {
                $player_role = $this->getPlayerRole($player_id);
                $args["role_label"] = $this->ROLES[$player_role]["label"];
            }
        }
        return $args;
    }

    public function getHand($player_id, $associative = true): array
    {
        $hand = $this->cards->getPlayerHand($player_id);

        if (!$associative) {
            $hand = array_values($hand);
        }

        return $hand;
    }

    public function getPlayedCards(?int $location_id): array
    {
        $playedCards = $this->getCollectionFromDB("SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg
        FROM card WHERE card_location IN (1, 2, 3)");

        return array_values($playedCards);
    }

    public function hideCards(array $cards): array
    {
        $hiddenCards = [];
        foreach ($cards as $card_id => $card) {
            $card["type"] = null;
            $card["type_arg"] = null;
            $hiddenCards[] = $card;
        }

        return $hiddenCards;
    }

    public function countPlacedTokens(int $location_id, string $player_role): int
    {
        return count($this->tokens->getCardsOfTypeInLocation($player_role, null, $location_id));
    }

    public function getPlacedTokens(): array
    {
        $placedTokens = $this->getCollectionFromDB("SELECT $this->deckFields FROM token WHERE card_location IN (1, 2, 3)");
        return array_values($placedTokens);
    }

    public function getPlayerRole(int $player_id): ?string
    {
        $players = $this->loadPlayersBasicInfos();

        if ($player_id === 1) {
            return PROPUH;
        }

        if (!array_key_exists($player_id, $players)) {
            return GRANNY;
        }

        return $this->getUniqueValueFromDB("SELECT player_role FROM player WHERE player_id=$player_id");
    }

    public function grannyId(): int
    {
        return (int) $this->getUniqueValueFromDB("SELECT player_id FROM player WHERE player_role='granny'");
    }

    public function propuhId(): int
    {
        if ($this->isSolo()) {
            return 1;
        }

        return (int) $this->getUniqueValueFromDB("SELECT player_id FROM player WHERE player_role='propuh'");
    }

    public function placeToken(int $location_id, int $player_id)
    {
        $tokenCard_id = (int) $this->getUniqueValueFromDB("SELECT card_id FROM token WHERE card_location='hand' AND card_location_arg=$player_id LIMIT 1");

        if (!$tokenCard_id) {
            $this->notify->all(
                "message",
                clienttranslate('${player_name} (${role_label}) may not place more tokens'),
                [
                    "player_id" => $player_id,
                ]
            );
            return;
        }

        $token = new TokenManager($tokenCard_id, $this);
        $token->place($location_id, $player_id);
    }

    public function discardToken(): void
    {
        $player_id = (int) $this->grannyId();

        $location_id = $this->globals->get(GRANNY_LOCATION);
        $tokenCard_id = (int) $this->getUniqueValueFromDB("SELECT card_id FROM token WHERE card_location=$location_id AND card_type='propuh' LIMIT 1");

        if ($tokenCard_id) {
            $token = new TokenManager($tokenCard_id, $this);
            $token->discard($location_id, $player_id);
        }
    }

    public function drawCards(): void
    {
        $players = $this->loadPlayersBasicInfos();
        $drawnCards = [];
        $deckCount = (int) $this->cards->countCardsInLocation("deck");

        foreach ($players as $player_id => $player) {
            if ($deckCount <= 1) {
                continue;
            }

            $cards = $this->cards->pickCards(2, "deck", $player_id);
            $cards = array_values($cards);

            $drawnCards[$player_id] = $this->hideCards($cards);

            $this->notify->player(
                $player_id,
                "drawCards",
                "",
                [
                    "player_id" => $player_id,
                    "cards" => $cards
                ]
            );
        }

        $msg = $this->isSolo() ? clienttranslate("New round: the Granny draws 2 cards") : clienttranslate("New round: each player draws 2 cards from the deck");

        if ($deckCount <= 1) {
            $msg = clienttranslate("New round: can't deal cards");
        }

        $this->notify->all(
            "newRound",
            $msg,
            [
                "cards" => $drawnCards,
            ]
        );
    }

    public function checkGoals($defineWinner = true): bool
    {
        $completedGoals = $this->globals->get(COMPLETED_GOALS);

        $winner_id = null;
        foreach ($this->ROLES as $player_role => $role_info) {
            $player_id = $player_role === GRANNY ? $this->grannyId() : $this->propuhId();
            $goals = $role_info["goals"];
            $goalsMet = true;

            foreach ($goals as $goal_id => $goal) {
                $tokenGoal = $goal["tokens"];

                if ($goal_id === 4) {
                    $tokenCount = -$this->countPlacedTokens(2, PROPUH);
                } else {
                    $tokenCount = $this->countPlacedTokens($goal_id, $player_role);
                }

                if ($tokenCount < $tokenGoal) {
                    $goalsMet = false;

                    if ($completedGoals[$player_role][$goal_id]) {
                        $this->notify->all(
                            "incompleteGoal",
                            "",
                            [
                                "player_id" => $player_id,
                                "goal_id" => $goal_id,
                                "player_role" => $player_role,
                            ]
                        );

                        $completedGoals[$player_role][$goal_id] = false;
                        $this->globals->set(COMPLETED_GOALS, $completedGoals);
                    }
                    continue;
                }

                if (!$completedGoals[$player_role][$goal_id]) {
                    $this->notify->all(
                        "completeGoal",
                        "",
                        [
                            "player_id" => $player_id,
                            "goal_id" => $goal_id,
                            "player_role" => $player_role,
                        ]
                    );

                    $completedGoals[$player_role][$goal_id] = true;
                    $this->globals->set(COMPLETED_GOALS, $completedGoals);
                }
            }

            if ($goalsMet) {
                $winner_id = $player_id;

                if ($player_role === PROPUH) {
                    break;
                }
            }
        }

        if (!$defineWinner) {
            return false;
        }

        if (!$winner_id && (int) $this->cards->countCardsInLocation("discard") === 28) {
            $winner_id = $this->propuhId();
        }

        if ($winner_id) {
            $this->defineWinner($winner_id);
            return true;
        }

        return false;
    }

    public function defineWinner($winner_id): void
    {
        $player_role = $this->getPlayerRole($winner_id);
        $this->custom_setStat(100, "{$player_role}Win%", $winner_id);

        if ($this->isSolo()) {
            $this->custom_setStat(100, "soloWin%", $winner_id);

            $difficulty = $this->solo_difficulty();
            $this->custom_setStat(100, "soloWin%-{$difficulty}", $winner_id);

            if ($difficulty === 4) {
               $randomDifficulty = $this->solo_randomDifficulty();
               $this->custom_setStat(100, "randomWin%-{$randomDifficulty}", $winner_id);
            }
        }

        if ($winner_id === 1) {
            $granny_id = $this->grannyId();
            $this->DbQuery("UPDATE player SET player_score=-1 WHERE player_id=$granny_id");
            return;
        }

        $this->DbQuery("UPDATE player SET player_score=1 WHERE player_id=$winner_id");
    }

    public function solo_playCard($card_id): void
    {
        $propuhCard = new CardManager($card_id, $this);
        $location_id = $this->solo_defineLocation($propuhCard);
        $propuhCard->play($location_id, 1);
    }

    public function solo_defineLocation(CardManager $propuhCard): int
    {
        $grannyCard_id = $this->globals->get(ATTACK_CARD);

        if (!$grannyCard_id) {
            return $propuhCard->suit_id;
        }

        $grannyCard = new CardManager($grannyCard_id, $this);
        $grannyCardLocation_id = (int) $grannyCard->location();

        if ($propuhCard->weight($grannyCardLocation_id) > $grannyCard->weight()) {
            return $grannyCardLocation_id;
        }

        if ($grannyCardLocation_id !== $propuhCard->suit_id) {
            return $propuhCard->suit_id;
        }

        $location_id = $grannyCardLocation_id + 1;
        if ($location_id > 3) {
            $location_id = 1;
        }

        return $location_id;
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws BgaUserException
     */
    public function actMoveGranny(#[IntParam(min: 1, max: 3)] int $location_id): void
    {
        $player_id = (int) $this->getActivePlayerId();

        $this->globals->set(GRANNY_LOCATION, $location_id);
        $this->globals->set(MOVED_GRANNY, true);

        $this->notify->all(
            "moveGranny",
            "",
            [
                "player_id" => $player_id,
                "location_id" => $location_id,
            ]
        );

        $this->gamestate->nextState("playerTurn");
    }

    public function actSkipGrannyMove(): void
    {
        $this->gamestate->nextState("skip");
    }

    public function actUndoSkipGrannyMove(): void
    {
        if ($this->globals->get(PLAY_COUNT) > 0) {
            throw new \BgaVisibleSystemException("You may no longer move the Granny");
        }

        $this->gamestate->nextState("undo");
    }

    public function actPlayCard(#[IntParam(min: 1, max: 28)] int $card_id, #[IntParam(min: 1, max: 3)] int $location_id): void
    {
        $player_id = (int) $this->getActivePlayerId();
        $card = new CardManager($card_id, $this);
        $card->play($location_id, $player_id);
        $this->gamestate->nextState("nextPlayer");
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `playerTurn` game state.
     *
     * @return array
     * @see ./states.inc.php
     */

    public function arg_playerTurn(): array
    {
        // Get some values from the current game situation from the database.

        return [
            "canUndo" => $this->globals->get(PLAY_COUNT) === 0,
        ];
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        $progression = (int) $this->cards->countCardsInLocation("discard") / 28 * 100;
        return round($progression);
    }

    /**
     * Game state action, example content.
     *
     * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */

    public function arg_grannyMove(): array
    {
        return [
            "grannyLocation" => $this->globals->get(GRANNY_LOCATION, 2),
        ];
    }

    // public function st_grannyMove(): void
    // {
    //     $args = $this->arg_grannyMove();
    //     if ($args["no_notify"]) {
    //         $this->gamestate->nextState("playerTurn");
    //         return;
    //     }
    // }

    public function st_betweenPlayers(): void
    {
        $player_id = (int)$this->getActivePlayerId();

        $this->giveExtraTime($player_id);
        $this->activeNextPlayer();

        if ($this->globals->get(RESOLVE_TRICK)) {
            $this->gamestate->nextState("nextTrick");
            return;
        }

        if ($this->globals->get(ATTACK_CARD)) {
            $this->globals->set(RESOLVE_TRICK, true);
        }

        if ($this->isSolo()) {
            $this->gamestate->nextState("soloTurn");
            return;
        }

        $this->gamestate->nextState("nextPlayer");
    }

    public function st_resolveTrick(): void
    {
        $card_id = $this->globals->get(ATTACK_CARD);
        $card = new CardManager($card_id, $this);
        $card->resolve();

        if (
            $this->globals->get(PLAY_COUNT) === 4 ||
            ($this->isSolo() && (int) $this->cards->countCardsInLocation("deck") === 0)
        ) {
            $card_id = (int) $this->getUniqueValueFromDB("SELECT card_id FROM card WHERE card_location IN (1, 2, 3)");

            if ($card_id) {
                $card = new CardManager($card_id, $this);
                $card->resolve(true);
            }

            $this->gamestate->nextState("nextRound");
            return;
        }

        if ($this->isSolo() && $this->globals->get(PLAY_COUNT) % 2 !== 0) {
            $this->gamestate->nextState("soloTurn");
            return;
        }

        $this->gamestate->nextState("nextTrick");
    }

    public function st_betweenRounds(): void
    {
        $this->globals->set(ATTACK_CARD, null);
        $this->globals->set(RESOLVE_TRICK, false);
        $this->globals->set(PLAY_COUNT, 0);
        $this->globals->set(MOVED_GRANNY, false);

        $this->discardToken();

        if ($this->checkGoals()) {
            $this->gamestate->nextState("gameEnd");
            return;
        };

        $this->drawCards();
        $this->gamestate->nextState("nextRound");
    }

    public function st_soloTurn(): void
    {
        $card_id = (int) $this->cards->getCardOnTop("deck")["id"];
        $this->solo_playCard($card_id, 1);
        $this->gamestate->nextState("realPlayer");
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
        //       if ($from_version <= 1404301345)
        //       {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
        //            $this->applyDbUpgradeToAllDB( $sql );
        //       }
        //
        //       if ($from_version <= 1405061421)
        //       {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            $this->applyDbUpgradeToAllDB( $sql );
        //       }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas(): array
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT player_id id, player_score score, player_role role FROM player"
        );
        $result["player_role"] = $this->getPlayerRole($current_player_id);
        $result["hand"] = $this->getHand($current_player_id, false);
        $result["playedCards"] = $this->getPlayedCards(null);
        $result["placedTokens"] = $this->getPlacedTokens(null);
        $result["deckCount"] = $this->cards->countCardsInLocation("deck");
        $result["grannyLocation"] = $this->globals->get(GRANNY_LOCATION);
        $result["completedGoals"] = $this->globals->get(COMPLETED_GOALS);

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName(): string
    {
        return "propuh";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = []): void
    {
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        if (count($players) > 1) {
            shuffle($default_colors);
        }

        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $player_role = $color === "ff0000" ? PROPUH : GRANNY;

            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                $color,
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
                $player_role,
            ]);
        }

        $this->DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar, player_role) VALUES %s",
                implode(",", $query_values)
            )
        );

        $cards = [];
        foreach ($this->CARDS as $card_id => $card) {
            $cards[] = ["type_arg" => $card_id, "type" => $card["suit"], "nbr" => $card["count"]];
        }
        $this->cards->createCards($cards, "deck");
        $this->cards->shuffle("deck");

        if ($this->isSolo()) {
            $this->cards->pickCardsForLocation(2, "deck", "discard");
        }

        foreach ($players as $player_id => $player) {
            $initialHandCount = $this->isSolo() ? 3 : 4;
            $this->cards->pickCards($initialHandCount, "deck", $player_id);

            $player_role = $this->getPlayerRole($player_id);
            $this->tokens->createCards(
                [["type_arg" => $player_id, "type" => $player_role, "nbr" => 7]],
                "hand",
                $player_id
            );

            if (!$this->isSolo()) {
                $this->initStat("player", "{$player_role}Win%", 0, $player_id);
            }

            $this->initStat("player", "successfulCounterplays", 0, $player_id);
            $this->initStat("player", "tokensPlaced", 0, $player_id);
        }

        $completedGoals = [];
        foreach ($this->ROLES as $role => $role_info) {
            $completedGoals[$role] = [1 => false, 3 => false];

            if ($role === GRANNY) {
                $completedGoals[$role][2] = false;
                $completedGoals[$role][4] = true;
            }
        }

        $granny_id = $this->grannyId();
        $this->initStat("player", "tokensRemoved", 0, $granny_id);

        if ($this->isSolo()) {
            $this->tokens->createCards(
                [["type_arg" => 1, "type" => PROPUH, "nbr" => 7]],
                "hand",
                1,
            );

            $difficulty = $this->solo_difficulty();

            if ($difficulty === 2) {
                $this->DbQuery("UPDATE token SET card_location=3 WHERE card_location='hand' AND card_location_arg=1 LIMIT 1");
            }

            if ($difficulty === 3) {
                $this->DbQuery("UPDATE token SET card_location=1 WHERE card_location='hand' AND card_location_arg=1 LIMIT 1");
                $this->DbQuery("UPDATE token SET card_location=2 WHERE card_location='hand' AND card_location_arg=1 LIMIT 1");
                $this->DbQuery("UPDATE token SET card_location=3 WHERE card_location='hand' AND card_location_arg=1 LIMIT 1");
            }

            if ($difficulty === 4) {
                $randomDifficulty = $this->solo_randomDifficulty();
                $randomCards = $this->RANDOM_DIFFICULTY;
                shuffle($randomCards);
                $randomCards = array_slice($randomCards, 0, $randomDifficulty);

                foreach ($randomCards as $randomCard) {
                    $location_id = (int) $randomCard["location_id"];
                    $tokenCount = (int) $randomCard["tokenCount"];

                    $this->DbQuery("UPDATE token SET card_location=$location_id WHERE card_location='hand' AND card_location_arg=1 LIMIT $tokenCount");
                }

                $this->initStat("player", "randomWin%-{$randomDifficulty}", 0, $player_id);
            }

            $this->initStat("player", "soloWin%", 0, $player_id);
            $this->initStat("player", "soloWin%-{$difficulty}", 0, $player_id);
        }

        $this->globals->set(COMPLETED_GOALS, $completedGoals);
        $this->globals->set(PLAY_COUNT, 0);
        $this->globals->set(GRANNY_LOCATION, 2);
        $this->checkGoals(false);

        $this->reloadPlayersBasicInfos();
        $this->gamestate->changeActivePlayer($granny_id);
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];
        $this->gamestate->jumpToState(99);
        // throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }

    public function debug_goals(): void
    {
        $this->checkGoals();
    }
}
