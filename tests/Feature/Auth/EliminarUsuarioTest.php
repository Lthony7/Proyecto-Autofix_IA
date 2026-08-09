<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Auth\Infrastructure\Models\PermissionEloquentModel;
use Src\Auth\Infrastructure\Models\RoleEloquentModel;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Tests\TestCase;

final class EliminarUsuarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere PDO SQLite para la prueba de integración.');
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->string('model_type');
            $table->uuid('model_uuid');
            $table->index(['model_uuid', 'model_type']);
            $table->primary(['permission_id', 'model_uuid', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->string('model_type');
            $table->uuid('model_uuid');
            $table->index(['model_uuid', 'model_type']);
            $table->primary(['role_id', 'model_uuid', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->uuid('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('auditorias', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id')->nullable();
            $table->string('accion', 80)->index();
            $table->string('recurso_tipo', 120)->index();
            $table->uuid('recurso_id')->nullable()->index();
            $table->json('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    private function crearAdminConPermiso(string $permiso): UserEloquentModel
    {
        $permission = PermissionEloquentModel::findOrCreate($permiso, 'web');
        $rol = RoleEloquentModel::findOrCreate('Administrador', 'web');
        $rol->givePermissionTo($permission);

        $admin = UserEloquentModel::create([
            'name' => 'Administrador Pruebas',
            'email' => 'admin.pruebas@example.com',
            'password' => 'Admin2026!',
        ]);
        $admin->assignRole($rol);

        return $admin;
    }

    public function test_eliminar_usuario_no_lo_borra_y_lo_deja_inactivo(): void
    {
        $admin = $this->crearAdminConPermiso('usuarios.eliminar');
        $objetivo = UserEloquentModel::create([
            'name' => 'Usuario Objetivo',
            'email' => 'objetivo@example.com',
            'password' => 'Objetivo2026!',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('usuarios.eliminar', $objetivo->id));
        $response->assertRedirect();

        $objetivo->refresh();
        $this->assertFalse($objetivo->activo);
        $this->assertDatabaseHas('users', ['id' => $objetivo->id, 'activo' => false]);

        $this->assertDatabaseHas('auditorias', ['recurso_tipo' => 'usuario', 'recurso_id' => $objetivo->id, 'accion' => 'usuario.eliminado']);
    }

    public function test_sin_permiso_no_puede_eliminar_usuarios(): void
    {
        $usuarioSinPermiso = UserEloquentModel::create([
            'name' => 'Sin Permiso',
            'email' => 'sinpermiso@example.com',
            'password' => 'SinPermiso2026!',
        ]);
        $objetivo = UserEloquentModel::create([
            'name' => 'Objetivo Dos',
            'email' => 'objetivo2@example.com',
            'password' => 'Objetivo2026!',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuarioSinPermiso)->delete(route('usuarios.eliminar', $objetivo->id));
        $response->assertForbidden();

        $objetivo->refresh();
        $this->assertTrue($objetivo->activo);
    }

    public function test_unusuario_no_puede_eliminar_su_propia_cuenta(): void
    {
        $admin = $this->crearAdminConPermiso('usuarios.eliminar');

        $this->actingAs($admin)->delete(route('usuarios.eliminar', $admin->id))->assertSessionHasErrors('usuario');
        $admin->refresh();
        $this->assertTrue($admin->activo);
    }
}