<?php

namespace Src\Auth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role;

class RoleEloquentModel extends Role
{
    use HasUuids;
}
