<script>
    (function () {
        const wrappers = Array.from(document.querySelectorAll('[data-printing-subtype-wrap]'));
        if (!wrappers.length) {
            return;
        }

        wrappers.forEach((wrapper) => {
            const select = wrapper.querySelector('[data-printing-subtype-select]');
            const addButton = wrapper.querySelector('[data-printing-subtype-add]');
            const hint = wrapper.querySelector('[data-printing-subtype-hint]');
            if (!select || !addButton) {
                return;
            }

            const customerField = document.querySelector(select.dataset.customerField || '');
            const transactionTypeField = document.querySelector(select.dataset.transactionTypeField || '');
            const form = select.closest('form');
            const csrfField = form ? form.querySelector('input[name=\"_token\"]') : null;
            const baseOptionLabel = @json(__('txn.printing_subtype_select'));
            const addPromptLabel = @json(__('txn.printing_subtype_add_prompt'));
            const addCustomerFirstLabel = @json(__('txn.printing_subtype_customer_first'));
            const addOnlyForPrintLabel = @json(__('txn.printing_subtype_only_for_print'));
            const addFailedLabel = @json(__('txn.printing_subtype_add_failed'));

            let selectedId = String(select.dataset.selectedId || '').trim();
            let selectedName = String(select.dataset.selectedName || '').trim();
            let lastLoadedCustomerId = '';

            const setHint = (message) => {
                if (hint) {
                    hint.textContent = message;
                }
            };

            const setDisabledState = (disabled) => {
                select.disabled = disabled;
                addButton.disabled = disabled;
            };

            const fillOptions = (rows) => {
                const currentValue = String(select.value || selectedId || '').trim();
                const options = [`<option value=\"\">${baseOptionLabel}</option>`];
                let hasSelected = false;

                (rows || []).forEach((row) => {
                    const id = String(row.id || '');
                    const name = String(row.name || '').trim();
                    if (id === '' || name === '') {
                        return;
                    }
                    if (id === currentValue) {
                        hasSelected = true;
                    }
                    options.push(`<option value=\"${id}\">${name}</option>`);
                });

                if (!hasSelected && selectedId !== '' && selectedName !== '') {
                    options.push(`<option value=\"${selectedId}\">${selectedName}</option>`);
                }

                select.innerHTML = options.join('');
                if (currentValue !== '') {
                    select.value = currentValue;
                } else if (selectedId !== '') {
                    select.value = selectedId;
                }
            };

            const currentCustomerId = () => String(customerField?.value || '').trim();
            const currentTransactionType = () => String(transactionTypeField?.value || '').trim();

            const syncVisibility = async () => {
                const isPrinting = currentTransactionType() === 'printing';
                wrapper.style.display = isPrinting ? '' : 'none';
                if (!isPrinting) {
                    select.value = '';
                    setDisabledState(true);
                    setHint(addOnlyForPrintLabel);
                    return;
                }

                const customerId = currentCustomerId();
                if (customerId === '') {
                    select.value = '';
                    setDisabledState(true);
                    setHint(addCustomerFirstLabel);
                    return;
                }

                setDisabledState(false);
                setHint('');
                if (customerId !== lastLoadedCustomerId) {
                    await loadOptions(customerId);
                }
            };

            const loadOptions = async (customerId) => {
                if (!customerId) {
                    fillOptions([]);
                    return;
                }

                const url = @json(url('/api/customers/__CUSTOMER__/printing-subtypes')).replace('__CUSTOMER__', encodeURIComponent(customerId));
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                lastLoadedCustomerId = customerId;
                fillOptions(payload.data || []);
            };

            addButton.addEventListener('click', async () => {
                const customerId = currentCustomerId();
                if (currentTransactionType() !== 'printing') {
                    setHint(addOnlyForPrintLabel);
                    return;
                }
                if (customerId === '') {
                    setHint(addCustomerFirstLabel);
                    return;
                }

                const name = window.prompt(addPromptLabel, '');
                if (!name || !name.trim()) {
                    return;
                }

                const url = @json(url('/api/customers/__CUSTOMER__/printing-subtypes')).replace('__CUSTOMER__', encodeURIComponent(customerId));
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfField ? csrfField.value : '',
                    },
                    body: JSON.stringify({ name: name.trim() }),
                });

                if (!response.ok) {
                    setHint(addFailedLabel);
                    return;
                }

                const payload = await response.json();
                selectedId = String(payload?.data?.id || '');
                selectedName = String(payload?.data?.name || '').trim();
                await loadOptions(customerId);
                if (selectedId !== '') {
                    select.value = selectedId;
                }
                setHint(selectedName !== '' ? `Subjenis tersimpan: ${selectedName}` : '');
            });
            select.addEventListener('change', () => {
                selectedId = String(select.value || '').trim();
                selectedName = selectedId !== ''
                    ? String(select.options[select.selectedIndex]?.text || '').trim()
                    : '';
            });

            if (customerField) {
                customerField.addEventListener('change', () => {
                    lastLoadedCustomerId = '';
                    selectedId = '';
                    selectedName = '';
                    syncVisibility();
                });
                customerField.addEventListener('input', () => {
                    lastLoadedCustomerId = '';
                    selectedId = '';
                    selectedName = '';
                });
            }
            if (transactionTypeField) {
                transactionTypeField.addEventListener('change', syncVisibility);
            }
            if (form) {
                form.addEventListener('change', () => {
                    window.setTimeout(syncVisibility, 0);
                });
                form.addEventListener('input', () => {
                    window.setTimeout(syncVisibility, 0);
                });
            }

            syncVisibility();
        });
    })();
</script>
