<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

$privMan = new Gazelle\Manager\Privilege;

$privilege = null;
if (isset($_REQUEST['id']) && $_REQUEST['id'] !== 'new') {
    $privilege = $privMan->findById((int)$_REQUEST['id']);
    if (is_null($privilege)) {
        header("Location: tools.php?action=permissions");
        exit;
    }
}

echo $Twig->render('admin/privilege-edit.twig', [
    'edited'     => isset($usersAffected),
    'edit_total' => $usersAffected ?? 0,
    'js'         => (new Gazelle\Util\Validator)->generateJS('permissionsform'),
    'group_list' => $privMan->staffGroupList(),
    'privilege'  => $privilege,
    'viewer'     => $Viewer,
]);
