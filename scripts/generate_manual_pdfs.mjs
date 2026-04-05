import fs from 'fs/promises';
import path from 'path';
import { marked } from 'marked';
import { chromium } from 'playwright';

const root = process.cwd();
const docsDir = path.join(root, 'docs');
const assetsDir = path.join(docsDir, 'assets', 'manuals');

const baseUrl = process.env.MANUAL_BASE_URL || 'http://tespgpos.test';
const adminLogin = process.env.MANUAL_ADMIN_LOGIN || 'admin';
const adminEmail = process.env.MANUAL_ADMIN_EMAIL || 'admin@pgpos.local';
const adminPassword = process.env.MANUAL_ADMIN_PASSWORD || '@Passwordadmin123#';
const userLogin = process.env.MANUAL_USER_LOGIN || 'user';
const userEmail = process.env.MANUAL_USER_EMAIL || 'user@pgpos.local';
const userPassword = process.env.MANUAL_USER_PASSWORD || '@Passworduser123#';

const waitForUi = async (page) => {
  await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(1200);
};

async function login(page, loginIdentifier, password) {
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="login"]', loginIdentifier);
  await page.fill('input[name="password"]', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  await waitForUi(page);
}

async function ensureLoggedIn(page, loginIdentifier, password, targetPath) {
  await page.goto(`${baseUrl}${targetPath}`, { waitUntil: 'domcontentloaded' });
  await waitForUi(page);
  const needsLogin = await page.locator('input[name="login"]').count().catch(() => 0);
  if (needsLogin > 0) {
    await page.fill('input[name="login"]', loginIdentifier);
    await page.fill('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }).catch(() => {}),
      page.click('button[type="submit"]'),
    ]);
    await waitForUi(page);
    await page.goto(`${baseUrl}${targetPath}`, { waitUntil: 'domcontentloaded' });
    await waitForUi(page);
  }
}

async function shot(page, fileName) {
  const output = path.join(assetsDir, fileName);
  await page.screenshot({ path: output, fullPage: true });
  return `assets/manuals/${fileName}`;
}

async function shotLocator(locator, fileName) {
  const output = path.join(assetsDir, fileName);
  await locator.screenshot({ path: output });
  return `assets/manuals/${fileName}`;
}

async function selectIfExists(page, selector, value) {
  const optionExists = await page.$eval(
    selector,
    (element, expectedValue) => Array.from(element.querySelectorAll('option')).some((option) => option.value === expectedValue),
    value,
  ).catch(() => false);

  if (optionExists) {
    await page.selectOption(selector, value);
    return true;
  }

  return false;
}

async function captureFirstOrderNoteDetail(page, fileName, loginIdentifier, password) {
  await ensureLoggedIn(page, loginIdentifier, password, '/order-notes');
  const firstLink = page.locator('.list-doc-link').first();
  const hasLink = await firstLink.count().catch(() => 0);

  if (hasLink > 0) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }).catch(() => {}),
      firstLink.click(),
    ]);
    await waitForUi(page);
  }

  await shot(page, fileName);
}

async function captureInvoiceValidationExamples(page, prefix, loginIdentifier, password) {
  await ensureLoggedIn(page, loginIdentifier, password, '/sales-invoices/create');

  const customerInput = page.locator('#customer-search');
  const invoiceDateInput = page.locator('#invoice-date');

  await customerInput.fill('Anto Tidak Ada');
  await invoiceDateInput.click();
  await waitForUi(page);
  const customerBlock = customerInput.locator('xpath=ancestor::div[contains(@class,"col-12")][1]');
  await shotLocator(customerBlock, `${prefix}-customer-error.png`);

  await ensureLoggedIn(page, loginIdentifier, password, '/sales-invoices/create');
  await page.evaluate(() => {
    if (typeof customers === 'undefined' || !Array.isArray(customers) || customers.length === 0) {
      return;
    }
    const selected = customers[0];
    const customerSearch = document.getElementById('customer-search');
    const customerId = document.getElementById('customer-id');
    const customerError = document.getElementById('customer-search-error');
    if (customerSearch) {
      customerSearch.value = `${selected.name} (${selected.city || '-'})`;
    }
    if (customerId) {
      customerId.value = String(selected.id);
    }
    if (customerError) {
      customerError.textContent = '';
      customerError.style.display = '';
    }
  });
  await waitForUi(page);

  let itemRows = await page.locator('#items-table tbody tr').count().catch(() => 0);
  if (itemRows === 0) {
    await page.click('#add-item');
    await waitForUi(page);
    itemRows = await page.locator('#items-table tbody tr').count().catch(() => 0);
  }

  if (itemRows > 0) {
    const firstProductInput = page.locator('#items-table tbody tr').first().locator('.product-search');
    const firstQtyInput = page.locator('#items-table tbody tr').first().locator('.qty');
    await firstProductInput.fill('Barang Tidak Ada');
    await firstQtyInput.click();
    await waitForUi(page);
    const productCell = page.locator('#items-table tbody tr').first().locator('td').first();
    await shotLocator(productCell, `${prefix}-product-error.png`);
  }
}

