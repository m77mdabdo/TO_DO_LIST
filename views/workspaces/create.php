<?php /** @var \App\View\View $this */ $form = $this->form; ?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow">
    <section class="card stack">
        <h1><?= $this->te('Create a company workspace') ?></h1>
        <p class="muted"><?= $this->te('Each company gets its own workspace. Its boards and members stay separate from your other workspaces.') ?></p>

        <form action="<?= $this->url('/workspaces') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label>
                <span><?= $this->te('Company name') ?></span>
                <input name="company_name" required maxlength="100" dir="auto" autofocus
                       value="<?= $this->e($form->old('company_name')) ?>"
                       aria-invalid="<?= $form->error('company_name') ? 'true' : 'false' ?>">
                <?= $this->partial('partials/field-error', ['field' => 'company_name']) ?>
            </label>
            <div class="row-end">
                <a class="btn btn-ghost" href="<?= $this->url('/') ?>"><?= $this->te('Cancel') ?></a>
                <button class="btn btn-primary" type="submit"><?= $this->te('Create workspace') ?></button>
            </div>
        </form>
    </section>
</main>
