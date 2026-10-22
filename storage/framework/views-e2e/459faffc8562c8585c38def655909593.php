<?php $__env->startSection('equipments-content'); ?>
<div class="form-layout">
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('equipments.manage')): ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', $equipment->exists ? 'equipments.update' : 'equipments.store')): ?><form class="standard-form" method="POST" action="<?php echo e($equipment->exists ? route('equipments.update', $equipment) : route('equipments.store')); ?>" novalidate>
        <?php echo csrf_field(); ?>
        <?php if($equipment->exists): ?>
            <?php echo method_field('PUT'); ?>
        <?php endif; ?>
        
        <h2><?php echo e($equipment->exists ? 'Editar equipo' : 'Registrar nuevo equipo'); ?></h2><p class="form-intro">Completa los campos marcados con *. Los demás son opcionales.</p>
        
        <div class="inventory-grid"><section class="inventory-card"><h3><span>01</span> Identificación del equipo</h3>
        
        <?php if($equipment->exists): ?>
        
        <div class="form-row" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label for="codigo">Código</label>
                <input type="text" id="codigo" name="codigo" value="<?php echo e(old('codigo', $equipment->codigo)); ?>" maxlength="60" <?php if($equipment->estado === 'ASIGNADO'): ?> readonly <?php endif; ?>>
                <?php $__errorArgs = ['codigo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            
            <div class="form-group">
                <label for="numero_serie">N° Serie</label>
                <input type="text" id="numero_serie" name="numero_serie" value="<?php echo e(old('numero_serie', $equipment->numero_serie)); ?>" maxlength="100">
                <?php $__errorArgs = ['numero_serie'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>
        <?php else: ?>
        
        <div class="form-row" style="grid-template-columns: 1fr;">
            <div class="form-group">
                <label for="numero_serie">N° Serie</label>
                <input type="text" id="numero_serie" name="numero_serie" value="<?php echo e(old('numero_serie', $equipment->numero_serie)); ?>" maxlength="100">
                <small style="color: #64748b;">El código se generará automáticamente</small>
                <?php $__errorArgs = ['numero_serie'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>
        <?php endif; ?>
            
        <div class="form-row" style="grid-template-columns: 1fr;">
            <div class="form-group">
                <label for="nombre">Nombre del equipo *</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo e(old('nombre', $equipment->nombre)); ?>" maxlength="120">
                <?php $__errorArgs = ['nombre'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>
        
        <!-- Fila 2: Tipo, Marca, Modelo -->
        <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="form-group">
                <label for="tipo">Tipo *</label>
                <input type="text" id="tipo" name="tipo" value="<?php echo e(old('tipo', $equipment->tipo)); ?>" maxlength="80" placeholder="Router, ONT, Switch...">
                <?php $__errorArgs = ['tipo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            
            <div class="form-group">
                <label for="marca">Marca</label>
                <input type="text" id="marca" name="marca" value="<?php echo e(old('marca', $equipment->marca)); ?>" maxlength="80" placeholder="TP-Link, Huawei...">
                <?php $__errorArgs = ['marca'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            
            <div class="form-group">
                <label for="modelo">Modelo</label>
                <input type="text" id="modelo" name="modelo" value="<?php echo e(old('modelo', $equipment->modelo)); ?>" maxlength="100" placeholder="Archer C6, HG8546M...">
                <?php $__errorArgs = ['modelo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>
        
        </section><section class="inventory-card"><h3><span>02</span> Estado y condición</h3>
        <div class="form-row" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label for="condicion">Condición *</label>
                <select id="condicion" name="condicion">
                    <?php $__currentLoopData = ['NUEVO' => 'Nuevo', 'BUENO' => 'Bueno', 'REGULAR' => 'Regular', 'MALO' => 'Malo', 'MALOGRADO' => 'Malogrado']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($value); ?>" <?php if(old('condicion', $equipment->condicion) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php $__errorArgs = ['condicion'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            
            <div class="form-group">
                <label for="estado">Estado *</label>
                <?php if($equipment->exists && !auth()->user()->hasPermission('equipments.status')): ?><input type="hidden" name="estado" value="<?php echo e($equipment->estado); ?>"><?php endif; ?>
                <select id="estado" name="estado" <?php if($equipment->exists && !auth()->user()->hasPermission('equipments.status')): echo 'disabled'; endif; ?> <?php if($equipment->estado === 'ASIGNADO'): ?> disabled <?php endif; ?>>
                    <?php $__currentLoopData = ['DISPONIBLE' => 'Disponible', 'ASIGNADO' => 'Asignado', 'MANTENIMIENTO' => 'Mantenimiento', 'INACTIVO' => 'Inactivo']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($value); ?>" <?php if(old('estado', $equipment->estado) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php if($equipment->estado === 'ASIGNADO'): ?>
                    <input type="hidden" name="estado" value="ASIGNADO">
                    <small style="color: #dc2626;">⚠️ Equipo asignado</small>
                <?php endif; ?>
                <?php $__errorArgs = ['estado'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="2" maxlength="500" placeholder="Notas adicionales..."><?php echo e(old('observaciones', $equipment->observaciones)); ?></textarea>
                <?php $__errorArgs = ['observaciones'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error-message"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>
        
        </section></div><div class="form-actions">
            <button type="submit" class="action-link"><?php echo e($equipment->exists ? 'Actualizar equipo' : 'Registrar equipo'); ?></button>
            
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.show')): ?><a href="<?php echo e($equipment->exists ? route('equipments.show', $equipment) : route('equipments.index')); ?>" class="clear-link">Cancelar</a><?php endif; ?>

        </div>
    </form><?php endif; ?>
<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.standard-form');
    
    // Remover aria-invalid de todos los campos al cargar (para evitar bordes rojos iniciales)
    const allInputs = form.querySelectorAll('input, select, textarea');
    allInputs.forEach(input => {
        input.removeAttribute('aria-invalid');
    });
    
    form.addEventListener('submit', function(e) {
        let hasErrors = false;
        
        // Validar campos requeridos
        const requiredFields = [
            { id: 'nombre', label: 'Nombre del equipo' },
            { id: 'tipo', label: 'Tipo' },
            { id: 'condicion', label: 'Condición' },
            { id: 'estado', label: 'Estado' }
        ];
        
        requiredFields.forEach(field => {
            const input = document.getElementById(field.id);
            const value = input.value.trim();
            
            if (!value) {
                hasErrors = true;
                input.style.borderColor = '#c34444';
                
                // Agregar mensaje de error si no existe
                const errorMsg = input.parentElement.querySelector('.error-message');
                if (!errorMsg) {
                    const span = document.createElement('span');
                    span.className = 'error-message';
                    span.textContent = `El campo ${field.label} es obligatorio.`;
                    input.parentElement.appendChild(span);
                } else {
                    errorMsg.textContent = `El campo ${field.label} es obligatorio.`;
                    errorMsg.hidden = false;
                }
                input.setAttribute('aria-invalid', 'true');
            } else {
                // Remover error si el campo está lleno
                input.style.borderColor = '';
                const errorMsg = input.parentElement.querySelector('.error-message');
                if (errorMsg && !errorMsg.textContent.includes('ya está registrado')) {
                    errorMsg.remove();
                }
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    
    // Limpiar errores cuando el usuario empieza a escribir
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            this.style.borderColor = '';
            this.removeAttribute('aria-invalid');
            const errorMsg = this.parentElement.querySelector('.error-message');
            if (errorMsg && !errorMsg.textContent.includes('ya está registrado')) {
                errorMsg.remove();
            }
        });
    });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('equipments.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/equipments/create.blade.php ENDPATH**/ ?>