function wrapHtml(title, bodyHtml) {
  return `<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>${title}</title>
  <style>
    @page {
      margin: 16mm 12mm 18mm 12mm;
      size: A4;
    }
    body {
      font-family: Arial, Helvetica, sans-serif;
      color:#111827;
      font-size: 12px;
      line-height: 1.6;
    }
    .cover {
      min-height: 240px;
      padding: 18px 20px;
      border: 2px solid #1d4ed8;
      border-radius: 16px;
      background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
      margin-bottom: 18px;
    }
    .cover-kicker {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 999px;
      background: #dbeafe;
      color: #1d4ed8;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      margin-bottom: 14px;
    }
    h1 {
      font-size: 28px;
      margin: 0 0 8px;
      color: #0f172a;
    }
    .meta {
      color:#6b7280;
      font-size: 11px;
      margin-bottom: 20px;
    }
    .toc {
      page-break-after: always;
      border: 1px solid #d1d5db;
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 18px;
      background: #fcfcfd;
    }
    .toc h2 {
      border: none;
      padding-bottom: 0;
      margin-top: 0;
    }
    .toc ul {
      margin: 0;
      padding-left: 18px;
    }
    .toc li + li {
      margin-top: 4px;
    }
    h2 {
      font-size: 19px;
      margin: 24px 0 10px;
      border-bottom: 1px solid #d1d5db;
      padding-bottom: 4px;
      color: #0f172a;
      page-break-after: avoid;
    }
    h3 {
      font-size: 15px;
      margin: 18px 0 6px;
      color: #1f2937;
      page-break-after: avoid;
    }
    p, ul, ol {
      margin: 8px 0;
    }
    code {
      background:#f3f4f6;
      padding:2px 4px;
      border-radius:4px;
      font-size: 11px;
    }
    pre {
      background:#f8fbff;
      color:#0f172a;
      padding:14px 16px;
      border-radius:10px;
      border: 2px solid #bfdbfe;
      overflow:auto;
      white-space:pre-wrap;
      break-inside: avoid;
      page-break-inside: avoid;
      font-size: 12px;
      line-height: 1.8;
      font-weight: 700;
    }
    .flowchart {
      margin: 12px 0 18px;
      padding: 14px;
      border: 1px solid #bfdbfe;
      border-radius: 12px;
      background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
      break-inside: avoid;
      page-break-inside: avoid;
    }
    .flowchart-title {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #1d4ed8;
      margin-bottom: 10px;
    }
    .flow-step {
      display: block;
      padding: 10px 12px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      background: #ffffff;
      color: #0f172a;
      font-size: 12px;
      font-weight: 700;
      line-height: 1.5;
      margin: 0;
    }
    .flow-arrow {
      text-align: center;
      color: #2563eb;
      font-size: 18px;
      font-weight: 700;
      line-height: 1;
      margin: 6px 0;
    }
    img {
      max-width: 100%;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      margin: 0;
      display: block;
    }
    figure {
      margin: 12px 0 18px;
      break-inside: avoid;
      page-break-inside: avoid;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 10px;
    }
    figcaption {
      margin-top: 8px;
      font-size: 11px;
      color: #6b7280;
      text-align: center;
    }
    table {
      width:100%;
      border-collapse: collapse;
      margin: 10px 0;
      break-inside: avoid;
      page-break-inside: avoid;
    }
    th, td {
      border:1px solid #d1d5db;
      padding:6px 8px;
      text-align:left;
      vertical-align: top;
    }
    th {
      background: #f3f4f6;
    }
    blockquote {
      margin: 10px 0;
      padding: 10px 12px;
      border-left: 3px solid #93c5fd;
      color: #374151;
      background: #f8fbff;
      border-radius: 6px;
    }
    .note {
      background:#eff6ff;
      border:1px solid #bfdbfe;
      padding:10px 12px;
      border-radius:8px;
      margin:12px 0;
    }
    .footer-note {
      margin-top: 20px;
      font-size: 10px;
      color: #6b7280;
      text-align: right;
    }
  </style>
</head>
<body>
  ${bodyHtml}
</body>
</html>`;
}

