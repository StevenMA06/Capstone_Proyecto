<?php $__env->startSection('title', 'Iniciar sesión'); ?>
<?php $__env->startSection('body-class', 'login-page'); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/login.css')); ?>?v=1">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<section class="login-centered" aria-labelledby="login-heading">
    <header>
        <div class="company-logo"><img src="<?php echo e(asset('images/lunnys-peru.png')); ?>" alt="LUNNY’S PERU R.2.D. — colibríes y orquídea" width="1600" height="1600"></div>
        <h1 id="login-heading">Sistema web basado en Business Intelligence</h1>
        <p class="login-subtitle">Gestión de procesos operativos</p>
    </header>
    <div class="login-form">
        <?php if(session('status')): ?><div class="notice success" role="status"><?php echo e(session('status')); ?></div><?php endif; ?>
        <form method="POST" action="<?php echo e(route('login.store')); ?>">
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="email">Correo o usuario</label>
                <div class="input-row <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 22v-4a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v4"/></svg></span>
                    <input id="email" name="email" type="text" autocomplete="username" placeholder="Ingresa tu usuario o correo" value="<?php echo e(old('email')); ?>" required maxlength="150" <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="login-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
                </div>
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <div class="input-row <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 15v3"/></svg></span>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required maxlength="1024" <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="login-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
                    <button id="toggle-password" class="password-toggle" type="button" aria-label="Mostrar contraseña" aria-controls="password" aria-pressed="false" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button>
                </div>
            </div>
            <button class="primary" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 15v3"/></svg> Iniciar sesión</button>
        </form>
        <?php if($errors->any()): ?>
        <div id="login-error" class="login-warning" role="alert">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.3 4.5 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0Z"/><path d="M12 9v5m0 3v1"/></svg>
            <div><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/auth/login.blade.php ENDPATH**/ ?>