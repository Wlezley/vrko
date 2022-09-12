<?php

declare(strict_types=1);

namespace App\Model;

class KatalogAttributes
{
    private static array $attributes = [
        "players" => [
            -2 => [
                "icon" => "/img/icon/pocethracu3.png",
                "desc" => "MMO",
            ],
            -1 => [
                "icon" => "/img/icon/pocethracu3.png",
                "desc" => "PvP Multiplayer",
            ],
            0 => [
                "icon" => "/img/icon/pocethracu3.png",
                "desc" => "Počet hráčů neznámý",
            ],
            1 => [
                "icon" => "/img/icon/pocethracu1.png",
                "desc" => "Pro jednoho hráče",
            ],
            2 => [
                "icon" => "/img/icon/pocethracu2.png",
                "desc" => "Pro 1 nebo 2 hráče",
            ],
            3 => [
                "icon" => "/img/icon/pocethracu3.png",
                "desc" => "Až pro 3 hráče",
            ],
            4 => [
                "icon" => "/img/icon/pocethracu3.png",
                "desc" => "Až pro 4 hráče",
            ],
            5 => [
                "icon" => "/img/icon/pocethracu3.png",
                "desc" => "Až pro %d hráčů",
            ],
        ],

        "skills" => [
            0 => [
                "icon" => "/img/icon/narocnost3.png",
                "desc" => "Zkušenost neznámá",
            ],
            1 => [
                "icon" => "/img/icon/narocnost1.png",
                "desc" => "Pro všechny hráče",
            ],
            2 => [
                "icon" => "/img/icon/narocnost2.png",
                "desc" => "Pro mírně pokročilé",
            ],
            3 => [
                "icon" => "/img/icon/narocnost3.png",
                "desc" => "Pro zkušené hráče",
            ],
        ],

        "difficulty" => [
            0 => [
                "icon" => "/img/icon/fyzicka3.png",
                "desc" => "Náročnost neznámá",
            ],
            1 => [
                "icon" => "/img/icon/fyzicka1.png",
                "desc" => "Fyzicky nenáročné",
            ],
            2 => [
                "icon" => "/img/icon/fyzicka2.png",
                "desc" => "Fyzicky středně náročné",
            ],
            3 => [
                "icon" => "/img/icon/fyzicka3.png",
                "desc" => "Fyzicky velmi náročné",
            ],
        ],
    ];

    // ITEMS
    public function getPlayers(int $id): array
    {
        if ($id < -2 || $id > 4) {
            return [
                "icon" => self::$attributes['players'][5]['icon'],
                "desc" => sprintf(self::$attributes['players'][5]['desc'], $id)
            ];
        }

        return self::$attributes['players'][$id];
    }

    public function getSkills(int $id): array
    {
        return self::$attributes['skills'][$id];
    }

    public function getDifficulty(int $id): array
    {
        return self::$attributes['difficulty'][$id];
    }

    // LISTS
    public function getPlayersList(): array
    {
        return self::$attributes['players'];
    }

    public function getSkillsList(): array
    {
        return self::$attributes['skills'];
    }

    public function getDifficultyList(): array
    {
        return self::$attributes['difficulty'];
    }

    public function getAttributes(): array
    {
        return self::$attributes;
    }
}