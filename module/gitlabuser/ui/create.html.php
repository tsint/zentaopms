<?php
declare(strict_types=1);
/**
 * The create view file of gitlabuser module of ZenTaoPMS.
 *
 * @package    gitlabuser
 */
namespace zin;

formPanel
(
    set::id('gitlabuserCreateForm'),
    set::title($lang->gitlabuser->create),
    set::submitBtnText($lang->save),
    formRow
    (
        formGroup
        (
            set::width('300px'),
            set::name('gitlabAccount'),
            set::label($lang->gitlabuser->gitlabAccount)
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
            set::items($users)
        )
    )
);
