<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <?php include __DIR__ . '/../../includes/tabs.php'; ?>

    <div class="container">
        <div class="white-card">
            <div class="inner-nav">
                <div class="mode-selector">
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=list" class="btn-mode <?= $mode == 'list' ? 'active' : '' ?>"><i class="fas fa-list"></i> Ver Lista</a>
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=form" class="btn-mode <?= $mode == 'form' ? 'active' : '' ?>"><i class="fas fa-plus"></i> Adicionar Novo</a>
                </div>
            </div>

            <?php if ($mode === 'form'): ?>
                <?php include __DIR__ . '/content/form.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/content/list.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
