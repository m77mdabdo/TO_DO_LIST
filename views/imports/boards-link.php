<?php
/**
 * "Import a Trello board" next to "Create board" (slot "boards.create").
 *
 * @var \App\View\View $this
 */
?>
<a class="btn btn-ghost" href="<?= $this->url('/import/trello') ?>"><?= $this->icon('upload') ?><?= $this->te('Import a Trello board') ?></a>
