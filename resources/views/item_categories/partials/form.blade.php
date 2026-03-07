<div class="card">
    <div class="row">
        <div class="col-4">
            <label>{{ __('ui.code') }}</label>
            <input id="item-category-code" type="text" name="code" value="{{ old('code', $category?->code) }}">
            <small id="item-category-code-preview" class="muted" style="display:block; margin-top:4px;"></small>
            <button id="item-category-code-reset" type="button" class="btn secondary" style="display:none; margin-top:6px;">
                {{ __('ui.product_code_use_auto') }}
            </button>
        </div>
        <div class="col-4">
            <label>{{ __('ui.name') }} <span class="label-required">*</span></label>
            <input id="item-category-name" type="text" name="name" value="{{ old('name', $category?->name) }}" required>
        </div>
        <div class="col-12">
            <label>{{ __('ui.description') }}</label>
            <textarea name="description" rows="3">{{ old('description', $category?->description) }}</textarea>
        </div>
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('item-categories.index') }}">{{ __('ui.cancel') }}</a>

<script>
    (function () {
        const nameInput = document.getElementById('item-category-name');
        const codeInput = document.getElementById('item-category-code');
        const previewNode = document.getElementById('item-category-code-preview');
        const resetButton = document.getElementById('item-category-code-reset');
        const autoPreviewTemplate = @json(__('ui.product_code_auto_preview', ['code' => '__CODE__']));
        const manualPreviewTemplate = @json(__('ui.product_code_auto_preview_manual', ['code' => '__CODE__']));
        if (!nameInput || !codeInput || !previewNode || !resetButton) {
            return;
        }

        let manualOverride = false;
        const existingCode = (codeInput.value || '').trim();
        const existingName = (nameInput.value || '').trim();
        if (existingCode !== '' && existingName !== '') {
            manualOverride = true;
        }

        function generateCode(name) {
            const normalized = (name || '')
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, ' ')
                .trim();
            if (normalized === '') {
                return 'kategori';
            }

            const parts = normalized.split(/\s+/).filter(Boolean);
            if (parts.length === 1) {
                return parts[0].slice(0, 6);
            }

            return parts.map((part) => part.slice(0, 3)).join('').slice(0, 12);
        }

        function syncCode() {
            const generated = generateCode(nameInput.value);
            previewNode.textContent = (manualOverride ? manualPreviewTemplate : autoPreviewTemplate)
                .replace('__CODE__', generated);
            resetButton.style.display = manualOverride ? 'inline-flex' : 'none';

            if (!manualOverride) {
                codeInput.value = generated;
            }
        }

        nameInput.addEventListener('input', syncCode);
        codeInput.addEventListener('input', () => {
            const currentValue = (codeInput.value || '').trim();
            const generated = generateCode(nameInput.value);
            manualOverride = currentValue !== '' && currentValue !== generated;
            syncCode();
        });
        resetButton.addEventListener('click', () => {
            manualOverride = false;
            syncCode();
            codeInput.focus();
            codeInput.setSelectionRange(codeInput.value.length, codeInput.value.length);
        });

        syncCode();
    })();
</script>
