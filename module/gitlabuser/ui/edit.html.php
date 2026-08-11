<?php
declare(strict_types=1);
/**
 * The edit view file of gitlabuser module of ZenTaoPMS.
 *
 * @package    gitlabuser
 */
namespace zin;

formPanel
(
    set::id('gitlabuserEditForm'),
    set::title($lang->gitlabuser->editAction),
    set::submitBtnText($lang->save),
    formRow
    (
        formGroup
        (
            set::width('300px'),
            set::name('gitlabAccount'),
            set::label($lang->gitlabuser->gitlabAccount),
            set::value($gitlabUser->gitlabAccount)
        )
    ),
    formRow
    (
        formGroup
        (
            set::width('300px'),
            set::label($lang->gitlabuser->zentaoAccount),
            set::control('picker'),
            set::name('zentaoAccount'),
            set::items($users),
            set::value($gitlabUser->zentaoAccount)
        )
    )
);
