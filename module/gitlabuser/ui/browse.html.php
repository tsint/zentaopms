<?php
declare(strict_types=1);
/**
 * The browse view file of gitlabuser module of ZenTaoPMS.
 *
 * @package    gitlabuser
 */
namespace zin;

featureBar(set::current('all'));

$canCreate  = hasPriv('gitlabuser', 'create');
$createLink = $this->createLink('gitlabuser', 'create');

$tableData = initTableData($gitlabUsers, $this->config->gitlabuser->dtable->fieldList, $this->gitlabuser);

toolbar
(
    $canCreate ? btn
    (
        setClass('btn primary'),
        set::icon('plus'),
        set::url($createLink),
        set('data-toggle', 'modal'),
        set('data-size', 'sm'),
        $lang->gitlabuser->create
    ) : null
);

dtable
(
    set::userMap($users),
    set::cols(array_values($this->config->gitlabuser->dtable->fieldList)),
    set::data($tableData),
    set::orderBy($orderBy)
);

render();
