</div>
</div>

<div class="app-sidebar-backdrop js-sidebar-backdrop d-lg-none"></div>

<div class="modal fade" id="globalConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 app-modal">
            <div class="modal-header border-0">
                <h5 class="modal-title">Xac nhan thao tac</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="globalConfirmMessage">Ban co chac muon thuc hien thao tac nay khong?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huy</button>
                <button type="button" id="globalConfirmAction" class="btn btn-danger">Dong y</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('/assets/js/main.js')) ?>"></script>
</body>
</html>
