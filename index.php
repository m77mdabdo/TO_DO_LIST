<?php

use Classes\Session;

require_once 'inc/header.php';
require_once 'inc/connection.php';
?>
<body>

    <div class="container my-3">
        <div class="row d-flex justify-content-center">
            <div class="container mb-5 d-flex justify-content-center">
                <div class="col-md-4">
                    <?php 
                    if (Session::get("errors")) {
                        foreach (Session::get("errors") as $error) {
                            if (is_array($error)) {
                                foreach ($error as $err) { ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
                                <?php }
                            } else { ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php }
                        }
                        Session::unset("errors");
                    } ?>

                    <form action="handle/addToDo.php" method="post">
                        <textarea class="form-control" rows="3" name="titile" placeholder="Enter your note here"></textarea>
                        <div class="text-center">
                            <button type="submit" name="submit" class="form-control text-white bg-info mt-3">Add To Do</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row d-flex justify-content-between">
            <!-- All Notes -->
            <div class="col-md-3">
                <h4>All Notes</h4>
                <div class="m-2 py-3">
                    <?php
                    $stmt = $conn->query("SELECT id, titile, created_at FROM todo WHERE status='all' ORDER BY id DESC");
                    $allNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($allNotes) {
                        foreach ($allNotes as $all) { ?>
                            <div class="alert alert-info p-2">
                                <h4><?= htmlspecialchars($all['titile']) ?></h4>
                                <h5><?= htmlspecialchars($all['created_at']) ?></h5>
                                <div class="d-flex justify-content-between mt-3">
                                    <a href="edit.php?id=<?= $all['id'] ?>" class="btn btn-info p-1 text-white">Edit</a>
                                    <a href="handle/goto.php?status=doing&id=<?= $all['id'] ?>" class="btn btn-info p-1 text-white">Doing</a>
                                </div>
                            </div>
                        <?php }
                    } else { ?>
                        <div class="alert alert-info text-center">Empty to-do</div>
                    <?php } ?>
                </div>
            </div>

            <!-- Doing -->
            <div class="col-md-3">
                <h4>Doing</h4>
                <div class="m-2 py-3">
                    <?php
                    $stmt = $conn->query("SELECT id, titile, created_at FROM todo WHERE status='doing' ORDER BY id DESC");
                    $doingNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($doingNotes) {
                        foreach ($doingNotes as $doing) { ?>
                            <div class="alert alert-success p-2">
                                <h4><?= htmlspecialchars($doing['titile']) ?></h4>
                                <h5><?= htmlspecialchars($doing['created_at']) ?></h5>
                                <div class="d-flex justify-content-between mt-3">
                                    <a href="handle/goto.php?status=done&id=<?= $doing['id'] ?>" class="btn btn-success p-1 text-white">Done</a>
                                </div>
                            </div>
                        <?php }
                    } else { ?>
                        <div class="alert alert-success text-center">Empty to-do</div>
                    <?php } ?>
                </div>
            </div>

            <!-- Done -->
            <div class="col-md-3">
                <h4>Done</h4>
                <div class="m-2 py-3">
                    <?php
                    $stmt = $conn->query("SELECT id, titile, created_at FROM todo WHERE status='done' ORDER BY id DESC");
                    $doneNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($doneNotes) {
                        foreach ($doneNotes as $done) { ?>
                            <div class="alert alert-warning p-2">
                                <a href="handle/delete.php?id=<?= $done['id'] ?>" onclick="return confirm('Are you sure?')" class="text-dark d-flex justify-content-end">
                                    <i class="fa fa-close" style="font-size:16px;"></i>
                                </a>
                                <h4><?= htmlspecialchars($done['titile']) ?></h4>
                                <h5><?= htmlspecialchars($done['created_at']) ?></h5>
                            </div>
                        <?php }
                    } else { ?>
                        <div class="alert alert-warning text-center">Empty to-do</div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
