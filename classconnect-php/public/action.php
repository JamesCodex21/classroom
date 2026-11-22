<?php
require __DIR__ . '/../app/bootstrap.php';

/* Handles every change made from a button: leave, remove student, regenerate code,
   delete classroom and delete material. All requests must be POST with a valid CSRF token. */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_error(405, 'Please use the buttons on the page to make changes.');
}
verify_csrf();

$user = require_login();
$action = (string) ($_POST['action'] ?? '');

switch ($action) {
    case 'leave':
        if ($user['role'] !== ROLE_STUDENT) {
            render_error(403, 'Only students can leave a classroom.');
        }
        $removed = run(
            'DELETE FROM classroom_members WHERE classroom_id = ? AND student_id = ?',
            [(int) ($_POST['classroom_id'] ?? 0), (int) $user['id']]
        );
        flash($removed ? 'success' : 'error', $removed ? 'You left the classroom.' : 'You are not a member of that classroom.');
        redirect('dashboard.php');

    case 'remove_student':
        $classroom = owned_classroom((int) ($_POST['classroom_id'] ?? 0), $user);
        $removed = run(
            'DELETE FROM classroom_members WHERE classroom_id = ? AND student_id = ?',
            [(int) $classroom['id'], (int) ($_POST['student_id'] ?? 0)]
        );
        flash($removed ? 'success' : 'error', $removed ? 'The student was removed from this classroom.' : 'That student is not in this classroom.');
        redirect('classroom.php?id=' . (int) $classroom['id']);

    case 'regenerate_code':
        $classroom = owned_classroom((int) ($_POST['classroom_id'] ?? 0), $user);
        if (regenerate_class_code((int) $classroom['id'])) {
            flash('success', 'A new class code was generated. The old code no longer works.');
        } else {
            flash('error', 'We could not generate a new code. Please try again.');
        }
        redirect('classroom-form.php?id=' . (int) $classroom['id']);

    case 'delete_classroom':
        $classroom = owned_classroom((int) ($_POST['classroom_id'] ?? 0), $user);
        $storedNames = all('SELECT stored_name FROM learning_materials WHERE classroom_id = ?', [(int) $classroom['id']]);
        run('DELETE FROM classrooms WHERE id = ?', [(int) $classroom['id']]);
        foreach ($storedNames as $row) {
            delete_stored_file($row['stored_name']);
        }
        flash('success', '"' . $classroom['name'] . '" and its materials were deleted.');
        redirect('dashboard.php');

    case 'delete_material':
        [$material, $isOwner] = material_for_viewer((int) ($_POST['material_id'] ?? 0), $user);
        if (!$isOwner) {
            not_found();
        }
        run('DELETE FROM learning_materials WHERE id = ?', [(int) $material['id']]);
        delete_stored_file($material['stored_name']);
        flash('success', '"' . $material['title'] . '" was deleted.');
        if (($_POST['return_to'] ?? '') === 'classroom') {
            redirect('classroom.php?id=' . (int) $material['classroom_id']);
        }
        redirect('upload.php?classroom=' . (int) $material['classroom_id']);

    default:
        flash('error', 'That action is not available.');
        redirect('dashboard.php');
}
