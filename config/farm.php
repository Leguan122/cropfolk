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
    'width' => 1152,
    'height' => 768,
    'reach' => 70,
    'barnDoor' => ['x' => 869, 'y' => 695, 'reach' => 43],
    'shopDoor' => ['x' => 265, 'y' => 689, 'reach' => 48],
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
        ['kind' => 'yard', 'x' => 360, 'y' => 494, 'w' => 675, 'h' => 206, 'text' => 'Spoločný dvor'],
    ],
    'obstacles' => [
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
        ['kind' => 'barn', 'x' => 770, 'y' => 520, 'w' => 198, 'h' => 140, 'text' => 'Stodola'],
    ],
];
