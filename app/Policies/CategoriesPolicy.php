<?php

namespace App\Policies;

use App\Models\User;
use App\Models\categories;
use Illuminate\Auth\Access\Response;

class CategoriesPolicy
{

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, categories $categories): bool
    {

    }

    public function create(User $user): bool
    {

    }

    public function update(User $user, categories $categories): bool
    {

    }

    public function delete(User $user, categories $categories): bool
    {

    }

    public function restore(User $user, categories $categories): bool
    {

    }

    public function forceDelete(User $user, categories $categories): bool
    {

    }
}
