<?php

namespace Bga\Games\Propuh;

class TokenManager
{
    private array $LOCATIONS;
    private array $ROLES;

    public function __construct(int $card_id, \Table $game)
    {
        require "material.inc.php";

        $this->game = $game;
        $this->card_id = $card_id;

        $card = $this->game->tokens->getCard($card_id);
        $this->card = (array) $card;
        $this->role = (string) $card["type"];
        $this->role_label = (string) $this->ROLES[$this->role]["label"];
        $this->player_id = (int) $card["type_arg"];
    }

    public function getCard(): array
    {
        return $this->game->tokens->getCard($this->card_id);
    }

    public function validateLocation(int $player_id): void
    {
        $card = $this->getCard();

        if ($card["location"] !== "hand" || (int) $card["location_arg"] !== $player_id) {
            throw new \BgaVisibleSystemException("Invalid token");
        }
    }

    public function place(int $location_id, int $player_id): void
    {
        $this->validateLocation($player_id);

        $location = (array) $this->LOCATIONS[$location_id];
        $location_label = (string) $location["label"];

        $tokenCount = (int) count($this->game->tokens->getCardsOfTypeInLocation($this->role, null, $location_id));
        $tokenLimit = (int) $location["limits"][$this->role];

        if ($tokenCount === $tokenLimit) {
            $this->game->notify->all(
                "message",
                clienttranslate('${player_name} (${role_label}) may not place more tokens in the ${location_label}'),
                [
                    "player_id" => $player_id,
                    "location_label" => $location_label,
                    "i18n" => ["role_label", "location_label"],
                ]
            );

            return;
        }

        $this->game->tokens->moveCard($this->card_id, $location_id, $this->player_id);

        $this->game->notify->all(
            "placeToken",
            clienttranslate('${player_name} (${role_label}) places a token in the ${location_label}'),
            [
                "player_id" => $player_id,
                "token" => $this->getCard(),
                "location_label" => $location_label,
                "i18n" => ["role_label", "location_label"],
            ]
        );
    }
}
