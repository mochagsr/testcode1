<div class="card">
    <div class="row inline">
        <div class="col-6">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('ui.product_info') }}</h3>
                <p class="form-section-note">{{ __('ui.product_info_note') }}</p>
                <div class="row">
                    <div class="col-4">
                        <label>{{ __('ui.category') }} <span class="label-required">*</span></label>
                        @php
                            $categoryMap = $categories->keyBy('id');
                            $oldCategoryId = old('item_category_id', $product?->item_category_id);
                            $oldCategoryLabel = $oldCategoryId && $categoryMap->has($oldCategoryId)
                                ? $categoryMap[$oldCategoryId]->code.' - '.$categoryMap[$oldCategoryId]->name
                                : '';
                        @endphp
                        <input
                            id="product-category-search"
                            type="text"
                            list="product-categories-list"
                            value="{{ $oldCategoryLabel }}"
                            placeholder="{{ __('ui.search_item_categories_placeholder') }}"
                            required
                        >
                        <input id="product-category" type="hidden" name="item_category_id" value="{{ $oldCategoryId }}" required>
                        <datalist id="product-categories-list">
                            @foreach($categories as $category)
                                <option value="{{ $category->code }} - {{ $category->name }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.code') }}</label>
                        <input id="product-code" type="text" name="code" value="{{ old('code', $product?->code) }}">
                        <small class="muted" style="display:block; margin-top:4px;">{{ __('ui.product_code_format_hint') }}</small>
                        <small id="product-code-preview" class="muted" style="display:block; margin-top:4px;"></small>
                        <button id="product-code-reset" type="button" class="btn secondary" style="display:none; margin-top:6px;">
                            {{ __('ui.product_code_use_auto') }}
                        </button>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.unit') }}</label>
                        @php
                            $resolvedUnit = old('unit', $product?->unit ?? ($defaultUnit ?? 'exp'));
                        @endphp
                        <input
                            id="product-unit"
                            type="text"
                            name="unit"
                            list="product-unit-list"
                            value="{{ $resolvedUnit }}"
                            placeholder="exp"
                        >
                        <datalist id="product-unit-list">
                            @foreach(($unitOptions ?? []) as $unitOption)
                                <option value="{{ $unitOption['code'] }}" label="{{ $unitOption['label'] }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-12">
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
                    <div class="col-3">
                        <label>{{ __('ui.stock') }} <span class="label-required">*</span></label>
                        @php $stockValue = old('stock', $product?->stock); @endphp
                        <input id="product-stock-display" type="text" inputmode="numeric" value="{{ $stockValue !== null ? number_format((int) round((float) $stockValue), 0, ',', '.') : '' }}" placeholder="0" style="max-width: 140px;" required>
                        <input id="product-stock" type="hidden" name="stock" value="{{ $stockValue !== null ? (int) round((float) $stockValue) : '' }}" required>
                    </div>
                    <div class="col-3">
                        <label>{{ __('ui.price_agent') }} <span class="label-required">*</span></label>
                        @php $priceAgentValue = old('price_agent', $product?->price_agent); @endphp
                        <input id="product-price-agent-display" type="text" inputmode="numeric" value="{{ $priceAgentValue !== null ? number_format((int) round((float) $priceAgentValue), 0, ',', '.') : '' }}" placeholder="0" style="max-width: 140px;" required>
                        <input id="product-price-agent" type="hidden" name="price_agent" value="{{ $priceAgentValue !== null ? (int) round((float) $priceAgentValue) : '' }}" required>
                    </div>
                    <div class="col-3">
                        <label>{{ __('ui.price_sales') }} <span class="label-required">*</span></label>
                        @php $priceSalesValue = old('price_sales', $product?->price_sales); @endphp
                        <input id="product-price-sales-display" type="text" inputmode="numeric" value="{{ $priceSalesValue !== null ? number_format((int) round((float) $priceSalesValue), 0, ',', '.') : '' }}" placeholder="0" style="max-width: 140px;" required>
                        <input id="product-price-sales" type="hidden" name="price_sales" value="{{ $priceSalesValue !== null ? (int) round((float) $priceSalesValue) : '' }}" required>
                    </div>
                    <div class="col-3">
                        <label>{{ __('ui.price_general') }} <span class="label-required">*</span></label>
                        @php $priceGeneralValue = old('price_general', $product?->price_general); @endphp
                        <input id="product-price-general-display" type="text" inputmode="numeric" value="{{ $priceGeneralValue !== null ? number_format((int) round((float) $priceGeneralValue), 0, ',', '.') : '' }}" placeholder="0" style="max-width: 140px;" required>
                        <input id="product-price-general" type="hidden" name="price_general" value="{{ $priceGeneralValue !== null ? (int) round((float) $priceGeneralValue) : '' }}" required>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('products.index') }}">{{ __('ui.cancel') }}</a>

<script>
    (function () {
        @php
            $categoriesJson = $categories->values()->map(function ($category): array {
                return [
                    'id' => (int) $category->id,
                    'code' => (string) $category->code,
                    'name' => (string) $category->name,
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
        const SEARCH_DEBOUNCE_MS = 100;
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

        function generateCode(name) {
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

            const subjectMatch = cleaned.match(/[a-z]+/);
            const subjectRaw = subjectMatch ? subjectMatch[0] : 'item';
            let subject = subjectRaw.replace(/[aeiou]/g, '').slice(0, 2);
            if (!subject) {
                subject = subjectRaw.slice(0, 2);
            }
            if (!subject) {
                subject = 'it';
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
            }

            return `${subject}${level}${edition}${semester}${yearSuffix}`.slice(0, 60);
        }

        function categoryLabel(category) {
            return `${category.code} - ${category.name}`;
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

        function syncCode() {
            const generated = generateCode(nameInput.value);
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

        const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
            ? (fn, wait = SEARCH_DEBOUNCE_MS) => window.PgposAutoSearch.debounce(fn, wait)
            : (fn, wait = SEARCH_DEBOUNCE_MS) => {
                let timeoutId = null;
                return (...args) => {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => fn(...args), wait);
                };
            };

        nameInput.addEventListener('input', syncCode);
        const onCategoryInput = debounce(function () {
            const category = findCategoryByLabel(categorySearchInput.value);
            categoryInput.value = category ? category.id : '';
            if (category) {
                categorySearchInput.value = categoryLabel(category);
            }
            syncCode();
        });
        categorySearchInput.addEventListener('input', onCategoryInput);
        categorySearchInput.addEventListener('change', function () {
            const category = findCategoryByLabel(categorySearchInput.value);
            categoryInput.value = category ? category.id : '';
            if (category) {
                categorySearchInput.value = categoryLabel(category);
            }
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
