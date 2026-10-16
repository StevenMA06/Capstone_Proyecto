import { execFileSync } from 'node:child_process';
import { test as base, expect, type Page, type TestInfo } from '@playwright/test';
import { php, root } from '../scripts/e2e/environment.mjs';

export { expect };
export const test = base.extend({
    page: async ({ page, browser }, use, info) => {
        info.annotations.push({ type: 'browser-version', description: browser.version() });
        await page.goto('/login');
        await page.getByLabel('Correo o usuario').fill('qa_e2e_gerencia');
        await page.getByLabel('Contraseña', { exact: true }).fill('PruebasLunnys2026');
        await page.getByRole('button', { name: 'Iniciar sesión' }).click();
        await expect(page).not.toHaveURL(/\/login|\/two-factor/);
        await use(page);
    },
});
export function fixture(command: string, arg?: string): any {
    return JSON.parse(execFileSync(php, ['scripts/e2e/fixture.php', command, ...(arg ? [arg] : [])], {
        cwd: root, encoding: 'utf8', env: process.env,
    }));
}
export async function evidence(page: Page, info: TestInfo, name: string, observed?: unknown) {
    const screenshot = await page.screenshot({ path: info.outputPath(`${name}.png`), fullPage: true });
    await info.attach(name, { body: screenshot, contentType: 'image/png' });
    if (observed) await info.attach(`${name}-datos`, { body: JSON.stringify(observed, null, 2), contentType: 'application/json' });
}
export async function fillClient(page: Page, data: Record<string, any>) {
    for (const [name, value] of Object.entries(data)) {
        const field = page.locator(`[name="${name}"]`);
        if (await field.count()) {
            if (name === 'tipo_documento') await field.selectOption(String(value));
            else await field.fill(String(value));
        }
    }
}
export async function fillPlan(page: Page, data: Record<string, any>) {
    for (const [name, value] of Object.entries(data)) await page.locator(`[name="${name}"]`).fill(String(value));
}
