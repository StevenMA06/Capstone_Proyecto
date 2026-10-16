import test from 'node:test';
import assert from 'node:assert/strict';
import { runFixture } from './fixture-runner.mjs';

test('lee un objeto JSON y tolera BOM de consola', () => {
    assert.deepEqual(runFixture('init', [], () => '\uFEFF {"laravel":"12"}\n'), { laravel: '12' });
});

test('conserva el error PHP cuando el proceso devuelve texto con salida cero', () => {
    assert.throws(() => runFixture('init', [], () => '\nIn fixture.php line 30:\nSQLSTATE[HY000] [1045] Access denied'), /PHP informó:[\s\S]*1045.*Access denied/);
});

test('muestra stderr y oculta contraseñas en fallos de proceso', () => {
    const prior = process.env.E2E_DB_PASSWORD;
    process.env.E2E_DB_PASSWORD = 'secreto-de-prueba';
    try {
        assert.throws(() => runFixture('init', [], () => {
            throw Object.assign(new Error('Falló PHP'), { stderr: 'SQLSTATE 1045: secreto-de-prueba' });
        }), error => error.message.includes('1045: [oculto]') && !error.message.includes('secreto-de-prueba'));
    } finally {
        if (prior === undefined) delete process.env.E2E_DB_PASSWORD;
        else process.env.E2E_DB_PASSWORD = prior;
    }
});

test('no presenta salida vacía ni JSON escalar como preparación correcta', () => {
    for (const output of ['', 'null', '42', '[]']) {
        assert.throws(() => runFixture('init', [], () => output), /no devolvió JSON válido/);
    }
});

test('recupera stdout si un proceso falló sin mensaje en stderr', () => {
    assert.throws(() => runFixture('init', [], () => {
        throw Object.assign(new Error('Proceso fallido'), { stderr: Buffer.alloc(0), stdout: Buffer.from('Error de migración real') });
    }), /Error de migración real/);
});
