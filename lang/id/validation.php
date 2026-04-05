<?php

return [
    'accepted' => ':attribute harus disetujui.',
    'array' => ':attribute harus berupa daftar.',
    'boolean' => ':attribute harus berupa ya atau tidak.',
    'date' => ':attribute harus berupa tanggal yang valid.',
    'exists' => ':attribute tidak terdaftar.',
    'in' => 'Pilihan :attribute tidak valid.',
    'integer' => ':attribute harus berupa angka bulat.',
    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih dari :max KB.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'array' => ':attribute minimal :min item.',
        'file' => ':attribute minimal :min KB.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'after_or_equal' => ':attribute harus sama atau setelah :date.',

    'custom' => [
        'customer_id' => [
            'required' => 'Customer wajib dipilih dari daftar.',
            'exists' => 'Customer tidak terdaftar.',
        ],
        'supplier_id' => [
            'required' => 'Supplier wajib dipilih dari daftar.',
            'exists' => 'Supplier tidak terdaftar.',
        ],
        'order_note_id' => [
            'exists' => 'Surat pesanan tidak terdaftar.',
        ],
        'sales_invoice_id' => [
            'exists' => 'Faktur tidak terdaftar.',
        ],
        'items' => [
            'required' => 'Minimal satu barang harus diisi.',
            'array' => 'Daftar barang tidak valid.',
            'min' => 'Minimal satu barang harus diisi.',
        ],
        'items.*.product_id' => [
            'required' => 'Barang wajib dipilih dari daftar.',
            'exists' => 'Barang tidak terdaftar.',
        ],
        'items.*.quantity' => [
            'required' => 'Qty wajib diisi.',
            'integer' => 'Qty harus berupa angka bulat.',
            'min' => 'Qty minimal 1.',
        ],
        'items.*.unit_price' => [
            'required' => 'Harga wajib diisi.',
            'numeric' => 'Harga harus berupa angka.',
            'min' => 'Harga tidak boleh kurang dari 0.',
        ],
        'items.*.discount' => [
            'numeric' => 'Diskon harus berupa angka.',
            'min' => 'Diskon tidak boleh kurang dari 0.',
            'max' => 'Diskon tidak boleh lebih dari 100.',
        ],
    ],

    'attributes' => [
        'customer_id' => 'Customer',
        'supplier_id' => 'Supplier',
        'order_note_id' => 'Surat pesanan',
        'sales_invoice_id' => 'Faktur',
        'invoice_date' => 'Tanggal faktur',
        'return_date' => 'Tanggal retur',
        'delivery_date' => 'Tanggal surat jalan',
        'note_date' => 'Tanggal surat pesanan',
        'transaction_date' => 'Tanggal transaksi',
        'payment_date' => 'Tanggal pembayaran',
        'due_date' => 'Jatuh tempo',
        'semester_period' => 'Semester',
        'payment_method' => 'Metode pembayaran',
        'notes' => 'Catatan',
        'name' => 'Nama',
        'city' => 'Kota',
        'address' => 'Alamat',
        'phone' => 'No HP 1',
        'phone_secondary' => 'No HP 2',
        'level_id' => 'Level customer',
        'items' => 'Daftar barang',
        'items.*.product_id' => 'Barang',
        'items.*.quantity' => 'Qty',
        'items.*.unit_price' => 'Harga',
        'items.*.discount' => 'Diskon',
    ],
];