function buildToc(markdown) {
  const lines = markdown.split(/\r?\n/);
  const entries = [];
  for (const line of lines) {
    const match = line.match(/^##\s+(.+)$/);
    if (match) entries.push(match[1].trim());
  }
  if (entries.length === 0) return '';
  const list = entries.map((item) => `<li>${item}</li>`).join('');
  return `<!doctype html>
    <div class="toc">
      <h2>Daftar Isi</h2>
      <ul>${list}</ul>
    </div>`;
}

async function imageDataUrlFromDocsRelative(src) {
  const absolute = path.join(docsDir, src);
  const ext = path.extname(absolute).toLowerCase() === '.jpg' || path.extname(absolute).toLowerCase() === '.jpeg'
    ? 'jpeg'
    : 'png';
  const bytes = await fs.readFile(absolute);
  return `data:image/${ext};base64,${bytes.toString('base64')}`;
}

async function enhanceHtml(markdownHtml) {
  let result = markdownHtml.replace(
    /<pre><code class="language-text">([\s\S]*?)<\/code><\/pre>/g,
    (_match, codeContent) => {
      const decoded = codeContent
        .replaceAll('&amp;', '&')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .trim();
      if (!decoded.includes('->')) {
        return `<pre><code>${decoded}</code></pre>`;
      }

      const steps = decoded
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .map((line) => line.replace(/^->\s*/, '').trim());

      if (steps.length === 0) {
        return '';
      }

      const htmlSteps = steps.map((step, index) => {
        const safeStep = step
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;');
        const arrow = index < steps.length - 1 ? '<div class="flow-arrow">↓</div>' : '';
        return `<div class="flow-step">${safeStep}</div>${arrow}`;
      }).join('');

      return `<div class="flowchart"><div class="flowchart-title">Flowchart</div>${htmlSteps}</div>`;
    },
  );

  const matches = [...result.matchAll(/<p><img src="([^"]+)" alt="([^"]*)"><\/p>/g)];
  for (const match of matches) {
    const [full, src, alt] = match;
    const dataUrl = await imageDataUrlFromDocsRelative(src);
    const replacement = `<figure><img src="${dataUrl}" alt="${alt}">${alt ? `<figcaption>${alt}</figcaption>` : ''}</figure>`;
    result = result.replace(full, replacement);
  }
  return result.replace(
    /<p><img src="([^"]+)" alt="([^"]*)"><\/p>/g,
    (_match, _src, alt) => `<figure><div style="padding:20px;border:1px dashed #cbd5e1;color:#64748b;">Gambar tidak tersedia${alt ? `: ${alt}` : ''}</div></figure>`,
  );
}

async function renderPdf(browser, markdownFile, pdfFile, title) {
  const markdown = await fs.readFile(path.join(docsDir, markdownFile), 'utf8');
  const cover = `
    <div class="cover">
      <div class="cover-kicker">Panduan Internal PgPOS ERP</div>
      <h1>${title}</h1>
      <div class="meta">Dibuat otomatis dari dokumentasi aplikasi pada ${new Date().toLocaleString('id-ID')}</div>
      <div class="note">
        Dokumen ini disiapkan untuk onboarding, operasional harian, dan pelatihan staf. Gunakan bersama screenshot halaman aplikasi agar alurnya mudah diikuti.
      </div>
    </div>
  `;
  const toc = buildToc(markdown);
  const body = await enhanceHtml(marked.parse(markdown));
  const html = wrapHtml(title, `${cover}${toc}${body}<div class="footer-note">PgPOS ERP Manual</div>`);
  const page = await browser.newPage();
  await page.setContent(html, { waitUntil: 'load' });
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.evaluate(async () => {
    const imgs = Array.from(document.images);
    await Promise.all(imgs.map((img) => {
      if (img.complete) return Promise.resolve();
      return new Promise((resolve) => {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
      });
    }));
  });
  await page.pdf({
    path: path.join(docsDir, pdfFile),
    format: 'A4',
    printBackground: true,
    margin: { top: '16mm', right: '12mm', bottom: '16mm', left: '12mm' },
  });
  await page.close();
}

async function main() {
  await fs.mkdir(assetsDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });

  const adminContext = await browser.newContext({ viewport: { width: 1600, height: 980 } });
  const admin = await adminContext.newPage();
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/dashboard');
  await shot(admin, 'admin-dashboard.png');
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/sales-invoices');
  await shot(admin, 'admin-sales-invoices.png');
  await captureFirstOrderNoteDetail(admin, 'admin-order-notes.png', adminLogin || adminEmail, adminPassword);
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/receivables?customer_id=1&semester=S2-2526');
  await shot(admin, 'admin-receivables.png');
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/supplier-payables?supplier_id=1&year=2026&month=3');
  await shot(admin, 'admin-supplier-payables.png');
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/reports');
  await shot(admin, 'admin-reports.png');
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/users');
  await shot(admin, 'admin-users.png');
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/audit-logs');
  await shot(admin, 'admin-audit-logs.png');
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/ops-health');
  await shot(admin, 'admin-ops-health.png');
  await ensureLoggedIn(admin, adminLogin || adminEmail, adminPassword, '/settings');
  await shot(admin, 'admin-settings.png');
  await captureInvoiceValidationExamples(admin, 'admin-validation', adminLogin || adminEmail, adminPassword);

  const userContext = await browser.newContext({ viewport: { width: 1600, height: 980 } });
  const user = await userContext.newPage();
  await ensureLoggedIn(user, userLogin || userEmail, userPassword, '/dashboard');
  await shot(user, 'user-dashboard.png');
  await user.goto(`${baseUrl}/sales-invoices`, { waitUntil: 'domcontentloaded' });
  await waitForUi(user);
  await shot(user, 'user-sales-invoices.png');
  await captureFirstOrderNoteDetail(user, 'user-order-notes.png', userLogin || userEmail, userPassword);
  await user.goto(`${baseUrl}/receivables/global`, { waitUntil: 'domcontentloaded' });
  await waitForUi(user);
  await shot(user, 'user-receivables-global.png');
  await user.goto(`${baseUrl}/receivables/semester`, { waitUntil: 'domcontentloaded' });
  await waitForUi(user);
  await shot(user, 'user-receivables-semester.png');
  await user.goto(`${baseUrl}/supplier-payables`, { waitUntil: 'domcontentloaded' });
  await waitForUi(user);
  await shot(user, 'user-supplier-payables.png');
  await user.goto(`${baseUrl}/receivable-payments`, { waitUntil: 'domcontentloaded' });
  await waitForUi(user);
  await shot(user, 'user-receivable-payments.png');
  await user.goto(`${baseUrl}/outgoing-transactions`, { waitUntil: 'domcontentloaded' });
  await waitForUi(user);
  await shot(user, 'user-outgoing-transactions.png');
  await user.goto(`${baseUrl}/settings`, { waitUntil: 'domcontentloaded' });
  await waitForUi(user);
  await shot(user, 'user-settings.png');
  await captureInvoiceValidationExamples(user, 'user-validation', userLogin || userEmail, userPassword);

  await renderPdf(browser, 'USER_TRANSACTION_GUIDE.md', 'USER_TRANSACTION_GUIDE.pdf', 'Panduan User - Semua Jenis Transaksi');
  await renderPdf(browser, 'ADMIN_TRANSACTION_GUIDE.md', 'ADMIN_TRANSACTION_GUIDE.pdf', 'Panduan Admin - Semua Jenis Transaksi');

  await admin.close();
  await adminContext.close();
  await user.close();
  await userContext.close();
  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});

