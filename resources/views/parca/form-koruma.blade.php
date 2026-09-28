{{-- Spam koruması: bal küpü alanı + şifreli form zamanı (BasvuruController::botMu) --}}
@csrf
<div class="bal" aria-hidden="true">
    <label>Web siteniz <input type="text" name="web_sitesi" tabindex="-1" autocomplete="off"></label>
</div>
<input type="hidden" name="form_zamani" value="{{ Crypt::encryptString((string) now()->timestamp) }}">
