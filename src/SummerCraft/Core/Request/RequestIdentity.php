<?php

namespace SummerCraft\Core\Request;

class RequestIdentity
{
    private static int $lastId = 0;

    private string $id;

    private function __construct()
    {
        $this->id = (string)(++self::$lastId);
    }

    public static function createUnique(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return $this->id;
    }
}
