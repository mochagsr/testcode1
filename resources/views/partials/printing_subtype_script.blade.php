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
            const modal = wrapper.querySelector('[data-printing-subtype-modal]');
            const overlay = wrapper.querySelector('[data-printing-subtype-modal-overlay]');
            const nameInput = wrapper.querySelector('[data-printing-subtype-name]');
            const status = wrapper.querySelector('[data-printing-subtype-modal-status]');
            const closeButtons = Array.from(wrapper.querySelectorAll('[data-printing-subtype-modal-close]'));
            const saveButton = wrapper.querySelector('[data-printing-subtype-save]');
            if (!select || !addButton || !modal || !overlay || !nameInput || !saveButton) {
                return;
            }

            const customerField = document.querySelector(select.dataset.customerField || '');
            const transactionTypeField = document.querySelector(select.dataset.transactionTypeField || '');
            const form = select.closest('form');
            const csrfField = form ? form.querySelector('input[name="_token"]') : null;
            const baseOptionLabel = @json(__('txn.printing_subtype_select'));
            const addCustomerFirstLabel = @json(__('txn.printing_subtype_customer_first'));
            const addOnlyForPrintLabel = @json(__('txn.printing_subtype_only_for_print'));
            const addFailedLabel = @json(__('txn.printing_subtype_add_failed'));
            const addSuccessLabel = @json(__('txn.printing_subtype_add_success'));
            const addValidationLabel = @json(__('txn.printing_subtype_add_validation'));

            let selectedId = String(select.dataset.selectedId || '').trim();
            let selectedName = String(select.dataset.selectedName || '').trim();
            let lastLoadedCustomerId = '';

            const setHint = (message) => {
                if (hint) {
                    hint.textContent = message;
                }
            };

            const setModalStatus = (message, isError = false) => {
                if (!status) {
                    return;
                }
                status.textContent = message;
                status.style.color = isError ? '#dc2626' : 'var(--muted)';
            };

            const setDisabledState = (disabled) => {
                select.disabled = disabled;
                addButton.disabled = disabled;
            };

            const closeModal = () => {
                modal.style.display = 'none';
                overlay.style.display = 'none';
                nameInput.value = '';
                setModalStatus('');
            };

            const openModal = () => {
                modal.style.display = 'block';
                overlay.style.display = 'block';
                nameInput.value = '';
                setModalStatus('');
                window.setTimeout(() => nameInput.focus(), 0);
            };

            const fillOptions = (rows) => {
                const currentValue = String(select.value || selectedId || '').trim();
                const options = [`<option value="">${baseOptionLabel}</option>`];
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
                    options.push(`<option value="${id}">${name}</option>`);
                });

                if (!hasSelected && selectedId !== '' && selectedName !== '') {
                    options.push(`<option value="${selectedId}">${selectedName}</option>`);
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

            const syncVisibility = async () => {
                const isPrinting = currentTransactionType() === 'printing';
                wrapper.style.display = isPrinting ? '' : 'none';
                if (!isPrinting) {
                    select.value = '';
                    closeModal();
                    setDisabledState(true);
                    setHint(addOnlyForPrintLabel);
                    return;
                }

                const customerId = currentCustomerId();
                if (customerId === '') {
                    select.value = '';
                    closeModal();
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

            addButton.addEventListener('click', () => {
                if (currentTransactionType() !== 'printing') {
                    setHint(addOnlyForPrintLabel);
                    return;
                }
                if (currentCustomerId() === '') {
                    setHint(addCustomerFirstLabel);
                    return;
                }
                openModal();
            });

            saveButton.addEventListener('click', async () => {
                const customerId = currentCustomerId();
                const name = String(nameInput.value || '').trim();
                if (name === '') {
                    setModalStatus(addValidationLabel, true);
                    nameInput.focus();
                    return;
                }

                saveButton.disabled = true;
                setModalStatus('');
                try {
                    const url = @json(url('/api/customers/__CUSTOMER__/printing-subtypes')).replace('__CUSTOMER__', encodeURIComponent(customerId));
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfField ? csrfField.value : '',
                        },
                        body: JSON.stringify({ name }),
                    });

                    if (!response.ok) {
                        setModalStatus(addFailedLabel, true);
                        return;
                    }

                    const payload = await response.json();
                    selectedId = String(payload?.data?.id || '');
                    selectedName = String(payload?.data?.name || '').trim();
                    await loadOptions(customerId);
                    if (selectedId !== '') {
                        select.value = selectedId;
                    }
                    setHint(selectedName !== '' ? addSuccessLabel.replace(':name', selectedName) : '');
                    closeModal();
                } catch (error) {
                    setModalStatus(addFailedLabel, true);
                } finally {
                    saveButton.disabled = false;
                }
            });

            select.addEventListener('change', () => {
                selectedId = String(select.value || '').trim();
                selectedName = selectedId !== ''
                    ? String(select.options[select.selectedIndex]?.text || '').trim()
                    : '';
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', closeModal);
            });
            overlay.addEventListener('click', closeModal);
            nameInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    saveButton.click();
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeModal();
                }
            });

            if (customerField) {
                customerField.addEventListener('change', () => {
                    lastLoadedCustomerId = '';
                    selectedId = '';
                    selectedName = '';
                    closeModal();
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
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.style.display === 'block') {
                    closeModal();
                }
            });

            syncVisibility();
        });
    })();
</script>
