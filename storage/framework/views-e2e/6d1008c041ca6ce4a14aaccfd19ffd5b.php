<?php if(auth()->guard()->check()): ?>
<script src="<?php echo e(asset('js/session.js')); ?>" data-check="<?php echo e(route('session.status')); ?>" data-login="<?php echo e(route('login')); ?>" data-timeout="<?php echo e((int) config('session.lifetime') * 60); ?>" defer></script>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/layouts/session-monitor.blade.php ENDPATH**/ ?>