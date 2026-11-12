<?php /** @var \App\View\View $this @var string $field */ ?>
<?php if ($message = $this->form->error($field)): ?><small class="field-error"><?= $this->te($message) ?></small><?php endif; ?>
