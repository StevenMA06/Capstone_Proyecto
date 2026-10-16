import { type Page } from '@playwright/test';
import { expect } from './helpers';

export async function orderCard(page: Page, order: any) {
    const response = await page.goto(`/orders?search=${encodeURIComponent(order.code)}`);
    expect(response?.status(), 'Debe abrir Pedidos de instalación').toBe(200);
    return page.locator(`#pedido-${order.id}`);
}

// Solicitudes adversas autenticadas: ejercitan el servidor además de los controles HTML.
export async function rejected(page: Page, url: string, form: Record<string, any>, method = 'POST') {
    const token = await page.locator('input[name="_token"]') .first().inputValue();
    const response = await page.request.fetch(url, { method, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token! }, form });
    expect(response.status(), 'La operación inválida debe rechazarse sin modificar registros').toBe(422);
    const body = await response.json();
    expect(Object.keys(body.errors || {}).length).toBeGreaterThan(0);
    return body;
}
