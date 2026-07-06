#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel::getFieldsForStatus();
timeout=0
cid=0

- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'bug', 'closed' 属性assignedTo @closed
- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'story', 'closed' 属性stage @closed
- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'task', 'closed' 属性assignedTo @closed
- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'bug', 'active' 属性activatedDate @now
- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'story', 'active' 属性activatedDate @now
- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'task', 'active' 属性activatedDate @now
- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'bug', 'resolved' 属性resolvedBy @user
- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'task', 'done' 属性finishedBy @user
- 执行statetransitionTest模块的getFieldsForStatusTest方法，参数是'task', 'cancel' 属性canceledBy @user
*/

include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

$statetransitionTest = new statetransitionModelTest();

r($statetransitionTest->getFieldsForStatusTest('bug', 'closed')) && p('assignedTo') && e('closed');
r($statetransitionTest->getFieldsForStatusTest('story', 'closed')) && p('stage') && e('closed');
r($statetransitionTest->getFieldsForStatusTest('task', 'closed')) && p('assignedTo') && e('closed');
r($statetransitionTest->getFieldsForStatusTest('bug', 'active')) && p('activatedDate') && e('now');
r($statetransitionTest->getFieldsForStatusTest('story', 'active')) && p('activatedDate') && e('now');
r($statetransitionTest->getFieldsForStatusTest('task', 'active')) && p('activatedDate') && e('now');
r($statetransitionTest->getFieldsForStatusTest('bug', 'resolved')) && p('resolvedBy') && e('user');
r($statetransitionTest->getFieldsForStatusTest('task', 'done')) && p('finishedBy') && e('user');
r($statetransitionTest->getFieldsForStatusTest('task', 'cancel')) && p('canceledBy') && e('user');