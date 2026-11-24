<?php

$plots = [];
foreach ([164, 724] as $left) {
    foreach ([215, 350] as $y) {
        for ($column = 0; $column < 4; $column++) {
            $plots[] = ['x' => $left + 88 * $column, 'y' => $y];
        }
    }
}

return [
    'testCoins' => (bool) env('CROPFOLK_TEST_COINS', false),
    'width' => 1152,
    'height' => 1120,
    'closedHeight' => 756,
    'shopExpansionCost' => 65,
    'eastFieldCost' => 45,
    'orders' => [
        ['name' => 'Reďkovky na trh', 'crops' => ['radish' => 4], 'reward' => 26],
        ['name' => 'Úroda pre trh', 'crops' => ['carrot' => 3, 'corn' => 2], 'reward' => 100],
        ['name' => 'Zeleninový košík', 'crops' => ['radish' => 4, 'carrot' => 4, 'corn' => 1], 'reward' => 110],
    ],
    'weather' => ['period' => 180000, 'rain' => 32000],
    'irrigation' => [
        'fields' => [
            ['name' => 'Západné pole', 'cost' => 60, 'upgradeCost' => 45, 'valve' => ['x' => 471, 'y' => 281, 'reach' => 31]],
            ['name' => 'Východné pole', 'cost' => 75, 'upgradeCost' => 55, 'valve' => ['x' => 684, 'y' => 281, 'reach' => 31]],
            ['name' => 'Južné pole', 'cost' => 90, 'upgradeCost' => 65, 'valve' => ['x' => 573, 'y' => 801, 'reach' => 31]],
        ],
    ],
    'well' => ['building' => 'well', 'x' => 487, 'y' => 680, 'reach' => 44],
    'fieldGrid' => ['columns' => 4, 'rows' => 2],
    'toolTiers' => [
        'hoe' => [1 => ['cost' => 0, 'rangeSquared' => 0, 'limit' => 1], 2 => ['cost' => 35, 'rangeSquared' => 1, 'limit' => 3], 3 => ['cost' => 80, 'rangeSquared' => 2, 'limit' => 5]],
        'can' => [1 => ['cost' => 0, 'rangeSquared' => 0, 'limit' => 1, 'capacity' => 6], 2 => ['cost' => 30, 'rangeSquared' => 1, 'limit' => 3, 'capacity' => 12], 3 => ['cost' => 75, 'rangeSquared' => 2, 'limit' => 5, 'capacity' => 20]],
    ],
    'southGate' => ['x' => 576, 'y' => 714, 'reach' => 46, 'cost' => 110],
    'south' => [
        'plots' => array_map(fn ($index) => [
            'x' => 390 + ($index % 4) * 88,
            'y' => 870 + intdiv($index, 4) * 135,
        ], range(0, 7)),
        'landmarks' => [
            ['kind' => 'path', 'x' => 531, 'y' => 690, 'w' => 90, 'h' => 120],
            ['kind' => 'path', 'x' => 934, 'y' => 675, 'w' => 85, 'h' => 155],
            ['kind' => 'path', 'x' => 537, 'y' => 785, 'w' => 462, 'h' => 54],
            ['kind' => 'south', 'x' => 334, 'y' => 801, 'w' => 378, 'h' => 280, 'text' => 'Južné pole'],
        ],
    ],
    'reach' => 70,
    'barnDoor' => ['building' => 'barn', 'x' => 869, 'y' => 695, 'reach' => 43],
    'shopDoor' => ['building' => 'shop', 'x' => 265, 'y' => 689, 'reach' => 48],
    'bagCapacity' => 8,
    'wheelbarrow' => ['cost' => 40, 'capacity' => 12],
    'radius' => 16,
    'spawn' => ['x' => 576, 'y' => 580],
    'plots' => $plots,
    'landmarks' => [
        ['kind' => 'west', 'x' => 107, 'y' => 146, 'w' => 389, 'h' => 281, 'text' => 'Západné pole'],
        ['kind' => 'east', 'x' => 656, 'y' => 146, 'w' => 389, 'h' => 281, 'text' => 'Východné pole'],
        ['kind' => 'path', 'x' => 531, 'y' => 78, 'w' => 90, 'h' => 455],
        ['kind' => 'path', 'x' => 470, 'y' => 248, 'w' => 209, 'h' => 76],
        ['kind' => 'path', 'x' => 265, 'y' => 665, 'w' => 110, 'h' => 44],
        ['kind' => 'yard', 'x' => 360, 'y' => 494, 'w' => 675, 'h' => 206],
    ],
    'obstacles' => [
        ['kind' => 'fence', 'x' => 0, 'y' => 756, 'w' => 522, 'h' => 16],
        ['kind' => 'fence', 'x' => 630, 'y' => 756, 'w' => 298, 'h' => 16],
        ['kind' => 'fence', 'x' => 1026, 'y' => 756, 'w' => 126, 'h' => 16],
        ['kind' => 'fence', 'x' => 97, 'y' => 125, 'w' => 407, 'h' => 14],
        ['kind' => 'fence', 'x' => 97, 'y' => 430, 'w' => 407, 'h' => 14],
        ['kind' => 'fence', 'x' => 97, 'y' => 138, 'w' => 14, 'h' => 293],
        ['kind' => 'fence', 'x' => 490, 'y' => 138, 'w' => 14, 'h' => 100],
        ['kind' => 'fence', 'x' => 490, 'y' => 331, 'w' => 14, 'h' => 100],
        ['kind' => 'fence', 'x' => 648, 'y' => 125, 'w' => 407, 'h' => 14],
        ['kind' => 'fence', 'x' => 648, 'y' => 430, 'w' => 407, 'h' => 14],
        ['kind' => 'fence', 'x' => 1041, 'y' => 138, 'w' => 14, 'h' => 293],
        ['kind' => 'fence', 'x' => 648, 'y' => 138, 'w' => 14, 'h' => 100],
        ['kind' => 'fence', 'x' => 648, 'y' => 331, 'w' => 14, 'h' => 100],
        ['kind' => 'shop', 'x' => 175, 'y' => 525, 'w' => 180, 'h' => 128, 'text' => 'Obchod so semienkami'],
        ['kind' => 'well', 'x' => 461, 'y' => 607, 'w' => 52, 'h' => 50],
        ['kind' => 'barn', 'x' => 770, 'y' => 520, 'w' => 198, 'h' => 140, 'text' => 'Stodola'],
    ],
];
