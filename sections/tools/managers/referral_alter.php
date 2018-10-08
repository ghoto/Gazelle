<?php
authorize();

if (!check_perms('admin_manage_referrals')) {
	error(403);
}

$P = db_array($_POST);
$ReferralManager = new \Gazelle\Manager\Referral($DB, $Cache);

if ($_POST['submit'] == 'Delete') {
	if (!is_number($_POST['id']) || $_POST['id'] == '') {
		error(0);
	}

	$ReferralManager->deleteAccount($_POST['id']);
} else {
	$Val->SetFields('site', '1', 'string', 'The site must be set, and has a max length of 30 characters', array('maxlength' => 30));
	$Val->SetFields('url', '1', 'string', 'The URL must be set, and has a max length of 30 characters', array('maxlength' => 30));
	$Val->SetFields('user', '1', 'string', 'The username must be set, and has a max length of 20 characters', array('maxlength' => 20));
	$Val->SetFields('password', '0', 'string', 'The password must be set, and has a max length of 128 characters', array('maxlength' => 128));
	$Val->SetFields('active', '1', 'checkbox', '');
	$Err = $Val->ValidateForm($_POST);

	if (substr($P['url'], -1) !== '/') {
		$P['url'] .= '/';
	}

	if ($_POST['submit'] == 'Create') {
		$ReferralManager->createAccount($P['site'], $P['url'], $P['user'], $P['password'],
		   	$P['active'] == 'on' ? 1 : 0, $P['type'], $P['cookie']);
	} elseif ($_POST['submit'] == 'Edit') {
		if (!is_number($_POST['id']) || $_POST['id'] == '') {
			error(0);
		}

		$account = $ReferralManager->getAccount($P['id']);
		if ($account == null) {
			error(0);
		}

		$ReferralManager->updateAccount($P['id'], $P['site'], $P['url'], $P['user'],
			$P['password'], $P['active'] == 'on' ? 1 : 0, $P['type'], $P['cookie']);
	}
}

header('Location: tools.php?action=referral_accounts');
?>
