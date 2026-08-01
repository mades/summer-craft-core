<?php

namespace SummerCraft\Core\Tests\Fixture\Routing\Namespaced\Admin;

class UsersController
{
    public function listAction(): string
    {
        return 'admin-users-list';
    }
}
