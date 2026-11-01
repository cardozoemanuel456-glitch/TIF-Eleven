<?php

namespace App\Models;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Notifications\RestablecerPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Solo los datos de perfil son asignables en masa. El rol, el año de cursada,
     * el estado y el resto de los campos de control se asignan de forma explícita
     * (forceFill) para que un formulario nunca pueda modificarlos.
     */
    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estado' => EstadoUsuario::class,
            'anio_cursada' => AnioCursada::class,
            'debe_cambiar_password' => 'boolean',
            'notificar_email' => 'boolean',
            'aprobado_en' => 'datetime',
            'anonimizado_en' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- Relaciones

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'aprobado_por');
    }

    // ------------------------------------------------------------------- Roles

    public function tieneRol(Rol ...$roles): bool
    {
        $actual = $this->role?->nombre;

        return $actual !== null && in_array($actual, $roles, true);
    }

    public function esAlumno(): bool
    {
        return $this->tieneRol(Rol::Alumno);
    }

    public function esPersonal(): bool
    {
        return $this->tieneRol(...Rol::personal());
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoUsuario::Activo;
    }

    // ------------------------------------------------------------------ Sesiones

    /**
     * Cierra todas las sesiones abiertas del usuario. Requiere SESSION_DRIVER=database;
     * con otro driver, el middleware "activo" igualmente corta el acceso en la
     * siguiente petición.
     */
    public function cerrarSesiones(): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $this->id)
                ->delete();
        }
    }

    // ------------------------------------------------------------- Notificaciones

    /** Reemplaza el correo en inglés de Laravel por el de Eleven. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerPasswordNotification($token));
    }

    // --------------------------------------------------------------------- Otros

    public function nombreCompleto(): string
    {
        return trim("{$this->apellido}, {$this->nombre}", ' ,');
    }

    public function scopeAlumnos(Builder $query): Builder
    {
        return $query->whereHas('role', fn (Builder $q) => $q->where('nombre', Rol::Alumno->value));
    }

    public function scopePersonal(Builder $query): Builder
    {
        return $query->whereHas('role', fn (Builder $q) => $q->whereIn('nombre', Rol::valoresDelPersonal()));
    }
}