<?php
/*
 * JohnCMS NEXT Mobile Content Management System (http://johncms.com)
 *
 * For copyright and license information, please see the LICENSE.md
 * Installing the system or redistributions of files must retain the above copyright notice.
 *
 * @link        http://johncms.com JohnCMS Project
 * @copyright   Copyright (C) JohnCMS Community
 * @license     GPL-3
 */

defined('_IN_JOHNCMS') or die('Error: restricted access');

/** @var Interop\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var Johncms\User $systemUser */
$systemUser = $container->get(Johncms\User::class);

/** @var Johncms\Config $config */
$config = $container->get(Johncms\Config::class);

/** @var Johncms\Counters $counters */
$counters = $container->get('counters');

$mp = new Johncms\NewsWidget();

// Блок информации
$newscount = $mp->newscount;
if ($newscount != '0') {
	echo '<div class="phdr"><b>' . _t('Information', 'system') . '</b></div>';
	echo $mp->news;
	echo '<div class="menu"><a href="news/">' . _t('News archive', 'system') . '</a> (' . $newscount . ')</div>';
}


////////////////////////////////////////////////////////////
// Блок общения                                           //
////////////////////////////////////////////////////////////
//echo '<div class="phdr"><b>' . _t('Communication', 'system') . '</b></div>';

// Ссылка на гостевую

// Ссылка на Форум
if ($config->mod_forum || $systemUser->rights >= 7) {
    echo '<div class="phdr"><table width="100%"><td><b>' . _t('Forum', 'system') . '</b> (' . $counters->forum() . ')</td><td align="right"><a href="forum" class="pagenav">&gt;&gt;</a></td></table></div>';
    $req = $db->query("SELECT `forum`.*, `users`.`rights` FROM `forum` INNER JOIN `users` ON `forum`.`user_id` = `users`.`id` WHERE `type`='t'" . ($systemUser->rights >= 7 ? "" : " AND `close` != '1'") . " ORDER BY `vip` DESC, `time` DESC LIMIT $start, $kmess");
	$total = $req->rowCount();

	if ($total > 0) {
    	for ($i = 0; $res = $req->fetch(); ++$i) {
        	if ($res['close'])
            	echo '<div class="rmenu">';
        	else
            	echo $i % 2 ? '<div class="list2">' : '<div class="list1">';

	        $razd = $db->query("SELECT `id`, `refid`, `text` FROM `forum` WHERE `type` = 'r' AND `id` = '" . $res['refid'] . "' LIMIT 1")->fetch();
	        $frm = $db->query("SELECT `id`, `text` FROM `forum` WHERE `type`='f' AND `id` = '" . $razd['refid'] . "' LIMIT 1")->fetch();
	        $colmes = $db->query("SELECT `forum`.`from`, `forum`.`time`, `forum`.`user_id`, `users`.`rights` FROM `forum` INNER JOIN `users` ON `users`.`id` = `forum`.`user_id` WHERE `refid` = '" . $res['id'] . "' AND `type` = 'm'" . ($systemUser->rights >= 7 ? '' : " AND `close` != '1'") . " ORDER BY `forum`.`time` DESC");
	        $colmes1 = $colmes->rowCount();
	        $cpg = ceil($colmes1 / $kmess);
	        $nick = $colmes->fetch();
	        $icons = [
                        (isset($np) ? (!$res['vip'] ? $tools->image('op.gif') : '') : $tools->image('np.gif')),
                        ($res['vip'] ? $tools->image('pt.gif') : ''),
                        ($res['realid'] ? $tools->image('rate.gif') : ''),
                        ($res['edit'] ? $tools->image('tz.gif') : ''),
	                 ];
	        echo implode('', array_filter($icons));
	        echo '<a href="' . $tools->rewriteUrl($res['text'], $res['id']) . '">' . (empty($res['text']) ? '-----' : $res['text']) .
	            '</a>&#160;[' . $colmes1 . '] ';
	        if ($cpg > 1)
	            echo '&#160;<a href="' . $tools->rewriteUrl($res['text'], $res['id'], $cpg) . '">&gt;&gt;</a>';
	        echo '<div class="sub">';
	        if ($systemUser->isValid() && $systemUser->id != $res['user_id'])
                echo '<a href="../profile/?user=' . $res['user_id'] . '">' . $tools->displayUsername($res['from'], $res['rights']) . '</a>';
            else
                echo $tools->displayUsername($res['from'], $res['rights']);
            if ($colmes1 > 1)
                if ($systemUser->isValid() && $systemUser->id != $nick['user_id'])
                    echo '&#160;-&#160;<a href="../profile/?user=' . $nick['user_id'] . '">' . $tools->displayUsername($nick['from'], $nick['rights']) . '</a>';
                else
                    echo '&#160;-&#160;' . $tools->displayUsername($nick['from'], $nick['rights']);
	    	echo '<span class="gray"> (' . $tools->displayDate($nick['time']) . ')</span><br/>';
	        echo '<a href="' . $tools->rewriteUrl($frm['text'], $frm['id']) . '">' . $frm['text'] . '</a>&#160;/&#160;<a href="' . $tools->rewriteUrl($razd['text'], $razd['id']) . '">' . $razd['text'] . '</a></div></div>';
	    }
	} else {
	    echo '<div class="menu"><p>' . _t('The list is empty', 'system') . '</p></div>';
	}
	if ($total > $kmess) {
	    echo '<div class="topmenu">' . $tools->displaypagination('index.php?', $start, $total, $kmess) . '</div>' .
	        '<p><form action="index.php" method="get">' .
	        '<input type="text" name="page" size="2"/>' .
	        '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/>' .
	        '</form></p>';
	}
	}

