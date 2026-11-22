<?php
declare(strict_types=1);

/* Classroom queries and access rules. */

function find_classroom(int $id): ?array
{
    return one(
        'SELECT c.*, u.full_name AS teacher_name
         FROM classrooms c JOIN users u ON u.id = c.teacher_id
         WHERE c.id = ?',
        [$id]
    );
}

/** Returns [classroom, isOwner]. Owners and joined students may view; everyone else gets a 404. */
function classroom_for_viewer(int $id, array $user): array
{
    $classroom = find_classroom($id);
    if ($classroom === null) {
        not_found();
    }
    if ($user['role'] === ROLE_TEACHER) {
        if ((int) $classroom['teacher_id'] !== (int) $user['id']) {
            not_found();
        }
        return [$classroom, true];
    }
    $member = one(
        'SELECT 1 AS found FROM classroom_members WHERE classroom_id = ? AND student_id = ?',
        [$id, (int) $user['id']]
    );
    if ($member === null) {
        not_found();
    }
    return [$classroom, false];
}

/** The classroom if the user owns it, otherwise a 404. */
function owned_classroom(int $id, array $user): array
{
    [$classroom, $isOwner] = classroom_for_viewer($id, $user);
    if (!$isOwner) {
        not_found();
    }
    return $classroom;
}

function create_classroom(int $teacherId, array $form): int
{
    for ($attempt = 0; $attempt < 20; $attempt++) {
        try {
            return insert(
                'INSERT INTO classrooms (teacher_id, name, subject, description, class_code) VALUES (?, ?, ?, ?, ?)',
                [$teacherId, $form['name'], $form['subject'], $form['description'], new_class_code()]
            );
        } catch (PDOException $exception) {
            if (!is_unique_violation($exception)) {
                throw $exception;
            }
        }
    }
    throw new RuntimeException('Could not generate a unique class code.');
}

function regenerate_class_code(int $classroomId): bool
{
    for ($attempt = 0; $attempt < 20; $attempt++) {
        try {
            run('UPDATE classrooms SET class_code = ? WHERE id = ?', [new_class_code(), $classroomId]);
            return true;
        } catch (PDOException $exception) {
            if (!is_unique_violation($exception)) {
                throw $exception;
            }
        }
    }
    return false;
}

function is_unique_violation(PDOException $exception): bool
{
    return $exception->getCode() === '23000' || str_contains($exception->getMessage(), 'UNIQUE');
}
