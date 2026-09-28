<?php

namespace App\Filament\Pages;

use App\Models\Ayar;
use App\Models\IslemKaydi;
use App\Support\Yetki;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Site ayarları (şartname 10). Boş bırakılan alan sitede hiç çizilmez;
 * doğrulanmamış işletme bilgisi (adres, çalışma saatleri, "7/24" gibi
 * ifadeler) onaylanana kadar girilmemelidir.
 */
class SiteAyarlari extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Site Ayarları';

    protected static ?string $title = 'Site Ayarları';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'site-ayarlari';

    /** Görsel ayarları: anahtar => etiket. */
    public const GORSELLER = [
        'logo' => 'Logo (açık zemin)',
        'logo_acik' => 'Logo (koyu zemin için açık renk)',
        'hero_gorsel' => 'Ana sayfa büyük görsel',
        'cta_gorsel' => 'Çağrı alanı görseli',
        'yonetim_gorsel' => 'Site yönetimi bölümü görseli',
    ];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Yetki::var('ayar.yonet');
    }

    public function mount(): void
    {
        $this->form->fill(Ayar::tumu());
    }

    public function form(Schema $schema): Schema
    {
        $gorselAlanlari = [];
        foreach (self::GORSELLER as $anahtar => $etiket) {
            $gorselAlanlari[] = FileUpload::make($anahtar)
                ->label($etiket)
                ->disk('medya')
                ->visibility('public')
                ->directory('site')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                ->maxSize(8192);
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Firma')
                    ->description('Boş bırakılan alan sitede hiç gösterilmez. Doğrulanmamış bilgi (adres, çalışma saatleri, "7/24" gibi ifadeler) onaylanana kadar girilmemelidir.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('firma_adi')->label('Firma adı')->required()->maxLength(120),
                        TextInput::make('firma_unvani')->label('Ticari unvan')->maxLength(255),
                        TextInput::make('alt_marka')->label('Alt marka')->maxLength(120),
                    ]),

                Section::make('İletişim')
                    ->columns(3)
                    ->schema([
                        TextInput::make('telefon')->label('Telefon')->tel()->maxLength(30),
                        TextInput::make('whatsapp')->label('WhatsApp')->tel()->maxLength(30),
                        TextInput::make('eposta')->label('E-posta')->email()->maxLength(255),
                        Textarea::make('adres')->label('Adres')->rows(2)->helperText('Yalnız doğrulanmış açık adres.')->columnSpan(2),
                        Textarea::make('calisma_saatleri')->label('Çalışma saatleri')->rows(2)->helperText('Onaylanmadan "7/24" yazmayın.'),
                        TextInput::make('acil_iletisim')->label('Acil iletişim (site yönetimi)')->maxLength(120),
                        TextInput::make('bildirim_eposta')->label('Form bildirimlerinin gideceği e-posta')->email()->maxLength(255),
                        Textarea::make('harita_embed')->label('Harita gömme adresi')->rows(2)->helperText('Google Haritalar "Yerleştir" bağlantısı (yalnız adres, iframe kodu değil).'),
                        Repeater::make('iletisim_kisileri')
                            ->label('İletişim kişileri')
                            ->schema([
                                TextInput::make('ad')->label('Ad soyad')->required()->maxLength(120),
                                TextInput::make('gorev')->label('Görev')->maxLength(120),
                                TextInput::make('telefon')->label('Telefon')->tel()->required()->maxLength(30),
                                Toggle::make('whatsapp')->label('WhatsApp ile ulaşılabilir')->inline(false),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Kişi ekle')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Sosyal medya')
                    ->columns(2)
                    ->schema([
                        TextInput::make('instagram')->label('Instagram adresi')->url()->maxLength(255),
                        TextInput::make('facebook')->label('Facebook adresi')->url()->maxLength(255),
                        TextInput::make('linkedin')->label('LinkedIn adresi')->url()->maxLength(255),
                        TextInput::make('youtube')->label('YouTube adresi')->url()->maxLength(255),
                    ]),

                Section::make('Ana sayfa metinleri ve SEO')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ana_baslik')->label('Ana başlık')->maxLength(160)->columnSpanFull(),
                        Textarea::make('ana_aciklama')->label('Ana açıklama')->rows(2)->maxLength(400)->columnSpanFull(),
                        TextInput::make('yonetim_slogani')->label('Site yönetimi sloganı')->maxLength(160),
                        Textarea::make('seo_aciklama')->label('Varsayılan SEO açıklaması')->rows(2)->maxLength(300),
                    ]),

                Section::make('Görseller')
                    ->description('Kullanım hakkı olan görseller yükleyin. Stok görseller gerçek ekip veya yönetilen site gibi sunulmaz.')
                    ->columns(3)
                    ->schema($gorselAlanlari),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->formIcerigi()]);
    }

    private function formIcerigi(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')->label('Kaydet')->submit('save'),
                ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $once = Ayar::tumu();
        $veri = $this->form->getState();

        // Boş dize yerine null: boş alan sitede hiç çizilmesin
        $veri = array_map(fn ($d) => $d === '' ? null : $d, $veri);

        Ayar::kaydet($veri);

        $degisen = array_keys(array_filter($veri, fn ($d, $k) => ($once[$k] ?? null) != $d, ARRAY_FILTER_USE_BOTH));
        if ($degisen !== []) {
            IslemKaydi::yaz('guncellendi', null, null, ['anahtarlar' => $degisen], 'Site ayarları');
        }

        Notification::make()->title('Ayarlar kaydedildi')->success()->send();
    }
}
