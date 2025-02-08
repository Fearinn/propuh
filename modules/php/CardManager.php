<?php

namespace Bga\Games\Propuh;

use const Bga\Games\propuh\ATTACK_CARD;
use const Bga\Games\propuh\GRANNY_LOCATION;
use const Bga\Games\propuh\MOVED_GRANNY;
use const Bga\Games\propuh\PLAY_COUNT;

class CardManager
{
    private array $CARDS;
    private array $LOCATIONS;

    public function __construct(int $card_id, \Table $game)
    {
        require "material.inc.php";

        $this->game = $game;
        $this->card_id = $card_id;

        $card = $this->game->cards->getCard($card_id);
        $this->player_id = (int) $card["location_arg"];

        $trick_id = (int) $card["type_arg"];
        $trick = $this->CARDS[$trick_id];

        $this->id = $trick_id;
        $this->value = (int) $trick["value"];
        $this->suit_id = (int) $trick["suit"];
        $this->suit_label = $this->LOCATIONS[$this->suit_id]["label"];
        $this->location_id = (int) $card["location"];
    }

    public function getCard(): array
    {
        return $this->game->cards->getCard($this->card_id);
    }

    public function validateLocation(int $player_id): void
    {
        $card = $this->getCard();

        if ($player_id === 1) {
            if ($card["location"] !== "deck") {
                throw new \BgaVisibleSystemException("This card was not drawn from the deck");
            }

            return;
        } 

        if ($card["location"] !== "hand" || (int) $card["location_arg"] !== $player_id) {
            throw new \BgaVisibleSystemException("This card is not in your hand");
        }
    }

    public function play(int $location_id, int $player_id): void
    {
        $this->validateLocation($player_id);

        if ($this->game->globals->get(PLAY_COUNT) === 0 && $this->game->globals->get(MOVED_GRANNY, false)) {
            $grannyLocation = $this->game->globals->get(GRANNY_LOCATION);

            $this->game->notify->all(
                "message",
                clienttranslate('${player_name} (${role_label}) moves the Standee to the ${location_label}'),
                [
                    "player_id" => $player_id,
                    "location_id" => $grannyLocation,
                    "location_label" => $this->LOCATIONS[$grannyLocation]["label"],
                    "i18n" => ["role_label", "location_label"],
                ]
            );
        }

        $location = $this->LOCATIONS[$location_id];
        $location_label = $location["label"];
        $this->game->cards->moveCard($this->card_id, $location_id, $player_id);

        $this->game->notify->all(
            "playCard",
            clienttranslate('${player_name} (${role_label}) plays a ${value} of ${suit_label} in the ${location_label}'),
            [
                "player_id" => $player_id,
                "card" => $this->getCard(),
                "value" => $this->value,
                "suit_label" => $this->suit_label,
                "location_label" => $location_label,
                "i18n" => ["suit_label", "location_label", "role_label"],
            ]
        );

        if (!$this->game->globals->get(ATTACK_CARD)) {
            $this->game->globals->set(ATTACK_CARD, $this->card_id);
        }

        $this->game->globals->inc(PLAY_COUNT, 1);
    }

    public function weight(): int
    {
        $weight = $this->value;

        $location_id = (int) $this->getCard()["location"];

        if ($this->suit_id === $location_id) {
            $weight += 10;
        }
        return $weight;
    }

    public function location(): string | int
    {
        return $this->getCard()["location"];
    }

    public function resolve($lastCard = false): void
    {
        $location_id = (int) $this->location();
        $this->discard();

        if ($lastCard) {
            $this->game->placeToken($location_id, $this->player_id);
            return;
        }

        $counterCard_id = (int) $this->game->getUniqueValueFromDb("SELECT card_id FROM card WHERE card_location IN (1, 2, 3) AND card_id<>'$this->card_id'");

        if ($counterCard_id) {
            $counterCard = new CardManager($counterCard_id, $this->game);

            if ((int) $counterCard->location() === $location_id) {
                if ($this->weight() >= $counterCard->weight()) {
                    $this->game->placeToken($location_id, $this->player_id);
                    $this->game->globals->set(ATTACK_CARD, $counterCard_id);
                } else {
                    $this->game->custom_incStat(1, "successfulCounterplays", $counterCard->player_id);
                    
                    $this->game->notify->all(
                        "successfulCounterplay",
                        clienttranslate('${player_name} (${role_label}) successfully counters ${player_name2}'),
                        [
                            "player_id" => $counterCard->player_id,
                            "player_id2" => $this->player_id,
                            "player_name2" => $this->game->getPlayerNameById($this->player_id),
                            "i18n" => ["role_label"],
                        ]
                    );

                    $counterCard->discard();
                    $this->game->globals->set(ATTACK_CARD, null);
                }

                return;
            }
        }

        $this->game->placeToken($location_id, $this->player_id);
        $this->game->globals->set(ATTACK_CARD, $counterCard_id);
    }

    public function discard(): void
    {
        $this->game->cards->moveCard($this->card_id, "discard");

        $this->game->notify->all(
            "discardCard",
            "",
            [
                "card" => $this->getCard(),
            ]
        );
    }
}
