<?php

namespace Bga\Games\Propuh;

class TokenManager
{
    private array $LOCATIONS;
    private array $ROLES;

    protected $card;
    protected $role;
    protected $role_label;
    protected $player_id;

    public function __construct(int $card_id, \Table $game)
    {
        require "material.inc.php";

        $this->game = $game;
        $this->card_id = $card_id;

        $card = $this->game->tokens->getCard($card_id);
        $this->card = $card;
        $this->role = $card["type"];
        $this->role_label = $this->ROLES[$this->role]["label"];
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

        $location = $this->LOCATIONS[$location_id];
        $location_name = $location["name"];
        $location_label = $location["label"];
        $this->game->tokens->moveCard($this->card_id, $location_name, $player_id);

        $this->game->notify->all(
            "placeToken",
            clienttranslate('${player_name} places a ${role_label} token in the ${location_label}'),
            [
                "player_id" => $player_id,
                "token" => $this->getCard(),
                "role_label" => $this->role_label,
                "location_label" => $location_label,
                "i18n" => ["role_label", "location_label"],
            ]
        );
    }
}
