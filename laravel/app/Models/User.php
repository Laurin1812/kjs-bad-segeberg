<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'roles', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * "roles"/"permissions" (Netlify Identity -> Laravel Fortify, siehe
     * Migration 2026_09_21_000001_add_roles_permissions_to_users_table.php
     * und App\Support\AdminIdentity) sind JSON-Spalten mit einer Liste von
     * Strings - Laravel liefert sie dadurch bereits als PHP-Array (null,
     * wenn noch keine Rechte vergeben wurden).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'roles' => 'array',
            'permissions' => 'array',
            'last_login_at' => 'datetime',
        ];
    }
}
