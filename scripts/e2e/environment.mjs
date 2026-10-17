import { existsSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
// Compilar las vistas en un directorio exclusivo evita reutilizar cachés operativas.
const compiledViews = path.join(root, 'storage/framework/views-e2e');
mkdirSync(compiledViews, { recursive: true });
const file = path.join(root, '.env.e2e');
if (existsSync(file)) process.loadEnvFile(file);
const database = process.env.E2E_DB_DATABASE || 'lunnys_e2e_test';
if (!/^[a-zA-Z0-9_]+_e2e_test$/.test(database)) {
    throw new Error('La base exclusiva de Playwright debe terminar en _e2e_test.');
}
const port = Number(process.env.E2E_PORT || 8011);
if (!Number.isInteger(port) || port < 1024 || port > 65535) throw new Error('E2E_PORT no es válido.');
export const baseURL = `http://127.0.0.1:${port}`;
export const php = process.env.E2E_PHP_BIN || 'php';
export const dniMode = process.env.E2E_DNI_MODE || 'simulado';
export const aiMode = process.env.E2E_AI_MODE || 'deshabilitado';
if (!['deshabilitado', 'real'].includes(aiMode)) throw new Error('E2E_AI_MODE debe ser deshabilitado o real.');
if (aiMode === 'real' && (!process.env.E2E_GEMINI_API_KEY || !process.env.E2E_GEMINI_MODEL)) throw new Error('IA real requiere E2E_GEMINI_API_KEY y E2E_GEMINI_MODEL explícitos.');
export const driveMode = process.env.E2E_DRIVE_MODE || 'deshabilitado';
if (!['deshabilitado', 'real'].includes(driveMode)) throw new Error('E2E_DRIVE_MODE debe ser deshabilitado o real.');
const driveValue = key => driveMode === 'real' ? process.env[`E2E_INSTALLATION_DRIVE_${key}`] || '' : '';
if (driveMode === 'real' && (!driveValue('FOLDER_ID') || !(driveValue('CREDENTIALS_PATH') || (driveValue('CLIENT_ID') && driveValue('CLIENT_SECRET') && driveValue('REFRESH_TOKEN'))))) {
    throw new Error('Drive real requiere credenciales E2E y una carpeta exclusiva de pruebas.');
}
if (!['simulado', 'real'].includes(dniMode)) throw new Error('E2E_DNI_MODE debe ser simulado o real.');
export const runId = process.env.E2E_RUN_ID ||= new Date().toISOString().replace(/[:.]/g, '-');
export const runDir = path.join(root, 'artifacts/e2e', runId);
// Sobrescribe cualquier configuración operativa heredada. No modifica .env.
Object.assign(process.env, {
    APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: baseURL,
    APP_KEY: 'base64:' + Buffer.from('0123456789abcdef0123456789abcdef').toString('base64'),
    APP_CONFIG_CACHE: path.join(root, 'storage/framework/e2e-no-config.php'),
    APP_ROUTES_CACHE: path.join(root, 'storage/framework/e2e-no-routes.php'),
    VIEW_COMPILED_PATH: compiledViews,
    DB_CONNECTION: 'mysql', DB_URL: '', DB_HOST: process.env.E2E_DB_HOST || '127.0.0.1',
    DB_PORT: process.env.E2E_DB_PORT || '3306', DB_DATABASE: database,
    DB_USERNAME: process.env.E2E_DB_USERNAME || 'root', DB_PASSWORD: process.env.E2E_DB_PASSWORD || '',
    SESSION_DRIVER: 'file', SESSION_COOKIE: 'lunnys_e2e', SESSION_DOMAIN: 'null',
    SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array', ACCESS_TWO_FACTOR_ENABLED: 'false',
    GEMINI_API_KEY: aiMode === 'real' ? process.env.E2E_GEMINI_API_KEY : '',
    GEMINI_MODEL: process.env.E2E_GEMINI_MODEL || 'gemini-2.0-flash-exp',
    SIGNATURE_PYTHON: process.env.E2E_SIGNATURE_PYTHON || 'python',
    INSTALLATION_DRIVE_CLIENT_ID: driveValue('CLIENT_ID'), INSTALLATION_DRIVE_CLIENT_SECRET: driveValue('CLIENT_SECRET'),
    INSTALLATION_DRIVE_REFRESH_TOKEN: driveValue('REFRESH_TOKEN'), INSTALLATION_DRIVE_CREDENTIALS_PATH: driveValue('CREDENTIALS_PATH'), INSTALLATION_DRIVE_FOLDER_ID: driveValue('FOLDER_ID'),
    APIPERU_API_KEY: dniMode === 'real' ? process.env.E2E_APIPERU_API_KEY || '' : '',
});
