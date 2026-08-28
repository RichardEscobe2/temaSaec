/**
 * Re-captura puntual de 2 pantallas del capítulo Docente que actualmente
 * muestran el error fatal "missingparam" de Moodle: ambas rutas exigen un
 * parámetro de curso (?id=) que el script original de captura no incluyó.
 *
 * Mismo contrato de credenciales que capture_screenshots.js /
 * recapture_admin.js: lee MOODLE_PASSWORD del entorno, nunca la contiene
 * en el código.
 *
 *   MOODLE_PASSWORD="tu-contraseña" node manual/recapture_teacher_grades.js
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
    { file: '12_docente_03_estudiantes_progreso.png', route: '/grade/report/user/index.php?id=2' },
    { file: '13_docente_04_calificador_integral.png', route: '/grade/report/grader/index.php?id=2' },
];

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: VIEWPORT });
    const page = await context.newPage();

    await page.goto(`${BASE_URL}/login/index.php`, { waitUntil: 'networkidle' });
    await page.fill('#username', 'maestro_b1');
    await page.fill('#password', PASSWORD);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#loginbtn'),
    ]);
    if (page.url().includes('/login/index.php')) {
        console.error('ERROR: el login para "maestro_b1" no redirigió — revisa MOODLE_PASSWORD.');
        await browser.close();
        process.exit(1);
    }

    let hadError = false;
    for (const shot of SHOTS) {
        const url = `${BASE_URL}${shot.route}`;
        process.stdout.write(`  - ${shot.route} ... `);
        await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(700);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, shot.file) });

        // Verificación básica de que no se guardó otra pantalla de error fatal.
        const bodyText = await page.evaluate(() => document.body.innerText);
        if (/Error code:\s*\w+/.test(bodyText) || bodyText.includes('parámetro necesario')) {
            console.log('GUARDADA, PERO AÚN MUESTRA UN ERROR — revisar manualmente.');
            hadError = true;
        } else {
            console.log('OK');
        }
    }

    await browser.close();
    console.log(hadError ? '\nTerminado con advertencias.' : '\nListo, sin errores detectados.');
    process.exitCode = hadError ? 1 : 0;
})();
