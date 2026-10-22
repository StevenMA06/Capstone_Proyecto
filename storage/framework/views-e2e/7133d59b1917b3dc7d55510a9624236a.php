<nav class="pagination" aria-label="<?php echo e($label); ?>">
    <span><?php echo e($records->total()); ?> registros · Página <?php echo e($records->currentPage()); ?> de <?php echo e($records->lastPage()); ?></span>
    <div>
        <?php if($records->previousPageUrl()): ?><a class="secondary" href="<?php echo e($records->previousPageUrl()); ?>">Anterior</a><?php endif; ?>
        <?php if($records->nextPageUrl()): ?><a class="secondary" href="<?php echo e($records->nextPageUrl()); ?>">Siguiente</a><?php endif; ?>
    </div>
</nav>
<?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/clients/pagination.blade.php ENDPATH**/ ?>