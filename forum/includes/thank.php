<?php

/**
 * @author      AriTuan http://gocmaster.com
 */

defined('_IN_JOHNCMS') or die('Error: restricted access');

if (!$id
    || !$systemUser->isValid()
    || isset($systemUser->ban['1'])
    || isset($systemUser->ban['11'])
) {
    require('../system/head.php');
    echo $tools->displayError(_t('Access forbidden'));
    require('../system/end.php');
    exit;
}
$req = $db->query("SELECT `user_id`, `refid` FROM `forum` WHERE `id` = '$id' AND `type` = 'm' AND `close` != '1';");
if ($req->rowCount() == 1) {
	$res = $req->fetch();
	$refp = isset($_GET['refp']) ? abs(intval($_GET['refp'])) : 1;
	$titlPost = $db->query("SELECT `text` FROM `forum` WHERE `id` = '" . $res['refid'] . "' AND `type` = 't';")->fetch();
	$refURL = $tools->rewriteUrl($titlPost['text'], $res['refid'], $refp) . '#' . $id;
	$checkThank = $db->query("SELECT * FROM `cms_forum_thank` WHERE `fid` = '$id' AND `user_id` = '$systemUser->id';")->rowCount();
	if ($checkThank == 1 || $systemUser->id == $res['user_id']) {
		header("Location: $refURL");
		exit;
	} else {
		$db->prepare('INSERT INTO `cms_forum_thank` SET
			`user_id` = ?,
			`fid` = ?,
			`time` = ?
		')->execute([
		$systemUser->id,
		$id,
		time()
		]);
		$db->exec("UPDATE `users` SET `postthank` = `postthank` + 1 WHERE `id` = '" . $res['user_id'] . "';");
		
		//Forum Notification
		$msg_mail = '[url=' . $config->homeurl . '/users/profile.php?user=' . $systemUser->id . ']'. $tools->displayUsername($systemUser->name, $systemUser->rights, true) . '[/url] ' . _t('thanked this post') . ': [url=' . $refURL . ']' . $titlPost['text'] . '[/url]';
		$check_notice_exist = $db->query("SELECT * FROM `cms_mail` WHERE `sys` = 1 AND `sys_ext` = '$id' AND `them` = 'Forum notification'")->rowCount();
		if ($check_notice_exist) {
			$req_user_thank = $db->query("SELECT `users`.`id`, `users`.`name`, `users`.`rights` FROM `users` INNER JOIN `cms_forum_thank` ON `users`.`id` = `cms_forum_thank`.`user_id` WHERE `cms_forum_thank`.`fid` = '$id' ORDER BY `cms_forum_thank`.`time` DESC LIMIT 4;");
			$count_user_thank = $req_user_thank->rowCount();
			$list = [];
			for($i = 1; $resThank = $req_user_thank->fetch(); $i++) {
				$list[] = '[url=' . $config->homeurl . '/profile/?user=' . $resThank['id'] . ']' . $tools->displayUsername($resThank['name'], $resThank['rights'], true) . '[/url]';
				if ($i == 3 && $count_user_thank > $i) {
					$list[] = '...';
					break;
				}
			}
			$msg_mail = implode(', ', $list) . ' ' . _t('thanked this post') . ': [url=' . $refURL . ']' . $titlPost['text'] . '[/url]';
			$db->exec("UPDATE `cms_mail` SET `text` = " . $db->quote($msg_mail) . ", `time` = '" . time() . "', `read` = '0' WHERE `sys` = '1' AND `sys_ext` = '$id' AND `them` = 'Forum notification';");
		} else {
			$db->prepare('INSERT INTO `cms_mail` SET
                     `user_id` = ?, 
                     `from_id` = ?, 
                     `them`=\'Forum notification\',
                     `text` = ?,
                     `sys`=\'1\',
                     `sys_ext` = ?,
                     `time` = ?
                     ')->execute([
                     $systemUser->id,
                     $res['user_id'],
                     $msg_mail,
                     $id,
                     time()
                     ]);
		}
		
		header("Location: $refURL");
		exit;
	}
} else {
	require('../system/head.php');
	echo $tools->displayError($lng['error_wrong_data']);
    require('../system/end.php');
    exit;
}