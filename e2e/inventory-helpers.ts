import { type Page } from '@playwright/test';
import { expect } from './helpers';

export async function fillEquipment(page: Page, data: Record<string, any>) {
    for (const [key, value] of Object.entries(data)) {
        if (['estado', 'condicion'].includes(key)) await page.locator(`#${key}`).selectOption(String(value));
        else await page.locator(`#${key}`).fill(String(value));
    }
}
export async function fillMaterial(page: Page, data: Record<string, any>) {
    for (const [key, value] of Object.entries(data)) {
        if (['estado', 'unidad_base'].includes(key)) await page.locator(`#${key}`).selectOption(String(value));
        else await page.locator(`#${key}`).fill(String(value));
    }
}
export async function inventoryRow(page: Page, module: 'equipments' | 'materials', search: string) {
    await page.goto(`/${module}`);
    await page.locator('#search').fill(search);
    await page.getByRole('button', { name: /Aplicar(?: filtros)?$/ }).click();
    const row = page.locator('tbody tr').filter({ hasText: search });
    await expect(row).toHaveCount(1);
    return row;
}
export async function bypassNativeValidation(page: Page) {
    // Verifica el servidor además del límite nativo de cantidad.
    await page.locator('form.standard-form').evaluate((form: HTMLFormElement) => { form.noValidate = true; });
}
