<?php

namespace App\Models;

use App\Enums\Rol;
use App\Models\Concerns\IslemKaydeder;
use App\Support\Yetki;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * Panel kullanıcısı. Aynı kayıt, `ekipte_goster` açıksa sitede danışman /
 * ekip kartı olarak görünür — yalnız ad, görev, telefon, foto ve biyografi.
 *
 * FilamentUser BİLEREK uygulanıyor: bu arayüz olmadan Filament paneli
 * production ortamında kimseye açılmaz (Zeys'te testte yakalandı).
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, IslemKaydeder, Notifiable;

    /**
     * İki aşamalı doğrulamanın ZORUNLU olduğu roller (şartname 15: yönetici
     * hesapları). Mali kayıt ve kullanıcı yönetimi yapan roller dahil; emlak
     * danışmanı, içerik editörü ve saha personelinde isteğe bağlı.
     * Zorlama: App\Http\Middleware\IkiAsamaliZorunlu (config balkan.mfa_zorunlu).
     */
    public const MFA_ZORUNLU_ROLLER = ['sistem_yoneticisi', 'sirket_yoneticisi', 'site_yoneticisi', 'muhasebe_sorumlusu'];

    public function ikiAsamaliZorunlu(): bool
    {
        $rol = $this->rol instanceof Rol ? $this->rol->value : (string) $this->rol;

        return in_array($rol, self::MFA_ZORUNLU_ROLLER, true);
    }

    public function ikiAsamaliEtkin(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }

    protected $fillable = [
        'name', 'email', 'password', 'rol', 'aktif', 'gorev', 'telefon', 'whatsapp',
        'foto', 'biyografi', 'ekipte_goster', 'ekip_sirasi',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'aktif' => 'boolean',
            'ekipte_goster' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return Yetki::var('panel', $this);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function yetkili(string $izin): bool
    {
        return Yetki::var($izin, $this);
    }

    /** Kullanıcının atandığı yönetilen siteler (site_kullanici). */
    public function siteler(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_kullanici');
    }

    public function portfoyler(): HasMany
    {
        return $this->hasMany(Portfoy::class, 'danisman_id');
    }

    /** @param Builder<User> $sorgu */
    public function scopeEkip(Builder $sorgu): void
    {
        $sorgu->where('aktif', true)->where('ekipte_goster', true)->orderBy('ekip_sirasi')->orderBy('name');
    }

    /** @param Builder<User> $sorgu */
    public function scopeDanismanlar(Builder $sorgu): void
    {
        $sorgu->where('aktif', true)->whereIn('rol', [
            Rol::EmlakDanismani->value, Rol::SirketYoneticisi->value, Rol::SistemYoneticisi->value,
        ])->orderBy('name');
    }

    public function fotoUrl(): ?string
    {
        return $this->foto ? Storage::disk('medya')->url($this->foto) : null;
    }

    public function basHarfler(): string
    {
        $parcalar = preg_split('/\s+/u', trim($this->name)) ?: [];
        $ilk = mb_substr($parcalar[0] ?? '', 0, 1);
        $son = count($parcalar) > 1 ? mb_substr(end($parcalar), 0, 1) : '';

        return mb_strtoupper($ilk.$son, 'UTF-8');
    }
}
