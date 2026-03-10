table { width: 100%; border-collapse: collapse; margin-top: 12px; }
th, td { border: 1px solid #111; padding: 4px; vertical-align: top; }
th { font-size: 10px; text-align: center; }
td { text-align: left; }
.line-items th:first-child,
.line-items td:first-child { text-align: center; }
.line-items td.num,
td.num,
.total-box td.num,
.qty-box td.num { text-align: right !important; white-space: nowrap; }
.qty-box td:last-child,
.total-box td:last-child { text-align: right !important; white-space: nowrap; }
@media print {
    th, td { padding: 3px; }
}
