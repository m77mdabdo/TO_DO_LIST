<?php /** @var \App\View\View $this @var string $appName */ $form = $this->form; ?>
<main class="auth">
    <section class="auth-art" aria-hidden="true">
        <div class="floating-card fc-1"><span class="dot dot-violet"></span>Plan the week</div>
        <div class="floating-card fc-2"><span class="dot dot-amber"></span>Gym at 7pm</div>
        <div class="floating-card fc-3"><?= $this->icon('check') ?>Finish the report</div>
        <h2>Small steps,<br><span class="gradient-text">every single day.</span></h2>
        <p>Your tasks, lists and deadlines in one bright, simple board.</p>
    </section>

    <section class="card auth-card">
        <a class="brand" href="<?= $this->url('/login') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span><span><?= $this->e($appName) ?></span>
        </a>
        <h1>Welcome back</h1>
        <p class="muted">Log in to pick up where you left off.</p>

        <?php if ($message = $form->error('login')): ?>
            <div class="alert"><?= $this->icon('flag') ?><?= $this->e($message) ?></div>
        <?php endif; ?>

        <form action="<?= $this->url('/login') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label>
                <span>Email</span>
                <input type="email" name="email" required autocomplete="email" autofocus value="<?= $this->e($form->old('email')) ?>"
                       aria-invalid="<?= $form->error('email') ? 'true' : 'false' ?>">
                <?= $this->partial('partials/field-error', ['field' => 'email']) ?>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password"
                       aria-invalid="<?= $form->error('password') ? 'true' : 'false' ?>">
                <?= $this->partial('partials/field-error', ['field' => 'password']) ?>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Log in</button>
        </form>

        <p class="muted small center">New here? <a href="<?= $this->url('/register') ?>">Create an account</a></p>
    </section>
</main>
