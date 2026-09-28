<?php

namespace App\Filament\Resources\Portfoyler\Schemas;

use App\Enums\Islem;
use App\Enums\Kategori;
use App\Enums\OperasyonDurumu;
use App\Enums\YayinDurumu;
use App\Filament\Destek\Alanlar;
use App\Models\Kisi;
use App\Models\Konum;
use App\Models\Portfoy;
use App\Support\Para;
use App\Support\Yetki;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * Portföy formu (şartname 7.1). Alan grupları tablodaki sırayla; türe
 * göre anlamsız alanlar (arsada oda, konutta ada/parsel) hiç görünmez.
 *
 * Operasyon durumu (aktif → rezerve → satıldı...) ile yayın durumu
 * (taslak → incelemede → yayında...) AYRI bölümlerdedir. Yayına alma
 * yalnız üstteki "Yayımla" eylemiyle olur; eylem zorunlu alanları denetler.
 */
class PortfoyForm
{
    public static function kategori(Get $get): ?Kategori
    {
        $deger = $get('kategori');

        return $deger instanceof Kategori ? $deger : Kategori::tryFrom((string) $deger);
    }

    public static function islem(Get $get): ?Islem
    {
        $deger = $get('islem');

        return $deger instanceof Islem ? $deger : Islem::tryFrom((string) $deger);
    }

