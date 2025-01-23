<?php

namespace Bga\Games\Propuh;

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
        $this->card = $card;

        $trick_id = (int) $card["type_arg"];
        $trick = $this->CARDS[$trick_id];

        $this->id = $trick_id;
        $this->value = (int) $trick["value"];
        $this->suit_id = (int) $trick["suit"];
        $this->suit_label = $this->LOCATIONS[$this->suit_id]["label"];
    }

    public function getCard(): array
    {
        return $this->game->cards->getCard($this->card_id);
    }

    public function validateLocation(int $player_id): void
    {
        $card = $this->getCard();

        if ($card["location"] !== "hand" || (int) $card["location_arg"] !== $player_id) {
            throw new \BgaVisibleSystemException("This card is not in your hand");
        }
    }

    public function play(int $location_id, int $player_id): void
    {
        $this->validateLocation($player_id);

        $location = $this->LOCATIONS[$location_id];
        $location_name = $location["name"];
        $location_label = $location["label"];
        $this->game->cards->moveCard($this->card_id, $location_name, $player_id);

        $this->game->notify->all(
            "playCard",
            clienttranslate('${player_name} plays a ${value} of ${suit_label} in the ${location_label}'),
            [
                "player_id" => $player_id,
                "card" => $this->getCard(),
                "value" => $this->value,
                "suit_label" => $this->suit_label,
                "location_label" => $location_label,
                "i18n" => ["suit_label", "location_label"],
            ]
        );
    }
}
