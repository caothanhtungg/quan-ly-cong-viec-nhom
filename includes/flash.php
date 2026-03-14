<?php
$flash = get_flash();
if ($flash):
    $toastBg = match ($flash['type']) {
        'success' => 'text-bg-success',
        'danger'  => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        default   => 'text-bg-primary'
    };
?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;">
        <div class="toast align-items-center <?= e($toastBg) ?> border-0 js-auto-toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <?= e($flash['message']) ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
<?php endif; ?>