    /** Kayıtlı portföyde politika, yeni kayıtta izin. */
    public static function icBilgiGorur(?Portfoy $record): bool
    {
        return $record ? Gate::allows('icBilgi', $record) : Yetki::var('portfoy.ic_bilgi');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Kimlik')
                ->description('Portföy numarası kayıtta otomatik verilir.')
                ->columns(3)
                ->schema([
                    TextEntry::make('portfoy_no')
                        ->label('Portföy no')
                        ->visible(fn (?Portfoy $record) => $record !== null),
                    TextInput::make('baslik')
                        ->label('İlan başlığı')
                        ->required()
                        ->maxLength(160)
                        ->columnSpanFull(),
                    Select::make('islem')
                        ->label('İşlem')
                        ->options(Islem::class)
                        ->required()
                        ->live(),
                    Select::make('kategori')
                        ->label('Gayrimenkul türü')
                        ->options(Kategori::class)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('tip', null);
                        }),
                    Select::make('tip')
                        ->label('Tip')
                        ->options(fn (Get $get): array => static::kategori($get)?->tipler() ?? [])
                        ->required()
                        ->disabled(fn (Get $get): bool => static::kategori($get) === null)
                        ->helperText(fn (Get $get): ?string => static::kategori($get) ? null : 'Önce gayrimenkul türünü seçin.'),
                ]),

            Section::make('Konum')
                ->description('İl, ilçe ve mahalle ilanda görünür. Açık adres ve tam harita noktası ÖZELDİR: yalnız aşağıda ayrıca seçerseniz sitede gösterilir.')
                ->columns(3)
                ->schema([
                    Select::make('il_id')
                        ->label('İl')
                        ->options(fn () => Konum::secenekler('il'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('ilce_id', null);
                            $set('mahalle_id', null);
                        }),
                    Select::make('ilce_id')
                        ->label('İlçe')
                        ->options(fn (Get $get): array => $get('il_id') ? Konum::secenekler('ilce', (int) $get('il_id')) : [])
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('mahalle_id', null)),
                    Select::make('mahalle_id')
                        ->label('Mahalle')
                        ->options(fn (Get $get): array => $get('ilce_id') ? Konum::secenekler('mahalle', (int) $get('ilce_id')) : [])
                        ->searchable(),
                    Textarea::make('acik_adres')
                        ->label('Açık adres (özel)')
                        ->rows(2)
                        ->helperText('Varsayılan olarak yalnız panelde görünür.')
                        ->columnSpanFull(),
                    TextInput::make('enlem')->label('Enlem')->numeric()->minValue(-90)->maxValue(90)->placeholder('41.6771'),
                    TextInput::make('boylam')->label('Boylam')->numeric()->minValue(-180)->maxValue(180)->placeholder('26.5557'),
                    Select::make('harita_gosterimi')
                        ->label('Haritada gösterim')
                        ->options(Portfoy::HARITA_GOSTERIMI)
                        ->default('yok')
                        ->selectablePlaceholder(false)
                        ->helperText('"Yaklaşık" seçilirse nokta ~500 m yuvarlanır; gerçek konum sızmaz.'),
                    Toggle::make('adres_goster')
                        ->label('Açık adresi ilanda göster')
                        ->helperText('Kapalıyken sitede yalnız mahalle / ilçe / il görünür. Mülk sahibinin onayı olmadan açmayın.')
                        ->columnSpanFull(),
                ]),

            Section::make('Ticari bilgiler')
                ->description('Tutarlar Türkçe biçimde yazılabilir: 1.250.000 ya da 1250000. Fiyat değişiklikleri geçmişte saklanır.')
                ->columns(4)
                ->schema([
                    Alanlar::para('fiyat_kurus', 'Fiyat')->required(),
                    Select::make('para_birimi')
                        ->label('Para birimi')
                        ->options(Para::BIRIMLER)
                        ->default('TRY')
                        ->selectablePlaceholder(false)
                        ->required(),
                    Alanlar::para('depozito_kurus', 'Depozito')
                        ->visible(fn (Get $get) => static::islem($get) === Islem::Kiralik),
                    Alanlar::para('aidat_kurus', 'Aidat (aylık)'),
                ]),

            Section::make('Fiziksel özellikler')
                ->columns(4)
                ->schema([
                    TextInput::make('brut_m2')->label('Brüt m²')->numeric()->minValue(0),
                    TextInput::make('net_m2')->label('Net m²')->numeric()->minValue(0),
                    TextInput::make('oda')
                        ->label('Oda sayısı')
                        ->placeholder('3+1')
                        ->maxLength(10)
                        ->visible(fn (Get $get) => static::kategori($get)?->odaAlani() ?? false),
                    Grid::make(4)
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => static::kategori($get)?->yapiAlanlari() ?? false)
                        ->schema([
                            TextInput::make('banyo')->label('Banyo')->numeric()->minValue(0)->maxValue(20),
                            TextInput::make('kat')->label('Bulunduğu kat')->placeholder('3, Bahçe katı')->maxLength(20),
                            TextInput::make('kat_sayisi')->label('Kat sayısı')->numeric()->minValue(0)->maxValue(200),
                            TextInput::make('bina_yasi')->label('Bina yaşı')->numeric()->minValue(0)->maxValue(500),
                            Select::make('isitma')->label('Isıtma')->options(Portfoy::ISITMA),
                            Select::make('otopark')->label('Otopark')->options(Portfoy::OTOPARK),
                            Toggle::make('asansor')->label('Asansör')->inline(false),
                            Toggle::make('balkon')->label('Balkon')->inline(false),
                            Toggle::make('esyali')->label('Eşyalı')->inline(false),
                            Toggle::make('site_icinde')->label('Site içinde')->inline(false),
                        ]),
                ]),

            Section::make('Arsa bilgileri')
                ->description('Beyan edilen bilgilerdir; resmi imar durumu belgesi yerine geçmez.')
                ->columns(3)
                ->visible(fn (Get $get) => static::kategori($get)?->arsaAlanlari() ?? false)
                ->schema([
                    TextInput::make('ada')->label('Ada')->maxLength(20),
                    TextInput::make('parsel')->label('Parsel')->maxLength(20),
                    TextInput::make('imar_durumu')->label('İmar durumu')->maxLength(255),
                ]),

            Section::make('Özellikler')
                ->schema([
                    TagsInput::make('ozellikler')
                        ->label('Özellik etiketleri')
                        ->suggestions(Portfoy::OZELLIK_ONERILERI)
                        ->placeholder('Yazın ya da öneriden seçin')
                        ->helperText('Listede olmayan bir özelliği yazıp Enter\'a basarak ekleyebilirsiniz.'),
                ]),

            Section::make('Web yayını')
                ->columns(2)
                ->schema([
                    RichEditor::make('aciklama')
                        ->label('İlan açıklaması')
                        ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'h3', 'link', 'undo', 'redo'])
                        ->helperText('Yayın için en az 40 karakter. Mülk sahibinin adı, telefonu veya açık adres yazmayın.')
                        ->columnSpanFull(),
                    TextInput::make('video_url')->label('Video bağlantısı')->url()->maxLength(255)->placeholder('https://www.youtube.com/watch?v=…'),
                    Toggle::make('one_cikan')
                        ->label('Ana sayfada öne çıkar')
                        ->inline(false)
                        ->visible(fn () => Yetki::var('portfoy.yayimla')),
                    TextInput::make('seo_baslik')->label('SEO başlığı')->maxLength(255)->helperText('Boşsa ilan başlığı kullanılır.'),
                    Textarea::make('seo_aciklama')->label('SEO açıklaması')->maxLength(300)->rows(2)->helperText('Boşsa açıklamanın başı kullanılır.'),
                ]),

            Section::make('İlişkiler')
                ->columns(2)
                ->schema([
                    Alanlar::danisman(),
                    Select::make('mulk_sahibi_id')
                        ->label('Mülk sahibi')
                        ->visible(fn (?Portfoy $record) => static::icBilgiGorur($record))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Alanlar::kisiSecenekleri($search))
                        ->options(fn () => Alanlar::kisiSecenekleri())
                        ->getOptionLabelUsing(fn ($value) => ($k = Kisi::find($value)) ? Alanlar::kisiEtiketi($k) : null)
                        ->helperText('Mülk sahibi bilgisi hiçbir zaman sitede görünmez.')
                        ->createOptionModalHeading('Yeni kişi')
                        ->createOptionForm([
                            TextInput::make('ad_soyad')->label('Ad soyad / kurum adı')->required()->maxLength(255),
                            Select::make('tur')->label('Tür')->options(Kisi::TURLER)->default('bireysel')->required(),
                            TextInput::make('telefon')->label('Telefon')->tel()->maxLength(30),
                            TextInput::make('eposta')->label('E-posta')->email()->maxLength(255),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            return Kisi::create([...$data, 'olusturan_id' => auth()->id()])->id;
                        }),
                ]),

            Section::make('İç bilgiler')
                ->description('Yalnız panelde görünür; ilana, siteye ve dışa aktarıma (izinsiz) girmez.')
                ->columns(2)
                ->visible(fn (?Portfoy $record) => static::icBilgiGorur($record))
                ->schema([
                    Textarea::make('ic_notlar')->label('İç notlar')->rows(3),
                    TextInput::make('komisyon_anlasmasi')->label('Komisyon anlaşması')->maxLength(255)->placeholder('%2 + KDV, satıcıdan'),
                ]),

            Section::make('Durum')
                ->description('Operasyon durumu gayrimenkulün kendisini, yayın durumu ilanın sitede görünüp görünmediğini anlatır.')
                ->columns(2)
                ->schema([
                    Select::make('operasyon_durumu')
                        ->label('Operasyon durumu')
                        ->options(OperasyonDurumu::class)
                        ->default(OperasyonDurumu::Aktif->value)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->helperText('Satıldı / kiralandı olan ilan yayında kalabilir; sitede durum yazılır ve talep düğmesi kapanır.'),
                    Select::make('yayin_durumu')
                        ->label('Yayın durumu')
                        ->options(YayinDurumu::class)
                        ->default(YayinDurumu::Taslak->value)
                        ->selectablePlaceholder(false)
                        ->visible(fn () => Yetki::var('portfoy.yayimla'))
                        ->disableOptionWhen(fn (string $value, ?Portfoy $record): bool => $value === YayinDurumu::Yayinda->value
                            && $record?->yayin_durumu !== YayinDurumu::Yayinda)
                        ->helperText('Yayına almak için üstteki "Yayımla" düğmesini kullanın; zorunlu alanlar ve fotoğraf denetlenir.'),
                    TextEntry::make('yayin_durumu_gosterim')
                        ->label('Yayın durumu')
                        ->state(fn (?Portfoy $record) => $record?->yayin_durumu ?? YayinDurumu::Taslak)
                        ->badge()
                        ->helperText('Yayın yetkiniz yok. Kaydı tamamlayınca "İncelemeye gönder" ile onaya sunun.')
                        ->visible(fn () => ! Yetki::var('portfoy.yayimla')),
                ]),
        ]);
    }
}
