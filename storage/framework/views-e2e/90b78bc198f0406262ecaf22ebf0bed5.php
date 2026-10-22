<?php $__env->startSection('payments-content'); ?>
<div class="management-toolbar">
    <div>
        <h2>Información del Pago</h2>
        <p class="muted">Código: <?php echo e($payment->codigo); ?></p>
    </div>
    <div class="row-actions">
        
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.index')): ?><a class="secondary" href="<?php echo e(route('payments.index')); ?>">← Volver a pagos</a><?php endif; ?>

        <?php if($payment->canBeEdited()): ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.manage')): ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.edit')): ?><a class="action-link" href="<?php echo e(route('payments.edit', $payment)); ?>">Validar pago</a><?php endif; ?>
<?php endif; ?>
        <?php endif; ?>
        <?php if($payment->constancia_ruta && $payment->isCompleted()): ?>
            
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.receipt')): ?><a class="action-link" href="<?php echo e(route('payments.receipt', $payment)); ?>" target="_blank" title="Ver comprobante de pago">
                <i class="bi bi-file-pdf"></i> Ver comprobante
            </a><?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<section aria-labelledby="payment-info-title">
    <h3 id="payment-info-title" style="margin-bottom: 16px; font-size: 1.1rem; color: #087846;">Datos del Pago</h3>
    <dl class="account-detail">
        <div><dt>Código</dt><dd><strong><?php echo e($payment->codigo); ?></strong></dd></div>
        <div><dt>Estado</dt><dd>
            <span class="state <?php echo e($payment->estado === 'REALIZADO' ? 'active' : 
                ($payment->estado === 'VENCIDO' || $payment->estado === 'IMPAGO' ? 'inactive' : '')); ?>" style="
                <?php if($payment->estado === 'VENCIDO'): ?> background: #fee2e2; color: #991b1b; <?php endif; ?>
                <?php if($payment->estado === 'IMPAGO'): ?> background: #ede9fe; color: #5b21b6; <?php endif; ?>
                <?php if($payment->estado === 'PENDIENTE'): ?> background: #fef3c7; color: #92400e; <?php endif; ?>
                <?php if($payment->estado === 'ANULADO'): ?> background: #e5e7eb; color: #374151; <?php endif; ?>
            ">
                <?php echo e(ucfirst(strtolower($payment->estado))); ?>

            </span>
        </dd></div>
        <div><dt>Período de servicio</dt><dd><?php echo e($payment->periodo_servicio ?? '—'); ?></dd></div>
        <div><dt>Monto</dt><dd><strong style="color: #087846;">S/ <?php echo e(number_format($payment->monto, 2)); ?></strong></dd></div>
        <div><dt>Fecha de vencimiento</dt><dd><?php echo e($payment->fecha_vencimiento?->format('d/m/Y') ?? '—'); ?></dd></div>
        <div><dt>Fecha de pago</dt><dd><?php echo e($payment->fecha_pago?->format('d/m/Y') ?? 'Aún no realizado'); ?></dd></div>
        <div><dt>Método de pago</dt><dd><?php echo e($payment->metodo_pago ?? '—'); ?></dd></div>
        <div><dt>Referencia externa</dt><dd><?php echo e($payment->referencia_externa ?? '—'); ?></dd></div>
        <div><dt>Observaciones</dt><dd style="white-space: pre-line;"><?php echo e($payment->observaciones ?? '—'); ?></dd></div>
        <?php if($payment->constancia_ruta && $payment->isCompleted()): ?>
            <div>
                <dt>Comprobante de pago</dt>
                <dd>
                    <?php
                        $extension = strtolower(pathinfo($payment->constancia_ruta, PATHINFO_EXTENSION));
                        $isImage = in_array($extension, ['jpg', 'jpeg', 'png']);
                        $isPdf = $extension === 'pdf';
                    ?>
                    
                    <?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.receipt')): ?><a href="<?php echo e(route('payments.receipt', $payment)); ?>" target="_blank" class="action-link" style="display: inline-flex; align-items: center; gap: 6px;">
                        <?php if($isImage): ?>
                            <i class="bi bi-file-earmark-image-fill" style="color: #087846;"></i>
                            Ver imagen del comprobante
                        <?php elseif($isPdf): ?>
                            <i class="bi bi-file-pdf-fill" style="color: #dc2626;"></i>
                            Ver comprobante PDF
                        <?php else: ?>
                            <i class="bi bi-file-earmark-fill" style="color: #6b7280;"></i>
                            Ver comprobante
                        <?php endif; ?>
                    </a><?php endif; ?>

                </dd>
            </div>
        <?php endif; ?>
    </dl>
</section>

<section aria-labelledby="contract-info-title" style="margin-top: 32px;">
    <h3 id="contract-info-title" style="margin-bottom: 16px; font-size: 1.1rem; color: #087846;">Información del Contrato y Cliente</h3>
    <dl class="account-detail">
        <div><dt>Contrato</dt><dd><strong><?php echo e($payment->contract?->codigo ?? '—'); ?></strong></dd></div>
        <div><dt>Cliente</dt><dd><?php echo e(trim(($payment->contract?->client?->nombres_razon_social ?? '') . ' ' . ($payment->contract?->client?->apellidos ?? ''))); ?></dd></div>
        <div><dt>Documento</dt><dd><?php echo e($payment->contract?->client?->tipo_documento); ?> <?php echo e($payment->contract?->client?->numero_documento); ?></dd></div>
        <div><dt>Correo electrónico</dt><dd>
            <?php if($payment->contract?->client?->correo): ?>
                <a href="mailto:<?php echo e($payment->contract->client->correo); ?>" style="color: #087846; text-decoration: none;">
                    <i class="bi bi-envelope"></i> <?php echo e($payment->contract->client->correo); ?>

                </a>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd></div>
        <div><dt>Teléfono</dt><dd>
            <?php if($payment->contract?->client?->telefono): ?>
                <a href="tel:<?php echo e($payment->contract->client->telefono); ?>" style="color: #087846; text-decoration: none;">
                    <i class="bi bi-telephone"></i> <?php echo e($payment->contract->client->telefono); ?>

                </a>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd></div>
        <div><dt>Plan contratado</dt><dd><?php echo e($payment->contract?->plan?->nombre ?? 'Sin plan'); ?></dd></div>
    </dl>
</section>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('payments.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/payments/show.blade.php ENDPATH**/ ?>