////////////////////////////////////////////////////////////
//	TOP users
////////////////////////////////////////////////////////////

$mod = isset($_GET['top']) ? trim($_GET['top']) : false;
$menu = [
    (!$mod ? '<b>' . _t('Forum', 'system') . '</b>' : '<a href="index.php#topuser">' . _t('Forum', 'system') . '</a>'),
    ($mod == 'guest' ? '<b>' . _t('Guestbook', 'system') . '</b>' : '<a href="index.php?top=guest#topuser">' . _t('Guestbook', 'system') . '</a>'),
    ($mod == 'thank' ? '<b>Thanks</b>' : '<a href="index.php?top=thank#topuser">Thanks</a>')
];
switch ($mod) {
	case 'guest':
		echo '<div class="phdr"><b>Top Users</b> | ' . _t('Most active in Guestbook', 'system') . '</div>';
		$order = 'postguest';
		break;
	case 'thank':
		echo '<div class="phdr"><b>Top Users</b></a> | ' . _t('Most thanked', 'system') . '</div>';
		$order = 'postthank';
		break;
	default:
		echo '<div class="phdr"><b>Top Users</b></a> | ' . _t('Most active in Forum', 'system') . '</div>';
		$order = 'postforum';
		break;
}
echo '<div class="topmenu">' . implode(' | ', $menu) . '</div>';
$req = $db->query("SELECT `id`, `name`, `rights`, `$order` as `value` FROM `users` WHERE `$order` > 0 ORDER BY `$order` DESC LIMIT 9");
if ($req->rowCount($req)) {
	echo '<div class="menu"><table style="width: 100%;">';
	for($i = 1; $res = $req->fetch(); $i++) {
		echo '<tr><td>' . $tools->image('list/' . $i . '.png') . ' ';
		if ($systemUser->isValid() && $res['id'] != $systemUser->id)
			echo '<a href="profile/?user=' . $res['id'] . '">' . $tools->displayUsername($res['name'], $res['rights']) . '</a>';
		else
			echo '<b>' . $tools->displayUsername($res['name'], $res['rights']) . '</b>';
		echo '</td><td align="right">' . $res['value'] . '</td></tr>';
	}
	echo '</table></div>';
} else {
	echo '<div class="menu">' . _t('List of TOP users empty, please!', 'system') . '</div>';
}

////////////////////////////////////////////////////////////
// Show menu                                         //
////////////////////////////////////////////////////////////
echo '<div class="phdr"><b>Menu</b></div>';
if ($config->mod_guest || $systemUser->rights >= 7) {
    echo '<div class="menu"><a href="guestbook/index.php">' . _t('Guestbook', 'system') . '</a> (' . $counters->guestbook() . ')</div>';
}

if ($config->mod_down || $systemUser->rights >= 7) {
    echo '<div class="menu"><a href="downloads/">' . _t('Downloads', 'system') . '</a> (' . $counters->downloads() . ')</div>';
}

if ($config->mod_lib || $systemUser->rights >= 7) {
    echo '<div class="menu"><a href="library/">' . _t('Library', 'system') . '</a> (' . $counters->library() . ')</div>';
}


if ($systemUser->isValid() || $config->active) {
    echo '<div class="menu"><a href="users/index.php">' . _t('Users', 'system') . '</a> (' . $counters->users() . ')</div>' .
        '<div class="menu"><a href="album/index.php">' . _t('Photo Albums', 'system') . '</a> (' . $counters->album() . ')</div>';
}

echo '<div class="menu"><a href="help/">' . _t('Information, FAQ', 'system') . '</a></div>';

//module history online
$file_cache_online = 'online_log.dat';
$current_time = time();
$current_time_es = $current_time - 300;
$users = $db->query('SELECT COUNT(*) FROM `users` WHERE `lastdate` > ' . $current_time_es)->fetchColumn();
$guests = $db->query('SELECT COUNT(*) FROM `cms_sessions` WHERE `lastdate` > ' . $current_time_es)->fetchColumn();
$total_onl = $users + $guests;
$count = [
            'users' => 0,
            'guests' => 0,
            'total' => 0
         ];
if (file_exists($file_cache_online)) {
    $f = file($file_cache_online);
    $count['users'] = abs(intval(trim($f[0])));
    $count['guests'] = abs(intval(trim($f[1])));
    $count['time'] = intval(trim($f[2]));
    $count['total'] = $count['users'] + $count['guests'];
}
if ($total_onl > $count['total']) {
    $count['users'] = $users;
    $count['guests'] = $guests;
    $count['time'] = $current_time;
    $count['total'] = $total_onl;
    $putContent = [$count['users'], $count['guests'], $count['time']];
    $putContent = implode("\n", $putContent);
    file_put_contents($file_cache_online, $putContent);
}
echo '<div class="phdr"><table width="100%"><tr><td><b>' . _t('Users Online', 'system') . '</b></td><td align="right"><a href="users/index.php?act=online" class="pagenav">&gt;&gt;</a></td></tr></table></div>' . 
	'<div class="menu">' . sprintf(_t('There are currently %d users online: %d members and %d guests<br/>Most users ever online was %d (%d members and %d guests) at %s', 'system'), $total_onl, $users, $guests, $count['total'], $count['users'], $count['guests'], date('H:i d/m/Y', $count['time'] + $config->timeshift * 3600)) . '</div>';