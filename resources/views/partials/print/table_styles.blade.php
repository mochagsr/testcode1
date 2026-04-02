table { width: 100%; border-collapse: collapse; margin-top: 12px; }
th, td { border: 1px solid #111; padding: 5px; vertical-align: top; font-size: 12px; font-weight: 600; }
th { font-size: 12px; text-align: center; font-weight: 800; }
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
    th, td { padding: 4px; font-size: 12px; font-weight: 600; }
}
