<?php $__env->startSection('payments-content'); ?>
<div class="management-toolbar">
    <h2><?php echo e($payment->exists ? 'Editar pago #'.$payment->codigo : 'Registrar pago'); ?></h2>
    
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.index')): ?><a class="secondary" href="<?php echo e(route('payments.index')); ?>">Ver pagos</a><?php endif; ?>

</div>

<?php if($payment->exists && $payment->estado === 'REALIZADO'): ?>
    <div class="notice" role="alert">
        <strong>⚠️ Este pago ya está realizado y no puede ser modificado.</strong>
        <p>Los pagos realizados son definitivos para mantener la integridad del historial financiero.</p>
    </div>
    
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.show')): ?><a class="action-link" href="<?php echo e(route('payments.show', $payment)); ?>">Ver detalle del pago</a><?php endif; ?>

<?php elseif($payment->exists && in_array($payment->estado, ['IMPAGO', 'ANULADO'])): ?>
    <div class="notice error" role="alert">
        <strong>⚠️ Este pago está <?php echo e($payment->estado === 'IMPAGO' ? 'marcado como IMPAGO' : 'anulado'); ?> y no puede ser modificado.</strong>
        <p><?php echo e($payment->estado === 'IMPAGO' ? 'El pago superó el período de tolerancia de 7 días.' : 'El pago fue anulado por error administrativo.'); ?></p>
    </div>
    
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.show')): ?><a class="action-link" href="<?php echo e(route('payments.show', $payment)); ?>">Ver detalle del pago</a><?php endif; ?>

<?php else: ?>
<p class="muted">
    <?php echo e($payment->exists ? 'Completa los datos del pago cuando el cliente realice el pago.' : 'Los campos marcados con * son obligatorios. El código se genera automáticamente.'); ?>

