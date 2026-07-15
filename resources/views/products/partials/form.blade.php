<style>
    .category-ac-dropdown {
        position: fixed; z-index: 9999; background: var(--card,#fff);
        border: 1px solid var(--border,#d0d7de); border-radius: 6px;
        box-shadow: 0 6px 24px rgba(0,0,0,0.16); min-width: 220px;
        max-height: 240px; overflow-y: scroll; font-size: 13px;
    }
    .category-ac-dropdown::-webkit-scrollbar { width: 6px; }
    .category-ac-dropdown::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 0 6px 6px 0; }
    .category-ac-dropdown::-webkit-scrollbar-thumb { background: #c0c0c0; border-radius: 3px; }
    .category-ac-dropdown::-webkit-scrollbar-thumb:hover { background: #999; }
    .category-ac-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border,#d0d7de); line-height: 1.35; }
    .category-ac-item:last-child { border-bottom: none; }
    .category-ac-item.is-active, .category-ac-item:hover { background: var(--hover-bg,rgba(59,130,246,0.08)); }
    .category-ac-empty { padding: 10px 12px; color: var(--muted,#6b7280); font-style: italic; }
</style>

<div class="card">
    <div class="row inline">
        <div class="col-6">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('ui.product_info') }}</h3>
                <p class="form-section-note">{{ __('ui.product_info_note') }}</p>
                <div class="row">
                    <div class="col-4">
                        <label>{{ __('ui.product_type_label') }} <span class="label-required">*</span></label>
                        @php $resolvedProductType = old('product_type', $product?->product_type ?? 'general'); @endphp
                        <select id="product-type" name="product_type" required>
                            @foreach(($productTypeOptions ?? []) as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}" @selected($resolvedProductType === $typeKey)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.category') }} <span class="label-required">*</span></label>
                        @php
                            $categoryMap = $categories->keyBy('id');
                            $oldCategoryId = old('item_category_id', $product?->item_category_id);
                            $oldCategoryLabel = '';
                            $compactCategoryLabelPart = static function (string $value): string {
                                return preg_replace('/[^a-z0-9]/', '', strtolower(\Illuminate\Support\Str::ascii($value))) ?? '';
                            };
                            $categoryPartsLookSame = static function (string $code, string $name) use ($compactCategoryLabelPart): bool {
                                $normalizedCode = $compactCategoryLabelPart($code);
                                $normalizedName = $compactCategoryLabelPart($name);

                                if ($normalizedCode === '' || $normalizedName === '') {
                                    return false;
                                }

                                if ($normalizedCode === $normalizedName) {
                                    return true;
                                }

                                return abs(strlen($normalizedCode) - strlen($normalizedName)) <= 1
                                    && levenshtein($normalizedCode, $normalizedName) <= 1;
                            };
                            if ($oldCategoryId && $categoryMap->has($oldCategoryId)) {
                                $selectedCategory = $categoryMap[$oldCategoryId];
                                $selectedCode = trim((string) $selectedCategory->code);
                                $selectedName = trim((string) $selectedCategory->name);
                                $oldCategoryLabel = $selectedCode !== '' && ! $categoryPartsLookSame($selectedCode, $selectedName)
                                    ? $selectedCode.' - '.$selectedName
                                    : $selectedName;
                            }
                        @endphp
                        <input
                            id="product-category-search"
                            type="text"
                            value="{{ $oldCategoryLabel }}"
                            placeholder="{{ __('ui.search_item_categories_placeholder') }}"
                            autocomplete="off"
                            required
                        >
                        <input id="product-category" type="hidden" name="item_category_id" value="{{ $oldCategoryId }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.code') }}</label>
                        <input id="product-code" type="text" name="code" value="{{ old('code', $product?->code) }}">
                        <small class="muted" style="display:block; margin-top:4px;">{{ __('ui.product_code_format_hint') }}</small>
                        <small id="product-code-preview" class="muted" style="display:block; margin-top:4px;"></small>
                        <button id="product-code-reset" type="button" class="btn info-btn" style="display:none; margin-top:6px;">
                            {{ __('ui.product_code_use_auto') }}
                        </button>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.unit') }}</label>
                        @php
                            $resolvedUnit = old('unit', $product?->unit ?? ($defaultUnit ?? 'exp'));
                            $resolvedUnitExistsInOptions = collect($unitOptions ?? [])
                                ->contains(fn (array $unitOption): bool => (string) ($unitOption['code'] ?? '') === (string) $resolvedUnit);
                        @endphp
                        <select
                            id="product-unit"
                            name="unit"
                        >
                            @if($resolvedUnit !== '' && ! $resolvedUnitExistsInOptions)
                                <option value="{{ $resolvedUnit }}">{{ $resolvedUnit }}</option>
                            @endif
                            @foreach(($unitOptions ?? []) as $unitOption)
                                <option value="{{ $unitOption['code'] }}" @selected((string) $resolvedUnit === (string) $unitOption['code'])>
                                    {{ $unitOption['code'] }} - {{ $unitOption['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-8">
                        <label>{{ __('ui.name') }} <span class="label-required">*</span></label>
                        <input id="product-name" type="text" name="name" value="{{ old('name', $product?->name) }}" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('ui.product_stock_price') }}</h3>
                <p class="form-section-note">{{ __('ui.product_stock_price_note') }}</p>
                <div class="row">
                    <div class="col-4">
                        <label>{{ __('ui.stock') }} <span class="label-required">*</span></label>
                        @php $stockValue = old('stock', $product?->stock); @endphp
                        <input id="product-stock-display" type="text" inputmode="numeric" value="{{ $stockValue !== null ? number_format((int) round((float) $stockValue), 0, ',', '.') : '' }}" placeholder="0" style="max-width: 140px;" required>
                        <input id="product-stock" type="hidden" name="stock" value="{{ $stockValue !== null ? (int) round((float) $stockValue) : '' }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.price_agent') }} <span class="label-required js-price-required">*</span></label>
                        @php $priceAgentValue = old('price_agent', $product?->price_agent ?? 0); @endphp
                        <input id="product-price-agent-display" type="text" inputmode="numeric" value="{{ $priceAgentValue !== null ? number_format((int) round((float) $priceAgentValue), 0, ',', '.') : '' }}" placeholder="0" style="max-width: 140px;" required>
                        <input id="product-price-agent" type="hidden" name="price_agent" value="{{ $priceAgentValue !== null ? (int) round((float) $priceAgentValue) : '' }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.price_sales') }} <span class="label-required js-price-required">*</span></label>
                        @php $priceSalesValue = old('price_sales', $product?->price_sales ?? 0); @endphp
                        <input id="product-price-sales-display" type="text" inputmode="numeric" value="{{ $priceSalesValue !== null ? number_format((int) round((float) $priceSalesValue), 0, ',', '.') : '' }}" placeholder="0" style="max-width: 140px;" required>
                        <input id="product-price-sales" type="hidden" name="price_sales" value="{{ $priceSalesValue !== null ? (int) round((float) $priceSalesValue) : '' }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.price_general') }} <span class="label-required js-price-required">*</span></label>
                        @php $priceGeneralValue = old('price_general', $product?->price_general ?? 0); @endphp
                        <input id="product-price-general-display" type="text" inputmode="numeric" value="{{ $priceGeneralValue !== null ? number_format((int) round((float) $priceGeneralValue), 0, ',', '.') : '' }}" placeholder="0" style="max-width: 140px;" required>
                        <input id="product-price-general" type="hidden" name="price_general" value="{{ $priceGeneralValue !== null ? (int) round((float) $priceGeneralValue) : '' }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="flex" style="gap:8px; align-items:center; margin-top:12px;">
    <button class="btn" type="submit">{{ __('ui.save') }}</button>
    <a class="btn secondary" href="{{ route('products.index') }}">{{ __('ui.cancel') }}</a>
    @if(($product?->exists ?? false) && (auth()->user()?->canAccess('products.delete') ?? false))
        <button
            class="btn danger-btn js-open-product-delete-modal"
            type="button"
            data-product-code="{{ (string) ($product?->code ?? '') }}"
            data-product-name="{{ (string) ($product?->name ?? '') }}"
            data-delete-url="{{ route('products.destroy', $product) }}"
        >
            {{ __('ui.delete') }}
        </button>
    @endif
</div>

<script>
    (function () {
        @php
            $categoriesJson = $categories->values()->map(function ($category): array {
                return [
                    'id' => (int) $category->id,
                    'code' => (string) $category->code,
                    'name' => (string) $category->name,
                    'type' => (string) ($category->type ?: \App\Models\ItemCategory::TYPE_GENERAL),
                ];
            })->all();
        @endphp
        const form = document.querySelector('form');
        const nameInput = document.getElementById('product-name');
        const codeInput = document.getElementById('product-code');
        const categoryInput = document.getElementById('product-category');
        const categorySearchInput = document.getElementById('product-category-search');
        const currencyMappings = [
            ['product-stock-display', 'product-stock'],
            ['product-price-agent-display', 'product-price-agent'],
            ['product-price-sales-display', 'product-price-sales'],
            ['product-price-general-display', 'product-price-general'],
        ].map(([displayId, hiddenId]) => {
            return {
                display: document.getElementById(displayId),
                hidden: document.getElementById(hiddenId),
            };
        });
        const previewNode = document.getElementById('product-code-preview');
        const resetButton = document.getElementById('product-code-reset');
        const categories = @json($categoriesJson);
        const autoPreviewTemplate = @json(__('ui.product_code_auto_preview', ['code' => '__CODE__']));
        const manualPreviewTemplate = @json(__('ui.product_code_auto_preview_manual', ['code' => '__CODE__']));
        if (!nameInput || !codeInput || !categoryInput || !categorySearchInput || !previewNode || !resetButton) {
            return;
        }

        let autoCode = '';
        let manualOverride = false;
        const existingCode = (codeInput.value || '').trim();
        const existingName = (nameInput.value || '').trim();

        if (existingCode !== '' && existingName !== '') {
            manualOverride = true;
        }

        function normalize(value) {
            return (value || '')
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9\s]/g, ' ')
                .trim();
        }

        function categoryPrefix(categoryName) {
            const normalized = normalize(categoryName || '').replace(/\s+/g, ' ').trim();
            if (/\bpaket\s+sd\b/.test(normalized)) {
                return 'ps';
            }
            if (/\bpaket\s+smp\b/.test(normalized)) {
                return 'pp';
            }
            if (/\bpaket\s+sma\b/.test(normalized)) {
                return 'pa';
            }
            if (/\bpaket\s+mi\b/.test(normalized)) {
                return 'pi';
            }
            if (/\bpaket\s+mts\b/.test(normalized)) {
                return 'pt';
            }
            if (normalized === 'cerdas') {
                return 'c';
            }
            if (normalized === 'pintar') {
                return 'p';
            }
            return '';
        }

        function activeCategoryName() {
            const selectedId = Number(categoryInput.value || 0);
            if (selectedId > 0) {
                const selected = categories.find((category) => Number(category.id) === selectedId);
                if (selected && selected.name) {
                    return String(selected.name);
                }
            }

            const byLabel = findCategoryByLabel(categorySearchInput.value);
            if (byLabel && byLabel.name) {
                return String(byLabel.name);
            }

            return '';
        }

        function generateCode(name, categoryName) {
            const rawNormalized = (name || '')
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9/\-\s]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            const cleaned = normalize(name);
            if (cleaned === '') {
                return 'item';
            }

            if (!usesAcademicCodePattern(cleaned, rawNormalized)) {
                return generalProductToken(cleaned).slice(0, 60);
            }

            let subject = '';
            const languageMatch = cleaned.match(/\b(?:bahasa|bhs)\s+([a-z]+)/);
            if (languageMatch) {
                const language = languageMatch[1] || '';
                subject = language.replace(/[aeiou]/g, '').slice(0, 2);
                if (!subject) {
                    subject = language.slice(0, 2);
                }
                subject = `b${subject || 'it'}`;
            } else {
                const subjectMatch = cleaned.match(/[a-z]+/);
                const subjectRaw = subjectMatch ? subjectMatch[0] : 'item';
                subject = subjectRaw.replace(/[aeiou]/g, '').slice(0, 2);
                if (!subject) {
                    subject = subjectRaw.slice(0, 2);
                }
                if (!subject) {
                    subject = 'it';
                }
            }

            const levelMatch = cleaned.match(/\b(\d+)\b/);
            const level = levelMatch ? levelMatch[1] : '';

            const editionMatch = cleaned.match(/\b(?:edisi|ed)\s*(\d+)\b/i);
            const edition = editionMatch ? `e${editionMatch[1]}` : '';

            const semesterMatch = cleaned.match(/\b(?:semester|smt)\s*(\d+)\b/i);
            const semester = semesterMatch ? `s${semesterMatch[1]}` : '';

            let yearSuffix = '';
            const yearMatch = rawNormalized.match(/\b(\d{2}|\d{4})\s*[-/]\s*(\d{2}|\d{4})\b/);
            if (yearMatch) {
                yearSuffix = `${yearMatch[1].slice(-1)}${yearMatch[2].slice(-1)}`;
            } else {
                const shortYearMatches = Array.from(rawNormalized.matchAll(/\b(\d{2})(\d{2})\b/g));
                for (const shortYearMatch of shortYearMatches) {
                    const start = Number(shortYearMatch[1] || 0);
                    const end = Number(shortYearMatch[2] || 0);
                    if (((start + 1) % 100) !== end) {
                        continue;
                    }
                    yearSuffix = `${String(start).slice(-1)}${String(end).slice(-1)}`;
                    break;
                }
            }

            const prefix = categoryPrefix(categoryName);
            return `${prefix}${subject}${level}${edition}${semester}${yearSuffix}`.slice(0, 60);
        }

        function usesAcademicCodePattern(cleaned, rawNormalized) {
            if (/\b(?:bahasa|bhs|edisi|ed|semester|smt)\b/.test(cleaned)) {
                return true;
            }

            if (/\b(\d{2}|\d{4})\s*[-/]\s*(\d{2}|\d{4})\b/.test(rawNormalized)) {
                return true;
            }

            const shortYearMatches = Array.from(rawNormalized.matchAll(/\b(\d{2})(\d{2})\b/g));
            return shortYearMatches.some((shortYearMatch) => {
                const start = Number(shortYearMatch[1] || 0);
                const end = Number(shortYearMatch[2] || 0);

                return ((start + 1) % 100) === end;
            });
        }

        function compactSubjectToken(subjectRaw) {
            const raw = String(subjectRaw || '').toLowerCase();
            let token = raw.replace(/[aeiou]/g, '').slice(0, 2);
            if (!token) {
                token = raw.slice(0, 2);
            }

            return token || 'it';
        }

        function isGeneralUnitToken(token) {
            return ['gr', 'gram', 'gsm', 'kg', 'mm', 'cm', 'm', 'ml', 'ltr', 'liter'].includes(token);
        }

        function generalProductToken(cleaned) {
            const segments = [];
            const tokens = cleaned.split(/\s+/);

            for (const token of tokens) {
                if (!token) {
                    continue;
                }

                if (/^\d+[a-z]+$/.test(token)) {
                    segments.push(token.replace(/\D+/g, ''));
                    continue;
                }

                if (/^\d+$/.test(token)) {
                    segments.push(token);
                    continue;
                }

                if (isGeneralUnitToken(token)) {
                    continue;
                }

                segments.push(compactSubjectToken(token));
            }

            return segments.join('') || 'item';
        }

        function categoryLabel(category) {
            const code = String(category.code || '').trim();
            const name = String(category.name || '').trim();
            if (code !== '' && !categoryPartsLookSame(code, name)) {
                return `${code} - ${name}`;
            }
            return name || code;
        }

        function compactCategoryLabelPart(value) {
            return normalize(value).replace(/\s+/g, '');
        }

        function hasOneEditDistanceOrLess(left, right) {
            if (left === right) {
                return true;
            }

            if (Math.abs(left.length - right.length) > 1) {
                return false;
            }

            let indexLeft = 0;
            let indexRight = 0;
            let edits = 0;

            while (indexLeft < left.length && indexRight < right.length) {
                if (left[indexLeft] === right[indexRight]) {
                    indexLeft += 1;
                    indexRight += 1;
                    continue;
                }

                edits += 1;
                if (edits > 1) {
                    return false;
                }

                if (left.length > right.length) {
                    indexLeft += 1;
                    continue;
                }

                if (right.length > left.length) {
                    indexRight += 1;
                    continue;
                }

                indexLeft += 1;
                indexRight += 1;
            }

            return edits + (left.length - indexLeft) + (right.length - indexRight) <= 1;
        }

        function categoryPartsLookSame(code, name) {
            const normalizedCode = compactCategoryLabelPart(code);
            const normalizedName = compactCategoryLabelPart(name);

            if (normalizedCode === '' || normalizedName === '') {
                return false;
            }

            return hasOneEditDistanceOrLess(normalizedCode, normalizedName);
        }

        function findCategoryByLabel(value) {
            const normalized = (value || '').trim().toLowerCase();
            if (normalized === '') {
                return null;
            }

            return categories.find((category) => categoryLabel(category).toLowerCase() === normalized)
                || categories.find((category) => String(category.code || '').toLowerCase() === normalized)
                || categories.find((category) => String(category.name || '').toLowerCase() === normalized)
                || categories.find((category) => categoryLabel(category).toLowerCase().includes(normalized))
                || categories.find((category) => String(category.name || '').toLowerCase().includes(normalized))
                || null;
        }

        const MIN_CATEGORY_SEARCH_LENGTH = 3;

        function canSearchCategory(value) {
            return normalize(value || '').length >= MIN_CATEGORY_SEARCH_LENGTH;
        }

        function createCategoryAutocomplete(inputEl, hiddenEl, onSelect) {
            let dropdown = null, activeIdx = -1, currentMatches = [], blurTimer = null;
            const esc = (s) => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            function getMatches(query) {
                const q = normalize(query || '');
                // Only offer categories that belong to the selected product type.
                const selectedType = document.getElementById('product-type')?.value || 'general';
                return categories.filter((category) => {
                    if (String(category.type || 'general') !== selectedType) {
                        return false;
                    }
                    return normalize(categoryLabel(category)).includes(q)
                        || normalize(category.code).includes(q)
                        || normalize(category.name).includes(q);
                }).slice(0, 20);
            }
            function position() {
                if (!dropdown) return;
                const r = inputEl.getBoundingClientRect(), dropH = Math.min(240, currentMatches.length * 36 + 12), below = window.innerHeight - r.bottom;
                dropdown.style.left = r.left + 'px'; dropdown.style.width = Math.max(r.width, 220) + 'px';
                dropdown.style.top = (below >= dropH || below >= r.top) ? (r.bottom + 2) + 'px' : (r.top - dropH - 2) + 'px';
            }
            function close() { dropdown?.remove(); dropdown = null; currentMatches = []; activeIdx = -1; }
            function setActive(idx) { activeIdx = idx; dropdown?.querySelectorAll('.category-ac-item').forEach((el, i) => el.classList.toggle('is-active', i === idx)); }
            function pick(idx) {
                const category = currentMatches[idx]; if (!category) return;
                inputEl.value = categoryLabel(category); hiddenEl.value = category.id; close(); onSelect(category);
            }
            function open(matches) {
                close(); currentMatches = matches;
                dropdown = document.createElement('div');
                dropdown.className = 'category-ac-dropdown';
                dropdown.innerHTML = matches.length === 0
                    ? '<div class="category-ac-empty">Kategori tidak ditemukan</div>'
                    : matches.map((category, i) => `<div class="category-ac-item" data-idx="${i}">${esc(categoryLabel(category))}</div>`).join('');
                document.body.appendChild(dropdown); position();
                dropdown.addEventListener('mousedown', e => { const item = e.target.closest('.category-ac-item'); if (!item) return; e.preventDefault(); pick(parseInt(item.dataset.idx, 10)); });
                dropdown.addEventListener('mousemove', e => { const item = e.target.closest('.category-ac-item'); if (item) setActive(parseInt(item.dataset.idx, 10)); });
            }
            function suggest(query) {
                if (!canSearchCategory(query)) {
                    close();
                    return;
                }
                open(getMatches(query));
            }
            inputEl.addEventListener('input', () => {
                const value = inputEl.value;
                hiddenEl.value = '';
                onSelect(null);
                if (!canSearchCategory(value)) {
                    close();
                    return;
                }
                suggest(value);
            });
            inputEl.addEventListener('focus', () => {
                clearTimeout(blurTimer);
                if (!canSearchCategory(categorySearchInput.value)) {
                    close();
                    return;
                }
                suggest(inputEl.value);
            });
            inputEl.addEventListener('blur', () => {
                blurTimer = setTimeout(() => {
                    close();
                    const val = inputEl.value.trim();
                    if (val === '') { hiddenEl.value = ''; onSelect(null); return; }
                    const category = findCategoryByLabel(val);
                    hiddenEl.value = category ? category.id : '';
                    if (category) { inputEl.value = categoryLabel(category); onSelect(category); }
                    else { onSelect(null); }
                }, 200);
            });
            inputEl.addEventListener('keydown', e => {
                if (e.key === 'ArrowDown') { e.preventDefault(); if (!dropdown) { suggest(inputEl.value); setActive(0); return; } setActive(Math.min(activeIdx + 1, currentMatches.length - 1)); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(activeIdx - 1, 0)); }
                else if (e.key === 'Enter' && dropdown && activeIdx >= 0) { e.preventDefault(); pick(activeIdx); }
                else if (e.key === 'Escape') { close(); }
            });
            const repos = () => position();
            window.addEventListener('scroll', repos, { passive: true });
            window.addEventListener('resize', repos, { passive: true });
        }

        function syncCode() {
            const generated = generateCode(nameInput.value, activeCategoryName());
            previewNode.textContent = (manualOverride ? manualPreviewTemplate : autoPreviewTemplate)
                .replace('__CODE__', generated);
            resetButton.style.display = manualOverride ? 'inline-flex' : 'none';

            if (manualOverride) {
                return;
            }

            codeInput.value = generated;
            autoCode = generated;
        }

        function digitsOnly(value) {
            return (value || '').toString().replace(/\D/g, '');
        }

        function formatThousands(value) {
            if (!value) {
                return '';
            }
            return value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function bindCurrencyInput(mapping) {
            if (!mapping.display || !mapping.hidden) {
                return;
            }

            const sync = () => {
                const digits = digitsOnly(mapping.display.value);
                mapping.hidden.value = digits;
                mapping.display.value = formatThousands(digits);
            };

            mapping.display.addEventListener('input', sync);
            mapping.display.addEventListener('focus', () => {
                if (mapping.display.value.trim() === '0') {
                    mapping.display.value = '';
                    mapping.hidden.value = '';
                }
            });
            mapping.display.addEventListener('blur', () => {
                if (mapping.display.value.trim() === '') {
                    mapping.display.value = '0';
                    mapping.hidden.value = '0';
                    return;
                }
                sync();
            });
            sync();
        }

        nameInput.addEventListener('input', syncCode);
        createCategoryAutocomplete(categorySearchInput, categoryInput, function () {
            syncCode();
        });
        codeInput.addEventListener('input', function () {
            const normalized = (codeInput.value || '').toLowerCase().replace(/[^a-z0-9\-]/g, '');
            if (codeInput.value !== normalized) {
                codeInput.value = normalized;
            }
            const current = (codeInput.value || '').trim().toLowerCase();
            if (current === '' || current === autoCode.toLowerCase()) {
                manualOverride = false;
                syncCode();
                return;
            }
            manualOverride = true;
            syncCode();
        });
        resetButton.addEventListener('click', function () {
            manualOverride = false;
            syncCode();
        });

        currencyMappings.forEach(bindCurrencyInput);

        const productTypeSelect = document.getElementById('product-type');
        const priceDisplayInputs = [
            document.getElementById('product-price-agent-display'),
            document.getElementById('product-price-sales-display'),
            document.getElementById('product-price-general-display'),
        ];
        const priceRequiredSpans = Array.from(document.querySelectorAll('.js-price-required'));

        function syncPriceRequired() {
            const isRawMaterial = (productTypeSelect?.value || '') === 'raw_material';
            priceDisplayInputs.forEach((input) => {
                if (!input) return;
                if (isRawMaterial) {
                    input.removeAttribute('required');
                } else {
                    input.setAttribute('required', '');
                }
            });
            priceRequiredSpans.forEach((span) => {
                span.style.display = isRawMaterial ? 'none' : '';
            });
        }

        function clearCategoryIfTypeMismatch() {
            const selectedId = parseInt(categoryInput?.value || '', 10);
            if (!selectedId) {
                return;
            }
            const selected = categories.find((category) => category.id === selectedId);
            const selectedType = productTypeSelect?.value || 'general';
            if (selected && String(selected.type || 'general') === selectedType) {
                return;
            }
            categoryInput.value = '';
            if (categorySearchInput) {
                categorySearchInput.value = '';
            }
            syncCode();
        }

        if (productTypeSelect) {
            productTypeSelect.addEventListener('change', function () {
                syncPriceRequired();
                clearCategoryIfTypeMismatch();
            });
        }
        syncPriceRequired();

        if (form) {
            form.addEventListener('submit', function (event) {
                if ((categoryInput.value || '').trim() !== '') {
                    return;
                }
                event.preventDefault();
                categorySearchInput.focus();
            });
        }

        syncCode();
    })();
</script>
