/**
 * Captura automatizada de pantallas para el Manual de Usuario SAEC.
 *
 * IMPORTANTE — credenciales: este script NUNCA contiene ni almacena la
 * contraseña. La lee exclusivamente de la variable de entorno
 * MOODLE_PASSWORD, que debes proporcionar tú mismo al ejecutarlo:
 *
 *   MOODLE_PASSWORD="tu-contraseña" node manual/capture_screenshots.js
 *
 * Opcional: MOODLE_BASE_URL (por defecto http://localhost:8080).
 *
 * Requiere que ya se haya ejecutado una vez:
 *   npm install playwright
 *   npx playwright install chromium
 * (ambos pasos ya se realizaron en este entorno — no requieren credenciales).
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = (process.env.MOODLE_BASE_URL || 'http://localhost:8080').replace(/\/+$/, '');
const PASSWORD = process.env.MOODLE_PASSWORD;

if (!PASSWORD) {
    console.error('ERROR: define la variable de entorno MOODLE_PASSWORD antes de ejecutar este script.');
    console.error('Ejemplo:  MOODLE_PASSWORD="tu-contraseña" node manual/capture_screenshots.js');
    process.exit(1);
}

const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');
const VIEWPORT = { width: 1920, height: 1080 };

const PUBLIC_SHOTS = [
    { file: '01_publico_landing.png', route: '/' },
    { file: '02_publico_login.png', route: '/login/index.php' },
    { file: '03_publico_recuperacion_contrasena.png', route: '/theme/saec/pages/forgot_password.php' },
];

const ROLES = [
    {
        name: 'alumno',
        username: 'alumno_top1',
        shots: [
            { file: '04_alumno_01_panel_principal.png', route: '/my/' },
            { file: '05_alumno_02_mis_cursos.png', route: '/my/courses.php' },
            { file: '06_alumno_03_mis_tareas.png', route: '/theme/saec/pages/student_tasks.php' },
            { file: '07_alumno_04_rendimiento_academico.png', route: '/grade/report/overview/index.php' },
            { file: '08_alumno_05_mochila_insignias.png', route: '/badges/mybadges.php' },
            { file: '09_alumno_06_preferencias_cuenta.png', route: '/user/preferences.php' },
        ],
    },
    {
        name: 'docente',
        username: 'maestro_b1',
        shots: [
            { file: '10_docente_01_panel_principal.png', route: '/my/' },
            { file: '11_docente_02_mis_cursos.png', route: '/my/courses.php' },
            { file: '12_docente_03_estudiantes_progreso.png', route: '/grade/report/user/index.php' },
            { file: '13_docente_04_calificador_integral.png', route: '/grade/report/grader/index.php' },
            { file: '14_docente_05_control_asistencia.png', route: '/theme/saec/pages/attendance_hub.php' },
            { file: '15_docente_06_edicion_curso.png', route: '/course/view.php?id=2' },
        ],
    },
    {
        name: 'admin',
        username: 'admin',
        shots: [
            { file: '16_admin_01_panel_principal.png', route: '/my/' },
            { file: '17_admin_02_catalogo_global.png', route: '/my/courses.php' },
            { file: '18_admin_03_directorio_usuarios.png', route: '/admin/user.php' },
            { file: '19_admin_04_reportes_auditoria.png', route: '/report/log/index.php' },
            { file: '20_admin_05_centro_administracion.png', route: '/theme/saec/pages/admin_hub.php' },
            { file: '21_admin_06_preferencias_cuenta.png', route: '/user/preferences.php' },
        ],
    },
];

async function shootPage(page, url, outfile, label, results) {
    process.stdout.write(`  - ${label} (${url}) ... `);
    try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
        // Deja que el JS tardío (drawers, gráficas de barras, popovers) termine de asentarse.
        await page.waitForTimeout(700);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, outfile) });
        console.log('OK');
        results.push({ file: outfile, status: 'OK' });
    } catch (err) {
        console.log(`FALLÓ (${err.message.split('\n')[0]})`);
        results.push({ file: outfile, status: 'ERROR', error: err.message });
    }
}

async function login(page, username) {
    await page.goto(`${BASE_URL}/login/index.php`, { waitUntil: 'networkidle' });
    await page.fill('#username', username);
    await page.fill('#password', PASSWORD);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('#loginbtn'),
    ]);
    if (page.url().includes('/login/index.php')) {
        throw new Error(
            `El login para "${username}" no redirigió fuera de /login/index.php — ` +
            `revisa que el usuario exista y que MOODLE_PASSWORD sea correcta para los 3 roles.`
        );
    }
}

(async () => {
    if (!fs.existsSync(SCREENSHOT_DIR)) {
        fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    }

    const browser = await chromium.launch();
    const results = [];

    try {
        console.log(`\nBase URL: ${BASE_URL}`);
        console.log('\n[Público] Capturando pantallas sin autenticación...');
        const publicContext = await browser.newContext({ viewport: VIEWPORT });
        const publicPage = await publicContext.newPage();
        for (const shot of PUBLIC_SHOTS) {
            await shootPage(publicPage, `${BASE_URL}${shot.route}`, shot.file, shot.route, results);
        }
        await publicContext.close();

        for (const role of ROLES) {
            console.log(`\n[${role.name}] Iniciando sesión como "${role.username}"...`);
            const context = await browser.newContext({ viewport: VIEWPORT });
            const page = await context.newPage();
            try {
                await login(page, role.username);
                console.log(`[${role.name}] Sesión iniciada. Capturando ${role.shots.length} pantallas...`);
                for (const shot of role.shots) {
                    await shootPage(page, `${BASE_URL}${shot.route}`, shot.file, shot.route, results);
                }
            } catch (err) {
                console.log(`[${role.name}] ERROR DE LOGIN: ${err.message}`);
                for (const shot of role.shots) {
                    results.push({ file: shot.file, status: 'ERROR', error: 'login falló, no se intentó' });
                }
            } finally {
                await context.close();
            }
        }
    } finally {
        await browser.close();
    }

    const ok = results.filter((r) => r.status === 'OK');
    const failed = results.filter((r) => r.status !== 'OK');

    console.log(`\n===== RESUMEN =====`);
    console.log(`Capturas exitosas: ${ok.length}/${results.length}`);
    if (failed.length) {
        console.log(`Fallidas:`);
        failed.forEach((f) => console.log(`  - ${f.file}: ${f.error}`));
        process.exitCode = 1;
    } else {
        console.log('Todas las capturas se completaron correctamente.');
    }
})();
