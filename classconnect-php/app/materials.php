<?php
declare(strict_types=1);

/* Learning material queries and access rules. */

/** Returns [material, isOwner]. Owners and joined students may view; everyone else gets a 404. */
function material_for_viewer(int $id, array $user): array
{
    $material = one(
        'SELECT m.*, c.name AS classroom_name, c.teacher_id AS owner_id
         FROM learning_materials m JOIN classrooms c ON c.id = m.classroom_id
         WHERE m.id = ?',
        [$id]
    );
    if ($material === null) {
        not_found();
    }
    if ($user['role'] === ROLE_TEACHER) {
        if ((int) $material['owner_id'] !== (int) $user['id']) {
            not_found();
        }
        return [$material, true];
    }
    $member = one(
        'SELECT 1 AS found FROM classroom_members WHERE classroom_id = ? AND student_id = ?',
        [(int) $material['classroom_id'], (int) $user['id']]
    );
    if ($member === null) {
        not_found();
    }
    return [$material, false];
}

/** Materials across a teacher's classrooms, optionally filtered to one classroom (0 = all). */
function materials_for_teacher(int $teacherId, int $classroomFilter): array
{
    return all(
        'SELECT m.*, c.name AS classroom_name
         FROM learning_materials m JOIN classrooms c ON c.id = m.classroom_id
         WHERE c.teacher_id = ? AND (? = 0 OR m.classroom_id = ?)
         ORDER BY m.uploaded_at DESC, m.id DESC',
        [$teacherId, $classroomFilter, $classroomFilter]
    );
}
