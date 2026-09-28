{{-- Aydınlatma onayı (zorunlu) ve pazarlama izni (isteğe bağlı) ayrı tutulur. Beklenen: $hatalar (MessageBag), $on (id öneki) --}}
<div class="form-alt">
    <label class="onay" for="{{ $on }}-kvkk">
        <input type="checkbox" name="kvkk" id="{{ $on }}-kvkk" value="1" required @checked(old('kvkk'))
               @if ($hatalar->has('kvkk')) aria-invalid="true" aria-describedby="{{ $on }}-kvkk-hata" @endif>
        <span><a href="{{ route('sayfa', 'kvkk-aydinlatma-metni') }}" target="_blank" rel="noopener">Aydınlatma metnini</a> okudum. Bilgilerim yalnızca bu başvurunun yanıtlanması için kullanılacaktır.</span>
    </label>
    @if ($hatalar->has('kvkk'))
        <p class="alan-hata" id="{{ $on }}-kvkk-hata">{{ $hatalar->first('kvkk') }}</p>
    @endif
    <label class="onay" for="{{ $on }}-pazarlama">
        <input type="checkbox" name="pazarlama" id="{{ $on }}-pazarlama" value="1" @checked(old('pazarlama'))>
        <span>Yeni ilan ve hizmetlerden haberdar olmak istiyorum <span class="istege-bagli">(isteğe bağlı)</span>.</span>
    </label>
</div>
