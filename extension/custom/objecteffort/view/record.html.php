<?php
declare(strict_types=1);
namespace zin;

$isEn = $app->getClientLang() == 'en';
modalHeader
(
    set::title($lang->objecteffort->record),
    set::entityID($objectID),
    to::suffix
    (
        span
        (
            setClass('flex gap-x-2 mx-3 nowrap'),
            $lang->objecteffort->estimate,
            span
            (
                setClass('label secondary-pale'),
                helper::formatHours($summary->estimate) . 'h'
            )
        ),
        span
        (
            setClass('flex gap-x-2 pr-4 nowrap'),
            $lang->objecteffort->consumed,
            span
            (
                setClass('label warning-pale'),
                helper::formatHours($summary->consumed) . 'h'
            )
        )
    )
);

$defaultRows = array();
for($i = 0; $i < 3; $i ++)
{
    $row = new \stdclass();
    $row->date = helper::today();
    $row->consumed = '';
    $row->left = '';
    $row->work = '';
    $defaultRows[] = $row;
}

if($efforts)
{
    $rows = '';
    foreach($efforts as $effort)
    {
        $canOperate = $this->objecteffort->canOperate($effort);
        $rows .= '<tr>';
        $rows .= '<td>' . (int)$effort->id . '</td>';
        $rows .= '<td>' . $effort->date . '</td>';
        $rows .= '<td>' . zget($users, $effort->account, $effort->account) . '</td>';
        $rows .= '<td>' . htmlSpecialString($effort->work) . '</td>';
        $rows .= '<td>' . helper::formatHours($effort->consumed) . '</td>';
        $rows .= '<td>' . helper::formatHours($effort->left) . '</td>';
        $rows .= '<td>';
        if(common::hasPriv('objecteffort', 'edit') && $canOperate) $rows .= "<a class='btn ghost square size-sm' data-toggle='modal' href='" . createLink('objecteffort', 'edit', "effortID={$effort->id}") . "'><i class='icon icon-edit'></i></a>";
        if(common::hasPriv('objecteffort', 'delete') && $canOperate) $rows .= "<a class='btn ghost square size-sm ajax-submit' data-confirm='{$lang->objecteffort->confirmDelete}' href='" . createLink('objecteffort', 'delete', "effortID={$effort->id}") . "'><i class='icon icon-trash'></i></a>";
        $rows .= '</td></tr>';
    }

    h::table
    (
        setClass('table condensed bordered mb-4'),
        h::tr
        (
            h::th(width('40px'), $lang->idAB),
            h::th(width('100px'), $lang->objecteffort->date),
            h::th(width('100px'), $lang->objecteffort->account),
            h::th($lang->objecteffort->work),
            h::th(width('70px'), $lang->objecteffort->consumed),
            h::th(width('70px'), $lang->objecteffort->left),
            h::th(width('80px'), $lang->objecteffort->actions)
        ),
        $rows
    );
}

formBatchPanel
(
    setID('objecteffortBatchForm'),
    set::title($lang->objecteffort->record),
    set::shadow(!isAjaxRequest('modal')),
    set::actions(array('submit')),
    set::data($defaultRows),
    set::maxRows(10),
    formBatchItem
    (
        set::name('id'),
        set::label($lang->idAB),
        set::control('index'),
        set::width('32px')
    ),
    count($executions) > 1 ? formBatchItem
    (
        set::required(true),
        set::name('execution'),
        set::label($lang->objecteffort->execution),
        set::width('160px'),
        set::control('picker'),
        set::items($executions)
    ) : null,
    !$executions && count($projects) > 1 ? formBatchItem
    (
        set::required(true),
        set::name('project'),
        set::label($lang->objecteffort->project),
        set::width('160px'),
        set::control('picker'),
        set::items($projects)
    ) : null,
    formBatchItem
    (
        set::required(true),
        set::name('date'),
        set::label($lang->objecteffort->date),
        set::width('130px'),
        set::control(array('control' => 'date', 'id' => '$GID')),
        set::value(helper::today())
    ),
    formBatchItem
    (
        set::name('work'),
        set::label($lang->objecteffort->work),
        set::width('auto'),
        set::control('textarea')
    ),
    formBatchItem
    (
        set::required(true),
        set::name('consumed'),
        set::label($lang->objecteffort->consumed . ($isEn ? ' h' : '')),
        set::width('90px'),
        set::control(array('type' => 'inputControl', 'suffix' => 'h', 'suffixWidth' => 20))
    ),
    formBatchItem
    (
        set::required(false),
        set::name('left'),
        set::label($lang->objecteffort->left . ($isEn ? ' h' : '')),
        set::width('90px'),
        set::control(array('type' => 'inputControl', 'suffix' => 'h', 'suffixWidth' => 20))
    ),
    count($executions) == 1 ? formHidden('execution', (int)key($executions)) : null,
    !$executions && count($projects) == 1 ? formHidden('project', (int)key($projects)) : null
);

render();
