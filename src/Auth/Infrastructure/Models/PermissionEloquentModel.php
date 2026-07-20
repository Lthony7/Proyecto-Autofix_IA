<?php

namespace Src\Auth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission;

class PermissionEloquentModel extends Permission
{
    use HasUuids;
}
