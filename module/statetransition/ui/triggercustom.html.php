<?php
declare(strict_types=1);
/**
 * The UI file of statetransition module.
 *
 * Renders the modal comment form for triggerCustom GET requests.
 * When requireComment=true the comment field is required; otherwise optional.
 *
 * Includes a 负责人 (assignedTo) picker so the user can hand off responsibility
 * during the transition — e.g., dev → QA, QA → dev. Empty selection keeps the
 * current assignedTo unchanged.
 */
namespace zin;

$assignedToItems = array();
foreach($users as $account => $realname)
{
    $assignedToItems[] = array('text' => $realname, 'value' => $account);
}

modalHeader(set::title($label));

formPanel
(
    set::submitBtnText($label),
    $requireComment ? set::requiredFields('comment') : null,
    formGroup
    (
        set::width('1/2'),
        set::label($lang->statetransition->assignedTo),
        picker
        (
            set::name('assignedTo'),
            set::items($assignedToItems),
            set::value($assignedTo)
        )
    ),
    formGroup
    (
        set::label($lang->comment),
        editor
        (
            set::name('comment')
        )
    )
);

render();
