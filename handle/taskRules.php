<?php

use Classes\TaskRepository;
use Classes\Validation\Validation;

// Shared by addToDo.php and edit.php so both validate the same way.
function validate_task(Classes\Request $request): Validation
{
    return (new Validation())
        ->validate('title', $request->input('title'), ['Required', 'Str', 'Min:2', 'Max:150'], 'Title')
        ->validate('notes', $request->input('notes'), ['Max:1000'], 'Notes')
        ->validate('priority', $request->input('priority', 'normal'), ['In:' . implode(',', array_keys(TaskRepository::PRIORITIES))], 'Priority')
        ->validate('due_date', $request->input('due_date'), ['Date'], 'Due date')
        ->validate('category', $request->input('category'), ['Max:30'], 'List name');
}

function task_input(Classes\Request $request): array
{
    return [
        'title'    => $request->input('title'),
        'notes'    => $request->input('notes'),
        'priority' => $request->input('priority', 'normal') ?: 'normal',
        'due_date' => $request->input('due_date'),
        'category' => $request->input('category'),
    ];
}
