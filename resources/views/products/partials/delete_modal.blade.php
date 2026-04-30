@once
    <div id="product-delete-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1210;"></div>
    <div id="product-delete-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(520px, calc(100vw - 24px)); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:1211;">
        <div class="flex" style="justify-content:space-between; margin-bottom:10px;">
            <strong>{{ __('ui.delete_product_title') }}</strong>
            <button type="button" id="product-delete-close" class="btn info-btn product-action-btn" style="min-height:30px; padding:4px 10px;">&times;</button>
        </div>
        <p class="muted" style="margin-top:0;">{{ __('ui.delete_product_modal_note') }}</p>
        <div class="row" style="margin-bottom:10px;">
            <div class="col-12">
                <label>{{ __('ui.code') }}</label>
                <input type="text" id="product-delete-code" value="" disabled>
            </div>
            <div class="col-12">
                <label>{{ __('ui.name') }}</label>
                <input type="text" id="product-delete-name" value="" disabled>
            </div>
        </div>
        <form id="product-delete-form" method="post" action="">
            @csrf
            @method('DELETE')
            <div class="flex" style="gap:8px; justify-content:flex-end;">
                <button type="button" id="product-delete-cancel" class="btn secondary">{{ __('ui.cancel') }}</button>
                <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('product-delete-modal');
            const overlay = document.getElementById('product-delete-modal-overlay');
            const closeBtn = document.getElementById('product-delete-close');
            const cancelBtn = document.getElementById('product-delete-cancel');
            const form = document.getElementById('product-delete-form');
            const codeInput = document.getElementById('product-delete-code');
            const nameInput = document.getElementById('product-delete-name');

            if (!modal || !overlay || !closeBtn || !cancelBtn || !form || !codeInput || !nameInput) {
                return;
            }

            const openModal = (button) => {
                form.action = String(button.getAttribute('data-delete-url') || '');
                codeInput.value = String(button.getAttribute('data-product-code') || '-');
                nameInput.value = String(button.getAttribute('data-product-name') || '-');
                modal.style.display = 'block';
                overlay.style.display = 'block';
                setTimeout(() => cancelBtn.focus(), 50);
            };

            const closeModal = () => {
                modal.style.display = 'none';
                overlay.style.display = 'none';
                form.action = '';
            };

            document.querySelectorAll('.js-open-product-delete-modal').forEach((button) => {
                button.addEventListener('click', () => openModal(button));
            });

            closeBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);
            overlay.addEventListener('click', closeModal);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.style.display === 'block') {
                    closeModal();
                }
            });
        })();
    </script>
@endonce
