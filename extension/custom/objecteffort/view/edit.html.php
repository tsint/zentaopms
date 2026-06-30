<?php
declare(strict_types=1);
namespace zin;

modalHeader(set::title($lang->objecteffort->edit), set::entityID($effort->id));

formPanel
(
    set::actions(array('submit')),
    count($executions) > 1 ? formRow
    (
        formGroup(set::name('execution'), set::label($lang->objecteffort->execution), set::control('picker'), set::items($executions), set::value($effort->execution), set::required(true))
    ) : null,
    count($executions) == 1 ? input(set::type('hidden'), set::name('execution'), set::value((int)key($executions))) : null,
    !$executions && count($projects) > 1 ? formRow
    (
        formGroup(set::name('project'), set::label($lang->objecteffort->project), set::control('picker'), set::items($projects), set::value($effort->project), set::required(true))
    ) : null,
    !$executions && count($projects) == 1 ? input(set::type('hidden'), set::name('project'), set::value((int)key($projects))) : null,
    formRow
    (
        formGroup(set::name('date'), set::label($lang->objecteffort->date), set::control('date'), set::value($effort->date), set::required(true)),
        formGroup(set::name('estimate'), set::label($lang->objecteffort->estimate), set::control('input'), set::value(helper::formatHours($effort->estimate))),
        formGroup(set::name('consumed'), set::label($lang->objecteffort->consumed), set::control('input'), set::value(helper::formatHours($effort->consumed)), set::required(true)),
        formGroup(set::name('left'), set::label($lang->objecteffort->left), set::control('input'), set::value(helper::formatHours($effort->left)), set::required(false))
    ),
    formRow
    (
        formGroup(set::name('work'), set::label($lang->objecteffort->work), set::control('textarea'), set::value($effort->work), set::width('full'))
    )
);
