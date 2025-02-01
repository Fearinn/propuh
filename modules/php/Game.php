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
const COMPLETED_GOALS = "completedGoals";

class Game extends \Table
{
    private array $CARDS;
    private array $ROLES;
    private array $LOCATIONS;
    public string $deckFields = "card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg";

    public function __construct()
    {
        parent::__construct();

        require "material.inc.php";

        $this->initGameStateLabels([]);

        $this->cards = $this->getNew("module.common.deck");
        $this->cards->init("card");

        $this->tokens = $this->getNew("module.common.deck");
        $this->tokens->init("token");

        $this->notify->addDecorator(fn(string $message, array $args) => $this->decoratePlayerNameNotifArg($message, $args));
    }


    /*  UTILITY FUNCTIONS */

    public function decoratePlayerNameNotifArg(string $message, array $args): array
    {
        if (isset($args["player_id"])) {
            $player_id = (int) $args["player_id"];
            if (!isset($args["player_name"]) && str_contains($message, '${player_name}')) {
                $args["player_name"] = $this->getPlayerNameById($player_id);
            }

            if (!isset($args["role_label"]) && str_contains($message, '${role_label}')) {
                $player_role = $this->playerRole($player_id);
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

    public function playerRole(int $player_id): string
    {
        return $this->getUniqueValueFromDB("SELECT player_role FROM player WHERE player_id=$player_id");
    }

    public function grannyId(): int
    {
        return (int) $this->getUniqueValueFromDB("SELECT player_id FROM player WHERE player_role='granny'");
    }

    public function placeToken(int $location_id, int $player_id)
    {
        $tokenCard_id = (int) $this->getUniqueValueFromDB("SELECT card_id FROM token WHERE card_location='hand' AND card_location_arg=$player_id LIMIT 1");

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
        foreach ($players as $player_id => $player) {
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

        $msg = !!$cards ? clienttranslate("New round: each player draws 2 cards from the deck") : clienttranslate("New round: no cards in the deck");

        $this->notify->all(
            "newRound",
            $msg,
            [
                "cards" => $drawnCards,
            ]
        );
    }

    public function checkGoals(): bool
    {
        $players = $this->loadPlayersBasicInfos();
        $completedGoals = $this->globals->get(COMPLETED_GOALS);

        $winner_id = null;
        foreach ($players as $player_id => $player) {
            $player_role = $this->playerRole($player_id);
            $goals = $this->ROLES[$player_role]["goals"];
            $goalsMet = true;

            foreach ($goals as $location_id => $goal) {
                $tokenGoal = $goal["tokens"];
                $tokenCount = $this->countPlacedTokens($location_id, $player_role);

                if ($tokenCount < $tokenGoal) {
                    $goalsMet = false;
                    $this->notify->all(
                        "incompleteGoal",
                        "",
                        [
                            "player_id" => $player_id,
                            "location_id" => $location_id
                        ]
                    );
                    continue;
                }

                if (!$completedGoals[$player_role][$location_id]) {
                    $this->notify->all(
                        "completeGoal",
                        clienttranslate('${player_name} (${role_label}) ${goal_label}'),
                        [
                            "player_id" => $player_id,
                            "location_id" => $location_id,
                            "goal_label" => $goal["label"],
                            "i18n" => ["role_label", "goal_label"],
                        ]
                    );

                    $completedGoals[$player_role][$location_id] = true;
                    $this->globals->set(COMPLETED_GOALS, $completedGoals);
                }
            }

            if ($player_role === GRANNY && $this->countPlacedTokens(2, PROPUH) > 0) {
                $goalsMet = false;
            }

            if ($goalsMet) {
                $winner_id = $player_id;

                if ($player_role === PROPUH) {
                    break;
                }
            }
        }

        if ($winner_id) {
            $this->DbQuery("UPDATE player SET player_score=1 WHERE player_id=$winner_id");
            return true;
        }

        return false;
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

        $this->notify->all(
            "moveGranny",
            clienttranslate('${player_name} (${role_label}) moves the standee to the ${location_label}'),
            [
                "player_id" => $player_id,
                "location_id" => $location_id,
                "location_label" => $this->LOCATIONS[$location_id]["label"],
                "i18n" => ["role_label", "location_label"],
            ]
        );

        $this->gamestate->nextState("playerTurn");
    }

    public function actSkipGrannyMove(): void
    {
        $this->gamestate->nextState("skip");
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
            "grannyLocation" => $this->globals->get(GRANNY_LOCATION, 2),
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
        // TODO: compute and return the game progression

        return 0;
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

        $this->gamestate->nextState("nextPlayer");
    }

    public function st_resolveTrick(): void
    {
        $card_id = $this->globals->get(ATTACK_CARD);
        $card = new CardManager($card_id, $this);
        $card->resolve();

        if ($this->globals->get(PLAY_COUNT) === 4) {
            $card_id = (int) $this->getUniqueValueFromDB("SELECT card_id FROM card WHERE card_location IN (1, 2, 3)");

            if ($card_id) {
                $card = new CardManager($card_id, $this);
                $card->resolve(true);
            }

            $this->gamestate->nextState("nextRound");
            return;
        }

        $this->gamestate->nextState("nextTrick");
    }

    public function st_betweenRounds(): void
    {
        $this->globals->set(ATTACK_CARD, null);
        $this->globals->set(RESOLVE_TRICK, false);
        $this->globals->set(PLAY_COUNT, 0);

        $this->discardToken();

        if ($this->checkGoals()) {
            $this->gamestate->nextState("gameEnd");
            return;
        };

        $this->drawCards();
        $this->gamestate->nextState("nextRound");
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
        $result["hand"] = $this->getHand($current_player_id, false);
        $result["playedCards"] = $this->getPlayedCards(null);
        $result["placedTokens"] = $this->getPlacedTokens(null);
        $result["deckCount"] = $this->cards->countCardsInLocation("deck");
        $result["grannyLocation"] = $this->globals->get(GRANNY_LOCATION);

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
        shuffle($default_colors);

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

        $completedGoals = [];
        foreach ($players as $player_id => $player) {
            $this->cards->pickCards(4, "deck", $player_id);

            $player_role = $this->playerRole($player_id);
            $this->tokens->createCards(
                [["type_arg" => $player_id, "type" => $player_role, "nbr" => 7]],
                "hand",
                $player_id
            );

            $completedGoals[$player_role] = [1 => false, 2 => false, 3 => false];
        }

        $this->globals->set(COMPLETED_GOALS, $completedGoals);
        $this->globals->set(PLAY_COUNT, 0);
        $this->globals->set(GRANNY_LOCATION, 2);

        $this->reloadPlayersBasicInfos();

        $granny = $this->getUniqueValueFromDB("SELECT player_id FROM player WHERE player_role='granny'");
        $this->gamestate->changeActivePlayer($granny);
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

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default: {
                        $this->gamestate->nextState("zombiePass");
                        break;
                    }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }

    public function debug_goals(): void
    {
        $this->checkGoals();
    }
}
