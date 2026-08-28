/**
 * Re-captura puntual de las 2 pantallas de Admin afectadas por el desfase
 * de versión ya corregido (theme_saec redirigía a /admin/index.php en vez
 * de /my/). Mismo contrato de credenciales que capture_screenshots.js:
 * lee MOODLE_PASSWORD del entorno, nunca la contiene en el código.
 *
 *   MOODLE_PASSWORD="tu-contraseña" node manual/recapture_admin.js
 */

const { chromium } = require('playwright');
const path = require('path');

const BASE_URL = (process.env.MOODLE_BASE_URL || 'http://localhost:8080').replace(/\/+$/, '');
const PASSWORD = process.env.MOODLE_PASSWORD;

if (!PASSWORD) {
    console.error('ERROR: define MOODLE_PASSWORD antes de ejecutar este script.');
    process.exit(1);
}

const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');
const VIEWPORT = { width: 1920, height: 1080 };

const SHOTS = [
    { file: '16_admin_01_panel_principal.png', route: '/my/' },
    { file: '17_admin_02_catalogo_global.png', route: '/my/courses.php' },
];

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: VIEWPORT });
    const page = await context.newPage();

    await page.goto(`${BASE_URL}/login/index.php`, { waitUntil: 'networkidle' });
    await page.fill('#username', 'admin');
    await page.fill('#password', PASSWORD);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#loginbtn'),
    ]);

    for (const shot of SHOTS) {
        const url = `${BASE_URL}${shot.route}`;
        process.stdout.write(`  - ${shot.route} ... `);
        await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(700);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, shot.file) });
        console.log('OK');
    }

    await browser.close();
    console.log('\nListo.');
})();
