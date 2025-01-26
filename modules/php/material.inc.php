<?php

$this->LOCATIONS = [
    1 => [
        "name" => "bed",
        "label" => _("bed"),
        "limits" => [
            "granny" => 2,
            "propuh" => 3,
        ]
    ],
    2 => [
        "name" => "stove",
        "label" => _("stove"),
        "limits" => [
            "granny" => 3,
            "propuh" => 1,
        ]
    ],
    3 => [
        "name" => "table",
        "label" => _("table"),
        "limits" => [
            "granny" => 2,
            "propuh" => 3,
        ]
    ],
];

$this->CARDS = [
    1 => [
        "suit" => 1,
        "count" => 1,
        "value" => 1,
    ],
    2 => [
        "suit" => 1,
        "count" => 4,
        "value" => 2,
    ],
    3 => [
        "suit" => 1,
        "count" => 4,
        "value" => 3,
    ],
    4 => [
        "suit" => 1,
        "count" => 2,
        "value" => 4,
    ],
    5 => [
        "suit" => 2,
        "count" => 1,
        "value" => 1,
    ],
    6 => [
        "suit" => 2,
        "count" => 2,
        "value" => 2,
    ],
    7 => [
        "suit" => 2,
        "count" => 2,
        "value" => 3,
    ],
    8 => [
        "suit" => 2,
        "count" => 1,
        "value" => 4,
    ],
    9 => [
        "suit" => 3,
        "count" => 1,
        "value" => 1,
    ],
    10 => [
        "suit" => 3,
        "count" => 4,
        "value" => 2,
    ],
    11 => [
        "suit" => 3,
        "count" => 4,
        "value" => 3,
    ],
    12 => [
        "suit" => 3,
        "count" => 2,
        "value" => 4,
    ],
];

$this->ROLES = [
    "propuh" => [
        "label" => "Propuh",
        "goals" => [],
    ],
    "granny" => [
        "label" => clienttranslate("Granny"),
        "goals" => [

        ],
    ]
];