</p>
<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.manage')): ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', $payment->exists ? 'payments.update' : 'payments.store')): ?><form class="account-form" method="POST" action="<?php echo e($payment->exists ? route('payments.update', $payment) : route('payments.store')); ?>" novalidate>
    <?php echo csrf_field(); ?>
    <?php if($payment->exists): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
    
    <div>
        <label for="contrato_id">Contrato *</label>
        <select id="contrato_id" name="contrato_id" required 
            <?php if($payment->exists): ?> disabled <?php endif; ?>
            <?php $__errorArgs = ['contrato_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="contrato_id-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <option value="">Selecciona un contrato activo</option>
            <?php $__currentLoopData = $contracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $contract): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($contract->contrato_id); ?>" 
                    data-client="<?php echo e(trim($contract->client->nombres_razon_social . ' ' . $contract->client->apellidos)); ?>"
                    data-plan="<?php echo e($contract->plan?->nombre ?? 'Sin plan'); ?>"
                    data-monthly="<?php echo e($contract->plan?->precio_mensual ?? 0); ?>"
                    <?php if(old('contrato_id', $payment->contrato_id) == $contract->contrato_id): echo 'selected'; endif; ?>>
                    <?php echo e($contract->codigo); ?> - <?php echo e(trim($contract->client->nombres_razon_social . ' ' . $contract->client->apellidos)); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php if($payment->exists): ?>
            <input type="hidden" name="contrato_id" value="<?php echo e($payment->contrato_id); ?>">
            <small>El contrato no puede modificarse en un pago existente.</small>
        <?php else: ?>
            <small>Solo se muestran contratos con estado ACTIVO.</small>
        <?php endif; ?>
        <?php $__errorArgs = ['contrato_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="contrato_id-error" class="error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div id="contract-info" class="notice" style="display: none; background: #f4f7f6; padding: 12px; border-left: 3px solid #087846; margin-bottom: 16px;">
        <strong>Información del contrato seleccionado:</strong>
        <p style="margin: 4px 0 0 0; font-size: 0.9rem;">
            <span id="info-client"></span><br>
            <span id="info-plan"></span><br>
            <span id="info-monthly"></span>
        </p>
    </div>

    <div>
        <label for="monto">Monto a pagar (S/) *</label>
        <input id="monto" name="monto" type="number" step="0.01" min="0.01" max="999999.99" 
            value="<?php echo e(old('monto', $payment->monto)); ?>" required
            <?php if($payment->exists): ?> readonly <?php endif; ?>
            <?php $__errorArgs = ['monto'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="monto-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <small><?php echo e($payment->exists ? 'El monto no puede modificarse en un pago existente.' : 'Ingresa el monto en soles.'); ?></small>
        <?php $__errorArgs = ['monto'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="monto-error" class="error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="periodo_servicio">Período de servicio *</label>
        <input id="periodo_servicio" name="periodo_servicio" type="text" placeholder="YYYY-MM (Ej: 2026-09)" 
            pattern="\d{4}-(0[1-9]|1[0-2])" maxlength="7"
            value="<?php echo e(old('periodo_servicio', $payment->periodo_servicio)); ?>" required
            <?php if($payment->exists): ?> readonly <?php endif; ?>
            <?php $__errorArgs = ['periodo_servicio'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="periodo_servicio-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <small><?php echo e($payment->exists ? 'El período de servicio no puede modificarse.' : 'Formato: YYYY-MM (Ej: 2026-09 para septiembre 2026).'); ?></small>
        <?php $__errorArgs = ['periodo_servicio'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="periodo_servicio-error" class="error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="fecha_vencimiento">Fecha de vencimiento *</label>
        <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" 
            value="<?php echo e(old('fecha_vencimiento', $payment->fecha_vencimiento?->format('Y-m-d'))); ?>" required
            <?php if($payment->exists): ?> readonly <?php endif; ?>
            <?php if(!$payment->exists): ?> min="<?php echo e(date('Y-m-d')); ?>" <?php endif; ?>
            <?php $__errorArgs = ['fecha_vencimiento'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="fecha_vencimiento-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <small><?php echo e($payment->exists ? 'La fecha de vencimiento no puede modificarse.' : 'Debe ser igual o posterior a hoy.'); ?></small>
        <?php $__errorArgs = ['fecha_vencimiento'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="fecha_vencimiento-error" class="error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="fecha_pago">Fecha de pago (opcional)</label>
        <input id="fecha_pago" name="fecha_pago" type="date" 
            value="<?php echo e(old('fecha_pago', $payment->fecha_pago?->format('Y-m-d'))); ?>" 
            max="<?php echo e(date('Y-m-d')); ?>"
            <?php $__errorArgs = ['fecha_pago'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="fecha_pago-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <small>Déjalo vacío si aún no se realizó el pago. No puede ser posterior a hoy.</small>
        <?php $__errorArgs = ['fecha_pago'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="fecha_pago-error" class="error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="metodo_pago">Método de pago (opcional)</label>
        <select id="metodo_pago" name="metodo_pago" <?php $__errorArgs = ['metodo_pago'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="metodo_pago-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <option value="">-- Seleccionar --</option>
            <?php $__currentLoopData = ['TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $metodo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($metodo); ?>" <?php if(old('metodo_pago', $payment->metodo_pago) === $metodo): echo 'selected'; endif; ?>><?php echo e($metodo); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <small>Requerido si indicaste fecha de pago.</small>
        <?php $__errorArgs = ['metodo_pago'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="metodo_pago-error" class="error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="referencia_externa">Número de operación (opcional)</label>
        <input id="referencia_externa" name="referencia_externa" type="text" maxlength="120"
            placeholder="Ej: OP-123456, YAPE-789012, etc."
            value="<?php echo e(old('referencia_externa', $payment->referencia_externa)); ?>"
            <?php $__errorArgs = ['referencia_externa'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="referencia_externa-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <small>Número de operación o transacción del pago.</small>
        <?php $__errorArgs = ['referencia_externa'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="referencia_externa-error" class="error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="observaciones">Observaciones (opcional)</label>
        <textarea id="observaciones" name="observaciones" rows="5" maxlength="500"
            <?php $__errorArgs = ['observaciones'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="observaciones-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><?php echo e(old('observaciones', $payment->observaciones)); ?></textarea>
        <small>Información adicional sobre el pago.</small>
        <?php $__errorArgs = ['observaciones'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="observaciones-error" class="error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div class="form-actions">
        <button class="action-link" type="submit"><?php echo e($payment->exists ? 'Guardar cambios' : 'Registrar pago'); ?></button>
        
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.show')): ?><a class="secondary" href="<?php echo e($payment->exists ? route('payments.show', $payment) : route('payments.index')); ?>">Cancelar</a><?php endif; ?>

    </div>
</form><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<script src="<?php echo e(asset('js/payments.js')); ?>" defer></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('payments.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/payments/create.blade.php ENDPATH**/ ?>