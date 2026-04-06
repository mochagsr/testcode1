import fs from 'fs/promises';
import path from 'path';
import { execFileSync } from 'child_process';
import { chromium } from 'playwright';

const root = process.cwd();
const outputDir = path.join(root, 'storage', 'app', 'qa', 'print-smoke');

const baseUrl = process.env.MANUAL_BASE_URL || process.env.APP_URL || 'http://tespgpos.test';
const adminLogin = process.env.MANUAL_ADMIN_LOGIN || 'admin';
const adminPassword = process.env.MANUAL_ADMIN_PASSWORD || '@Passwordadmin123#';

function phpJson(code) {
  const bootstrapScript = `require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make('Illuminate\\\\Contracts\\\\Console\\\\Kernel');
$kernel->bootstrap();
${code}
`;
  const encoded = Buffer.from(bootstrapScript, 'utf8').toString('base64');

  const raw = execFileSync('php', ['-r', `eval(base64_decode('${encoded}'));`], {
    cwd: root,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  return JSON.parse(raw.replace(/^\uFEFF/, '').trim());
}

async function waitForUi(page) {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(350);
}

async function login(page) {
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="login"]', adminLogin);
  await page.fill('input[name="password"]', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  await waitForUi(page);
}

async function captureHeader(page, fileName) {
  const output = path.join(outputDir, fileName);
  const selectors = [
    '.company-head',
    '.report-header',
    '.header',
    '.header-wrap',
    '.print-header',
    '.doc-header',
    '.container',
  ];

  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    const count = await locator.count().catch(() => 0);
    if (count > 0) {
      await locator.screenshot({ path: output });
      return output;
    }
  }

  await page.screenshot({ path: output, fullPage: false });
  return output;
}

async function inspectLogo(page) {
  return page.evaluate(() => {
    const images = Array.from(document.querySelectorAll('img[alt="Logo"], .company-logo img, .company-logo-img, img.logo'));
    const firstImage = images[0] ?? null;
    const fallback = document.querySelector('.logo-fallback');

    return {
      imageCount: images.length,
      imageLoaded: firstImage ? Boolean(firstImage.complete && firstImage.naturalWidth > 0 && firstImage.naturalHeight > 0) : false,
      imageSrc: firstImage ? (firstImage.getAttribute('src') || '') : '',
      fallbackText: fallback ? (fallback.textContent || '').trim() : '',
      bodyTextSnippet: (document.body?.innerText || '').trim().slice(0, 220),
    };
  });
}

function buildTargets(fixtures) {
  const semester = fixtures.semester || 'S2-2526';
  const customerId = fixtures.customerId;
  const targets = [
    {
      key: 'sales-invoice-print',
      label: 'Faktur Penjualan',
      path: fixtures.salesInvoiceId ? `/sales-invoices/${fixtures.salesInvoiceId}/print` : null,
    },
    {
      key: 'order-note-print',
      label: 'Surat Pesanan',
      path: fixtures.orderNoteId ? `/order-notes/${fixtures.orderNoteId}/print` : null,
    },
    {
      key: 'delivery-note-print',
      label: 'Surat Jalan',
      path: fixtures.deliveryNoteId ? `/delivery-notes/${fixtures.deliveryNoteId}/print` : null,
    },
    {
      key: 'outgoing-print',
      label: 'Tanda Terima Barang',
      path: fixtures.outgoingTransactionId ? `/outgoing-transactions/${fixtures.outgoingTransactionId}/print` : null,
    },
    {
      key: 'receivable-payment-print',
      label: 'Bayar Piutang',
      path: fixtures.receivablePaymentId ? `/receivable-payments/${fixtures.receivablePaymentId}/print` : null,
    },
    {
      key: 'customer-bill-print',
      label: 'Tagihan Customer',
      path: customerId ? `/receivables/customer/${customerId}/print-bill` : null,
    },
    {
      key: 'products-report-print',
      label: 'Report Barang',
      path: '/products/print',
    },
    {
      key: 'outgoing-report-print',
      label: 'Report Transaksi Keluar',
      path: `/reports/outgoing_transactions/print?semester=${encodeURIComponent(semester)}`,
    },
    {
      key: 'receivable-report-print',
      label: 'Report Piutang',
      path: customerId
        ? `/reports/receivables/print?semester=${encodeURIComponent(semester)}&customer_id=${customerId}`
        : `/reports/receivables/print?semester=${encodeURIComponent(semester)}`,
    },
  ];

  return targets;
}

function getFixtures() {
  return phpJson(`
echo json_encode([
  'companyLogoResolved' => \\App\\Support\\PrintLogoDataUri::resolveForPrint(\\App\\Models\\AppSetting::getValue('company_logo_path'), true) !== null,
  'customerId' => optional(\\App\\Models\\Customer::query()->orderBy('id')->first())->id,
  'salesInvoiceId' => optional(\\App\\Models\\SalesInvoice::query()->orderBy('id')->first())->id,
  'orderNoteId' => optional(\\App\\Models\\OrderNote::query()->orderBy('id')->first())->id,
  'deliveryNoteId' => optional(\\App\\Models\\DeliveryNote::query()->orderBy('id')->first())->id,
  'outgoingTransactionId' => optional(\\App\\Models\\OutgoingTransaction::query()->orderBy('id')->first())->id,
  'receivablePaymentId' => optional(\\App\\Models\\ReceivablePayment::query()->orderBy('id')->first())->id,
  'supplierPaymentId' => optional(\\App\\Models\\SupplierPayment::query()->orderBy('id')->first())->id,
  'schoolBulkTransactionId' => optional(\\App\\Models\\SchoolBulkTransaction::query()->orderBy('id')->first())->id,
  'deliveryTripId' => optional(\\App\\Models\\DeliveryTrip::query()->orderBy('id')->first())->id,
  'semester' => optional(\\App\\Models\\SalesInvoice::query()->whereNotNull('semester_period')->orderBy('id')->first())->semester_period
    ?? optional(\\App\\Models\\OutgoingTransaction::query()->whereNotNull('semester_period')->orderBy('id')->first())->semester_period
    ?? 'S2-2526',
]);
  `);
}

async function main() {
  await fs.mkdir(outputDir, { recursive: true });

  const fixtures = getFixtures();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1200 } });
  const page = await context.newPage();

  await login(page);
  console.log(`Login berhasil ke ${baseUrl}`);

  const targets = buildTargets(fixtures);
  const results = [];

  for (const target of targets) {
    console.log(`Cek: ${target.label}`);
    if (!target.path) {
      results.push({
        route: target.label,
        status: 'SKIP',
        detail: 'Data dokumen belum ada.',
        screenshot: '-',
      });
      continue;
    }

    const url = `${baseUrl}${target.path}`;

    try {
      const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 10000 });
      await waitForUi(page);
      const httpStatus = response?.status() ?? 0;
      const logo = await inspectLogo(page);
      const screenshotPath = await captureHeader(page, `${target.key}.png`);

      let status = httpStatus === 200 ? 'OK' : 'FAIL';
      let detail = `HTTP ${httpStatus}`;

      if (fixtures.companyLogoResolved) {
        if (!logo.imageLoaded) {
          status = 'FAIL';
          detail = `HTTP ${httpStatus} - logo tidak termuat`;
        } else {
          detail = `HTTP ${httpStatus} - logo OK`;
        }
      } else if (logo.imageLoaded) {
        detail = `HTTP ${httpStatus} - logo OK`;
      } else if (logo.fallbackText !== '') {
        detail = `HTTP ${httpStatus} - fallback "${logo.fallbackText}"`;
      } else {
        detail = `HTTP ${httpStatus} - tanpa logo/fallback`;
      }

      results.push({
        route: target.label,
        status,
        detail,
        screenshot: path.relative(root, screenshotPath).replaceAll('\\', '/'),
      });
    } catch (error) {
      const screenshotPath = await captureHeader(page, `${target.key}-error.png`).catch(() => null);
      results.push({
        route: target.label,
        status: 'FAIL',
        detail: String(error.message || error).slice(0, 180),
        screenshot: screenshotPath ? path.relative(root, screenshotPath).replaceAll('\\', '/') : '-',
      });
    }
  }

  await browser.close();

  console.log('== print visual smoke ==');
  console.table(results);

  const failures = results.filter((item) => item.status === 'FAIL');
  if (failures.length > 0) {
    process.exitCode = 1;
    console.error(`Visual smoke gagal pada ${failures.length} halaman print.`);
  } else {
    console.log('Visual smoke selesai tanpa FAIL.');
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
