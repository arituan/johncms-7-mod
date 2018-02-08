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

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Johncms\User $systemUser */
$systemUser = $container->get(Johncms\User::class);

/** @var Johncms\Tools $tools */
$tools = $container->get('tools');

/** @var Johncms\Config $config */
$config = $container->get(Johncms\Config::class);

// Закрываем доступ для определенных ситуаций
if (!$id
    || !$systemUser->isValid()
    || isset($systemUser->ban[1])
    || isset($systemUser->ban[11])
    || (!$systemUser->rights && $config['mod_forum'] == 3)
) {
    require('../system/head.php');
    echo $tools->displayError(_t('Access forbidden'));
    require('../system/end.php');
    exit;
}

// Вспомогательная Функция обработки ссылок форума
function forum_link($m)
{
    global $db, $config;

    if (!isset($m[3])) {
        return '[url=' . $m[1] . ']' . $m[2] . '[/url]';
    } else {
        $rwurl = false;
        $p = parse_url($m[3]);
        if ($config->rewriteurl && preg_match('|^' . $config->homeurl . '\/forum\/([A-Za-z0-9\-]+)\-t([0-9]+)\.html$|is', $m[3], $parse))
            $thid = abs(intval($parse[2]));
            
            if('http://' . $p['host'] . (isset($p['path']) ? $p['path'] : '') . '?id=' == $config->homeurl . '/forum/index.php?id=') {
                $thid = abs(intval(preg_replace('/(.*?)id=/si', '', $m[3])));
                if ($config->rewriteurl)
                    $rwurl = true;
            }
        if (isset($thid)) {
            $req = $db->query("SELECT `text` FROM `forum` WHERE `id`= '$thid' AND `type` = 't' AND `close` != '1'");

            if ($req->rowCount()) {
                $res = $req->fetch();
                $name = strtr($res['text'], [
                    '&quot;' => '',
                    '&amp;'  => '',
                    '&lt;'   => '',
                    '&gt;'   => '',
                    '&#039;' => '',
                    '['      => '',
                    ']'      => '',
                ]);

                if (mb_strlen($name) > 100) {
                    $name = $tools->substring($name, 100) . '...';
                }
                if ($rwurl)
                    $m[3] = $tools->rewriteurl($req['text'], $thid);
                return '[url=' . $m[3] . ']' . $name . '[/url]';
            } else {
                return $m[3];
            }
        } else {
            return $m[3];
        }
    }
}

// Проверка на флуд
$flood = $tools->antiflood();

if ($flood) {
    require('../system/head.php');
    echo $tools->displayError(sprintf(_t('You cannot add the message so often<br>Please, wait %d sec.'), $flood), '<a href="index.php?id=' . $id . '&amp;start=' . $start . '">' . _t('Back') . '</a>');
    require('../system/end.php');
    exit;
}

$headmod = 'forum,' . $id . ',1';
//$type1 = $db->query("SELECT * FROM `forum` WHERE `id` = '$id'")->fetch();

switch ($type1['type']) {
    case 't':
        require('../system/head.php');
        // Добавление простого сообщения
        if (($type1['edit'] == 1 || $type1['close'] == 1) && $systemUser->rights < 7) {
            echo $tools->displayError(_t('You cannot write in a closed topic'), '<a href="index.php?id=' . $id . '">' . _t('Back') . '</a>');
            require('../system/end.php');
            exit;
        }
        $msg = isset($_POST['msg']) ? trim($_POST['msg']) : '';
        if (isset($_POST['submit'])
            && !empty($_POST['msg'])
            && isset($_POST['token'])
            && isset($_SESSION['token'])
            && trim($_POST['token']) == $_SESSION['token']
        ) {
            unset($_SESSION['token']);
            $msg = preg_replace_callback('~\\[url=(http://.+?)\\](.+?)\\[/url\\]|(http://(www.)?[0-9a-zA-Z\.-]+\.[0-9a-zA-Z]{2,6}[0-9a-zA-Z/\?\.\~&amp;_=/%-:#]*)~', 'forum_link', $msg);
            $error = [];
            // Проверяем на минимальную длину
            if (mb_strlen($msg) < 4) {
                $error[] = _t('Text is too short');
            }

            // Проверяем, не повторяется ли сообщение?
            $req = $db->query("SELECT `text` FROM `forum` WHERE `user_id` = '" . $systemUser->id . "' AND `type` = 'm' ORDER BY `time` DESC");

            if ($req->rowCount()) {
                $res = $req->fetch();
                if ($msg == $res['text']) {
                    $error[] = _t('Message already exists');
                }
            }

            // Удаляем фильтр, если он был
            if (isset($_SESSION['fsort_id']) && $_SESSION['fsort_id'] == $id) {
                unset($_SESSION['fsort_id']);
                unset($_SESSION['fsort_users']);
            }
            if (!$error) {
            /** @var Johncms\Environment $env */
            $env = App::getContainer()->get('env');
            // Добавляем сообщение в базу
            $db->prepare('
              INSERT INTO `forum` SET
              `refid` = ?,
              `type` = \'m\',
              `time` = ?,
              `user_id` = ?,
              `from` = ?,
              `ip` = ?,
              `ip_via_proxy` = ?,
              `soft` = ?,
              `text` = ?,
              `edit` = \'\',
              `curators` = \'\'
            ')->execute([
                $id,
                time(),
                $systemUser->id,
                $systemUser->name,
                $env->getIp(),
                $env->getIpViaProxy(),
                $env->getUserAgent(),
                $msg,
            ]);

            $fadd = $db->lastInsertId();

            // Обновляем время топика
            $db->exec("UPDATE `forum` SET
                `time` = '" . time() . "'
                WHERE `id` = '$id'
            ");

            // Обновляем статистику юзера
            $db->exec("UPDATE `users` SET
                `postforum`='" . ($systemUser->postforum + 1) . "',
                `lastpost` = '" . time() . "'
                WHERE `id` = '" . $systemUser->id . "'
            ");
            

            // Вычисляем, на какую страницу попадает добавляемый пост
            $page = $set_forum['upfp'] ? 1 : ceil($db->query("SELECT COUNT(*) FROM `forum` WHERE `type` = 'm' AND `refid` = '$id'" . ($systemUser->rights >= 7 ? '' : " AND `close` != '1'"))->fetchColumn() / $kmess);

            ///////////////////////////////////////////////
            //module notification forum
            ///////////////////////////////////////////////
            $req = $db->query("SELECT DISTINCT `forum`.`user_id`, `forum`.`from`, `users`.`rights` FROM `forum` INNER JOIN `users` ON `forum`.`user_id` = `users`.`id` WHERE `forum`.`type` = 'm' AND `forum`.`user_id` != '" . $systemUser->id . "' AND `forum`.`refid` = '$id' ORDER BY `forum`.`id` ASC LIMIT 20;");
            $link_topic = $tools->rewriteUrl($resText, $id, $page) . '#' . $fadd;
            $listUserName = [];
            $listUserName[] = '[url=' . $config->homeurl . '/profile/?user=' . $systemUser->id . ']' . $tools->displayUsername($systemUser->name, $systemUser->rights, true) . '[/url]';
            $msg_no_new = '[url=' . $config->homeurl . '/profile/?user=' . $systemUser->id . ']' . $tools->displayUsername($systemUser->name, $systemUser->rights, true) . '[/url] ' . _t('has written in topic that you interested') . ': [url=' . $link_topic . ']' . strip_tags($resText) . '[/url]';
            $countUser = $req->rowCount();
            if ($countUser) {
                $userid = [];
                $username = [];
                $userrights = [];
                while ($res = $req->fetch()) {
                    $userid[] = $res['user_id'];
                    $username[] = $res['from'];
                    $userrights[] = $res['rights'];
                }
                for ($i = $countUser-1; $i >= 0; $i--) {
                    $check_notice_exist = $db->query("SELECT * FROM `cms_mail` WHERE `sys` = 1 AND `sys_ext` = '$id' AND `them` = 'Forum notification' AND `from_id` = '" . $userid[$i] . "'")->rowCount();
                    if ($check_notice_exist) {
                        $msg_no = implode(', ', $listUserName) . ' ' . _t('has written in topic that you interested') . ': [url=' . $link_topic . ']' . strip_tags($resText) . '[/url]';
                        $db->exec("UPDATE `cms_mail` SET `text` = " . $db->quote($msg_no) . ", `time` = '" . time() . "', `read` = '0' WHERE `from_id` = '" . $userid[$i] . "' AND `sys` = '1' AND `sys_ext` = '$id' AND `them` = 'Forum notification';");
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
                             $userid[$i],
                             $msg_no_new,
                             $id,
                             time()
                            ]);
                    }
                    if ($i >= $countUser - 5)
                        $listUserName[] = '[url=' . $config->homeurl . '/profile/?user=' . $userid[$i] . ']' . $tools->displayUsername($username[$i], $userrights[$i], true) . '[/url]';
                    if ($i == $countUser - 6)
                        $listUserName[] = '...';
                }
            }
            //module tag users
            if (preg_match_all('#(\s|^)\@([a-z\d\-\@\*\(\)\?\!\~\_\=\[\]\.]{2,15})#i', $msg, $list)) {
                $list = array_unique($list[2]); //get username in @username and remove duplicate values
                $max_users = count($list) > 10 ? 10 : count($list);
                $msg_no = '[url=' . $config->homeurl . '/profile/?user=' . $systemUser->id . ']' . $tools->displayUsername($systemUser->name, $systemUser->rights, true) . '[/url] ' . _t('has mentions you in a topic') . ': [url=' . $link_topic . ']' . strip_tags($resText) . '[/url]';
                for ($i=0; $i < $max_users; $i++) {
                    $username = $db->quote(trim($list[$i]));
                    if ($username == $systemUser->name) continue;
                    $req = $db->query("SELECT `id` FROM `users` WHERE `name_lat` = " . $username . " LIMIT 1;");
                    if ($req->rowCount() == 0) continue;
                    else {
                        $user = $req->fetch();
                        $db->prepare('INSERT INTO `cms_mail` SET
                         `user_id` = ?, 
                         `from_id` = ?, 
                         `them`=\'You tagged\',
                         `text` = ?,
                         `sys`=\'1\',
                         `sys_ext` = ?,
                         `time` = ?
                         ')->execute([
                             $systemUser->id,
                             $user['id'],
                             $msg_no,
                             $fadd,
                             time()
                            ]);

                    }
                }
            }

            if (isset($_POST['addfiles'])) {
                header("Location: index.php?id=$fadd&act=addfile");
            } else {
                header('Location: ' . $link_topic);
            }
            exit;
        }
        }
            $msg_pre = $tools->checkout($msg, 1, 1);
            $msg_pre = $tools->smilies($msg_pre, $systemUser->rights ? 1 : 0);
            $msg_pre = preg_replace('#\[c\](.*?)\[/c\]#si', '<div class="quote">\1</div>', $msg_pre);
            echo '<div class="phdr"><b>' . _t('Topic') . ':</b> ' . $type1['text'] . '</div>';
            if ($error)
                echo $tools->displayError(implode('<br />', $error));
            if ($msg && !isset($_POST['submit'])) {
                echo '<div class="list1">' . $tools->displayUser($systemUser, ['iphide' => 1, 'header' => '<span class="gray">(' . $tools->displayDate(time()) . ')</span>', 'body' => $msg_pre]) . '</div>';
            }

            echo '<form name="form" action="index.php?act=say&amp;id=' . $id . '&amp;start=' . $start . '" method="post"><div class="gmenu">' .
                '<p><h3>' . _t('Message') . '</h3>';
            echo '</p><p>' . $container->get('bbcode')->buttons('form', 'msg');
            echo '<textarea rows="' . $systemUser->getConfig()->fieldHeight . '" name="msg">' . (empty($_POST['msg']) ? '' : $tools->checkout($msg)) . '</textarea></p>' .
                '<p><input type="checkbox" name="addfiles" value="1" ' . (isset($_POST['addfiles']) ? 'checked="checked" ' : '') . '/> ' . _t('Add File');

            $token = substr(md5(mt_rand(1000, 100000)), 0, rand(28, 32));
            $_SESSION['token'] = $token;
            echo '</p><p>' .
                '<input type="submit" name="submit" value="' . _t('Send') . '" style="width: 107px; cursor: pointer"/> ' .
                ($set_forum['preview'] ? '<input type="submit" value="' . _t('Preview') . '" style="width: 107px; cursor: pointer"/>' : '') .
                '<input type="hidden" name="token" value="' . $token . '"/>' .
                '</p></div></form>';

        echo '<div class="phdr"><a href="../help/?act=smileys">' . _t('Smilies') . '</a></div>' .
            '<p><a href="index.php?id=' . $id . '&amp;start=' . $start . '">' . _t('Back') . '</a></p>';
        break;

    case 'm':
        // Добавление сообщения с цитированием поста
        $th = $type1['refid'];
        $th1 = $db->query("SELECT `id`, `text`, `edit`, `close` FROM `forum` WHERE `id` = '$th'")->fetch();

        if (($th1['edit'] == 1 || $th1['close'] == 1) && $systemUser->rights < 7) {
            require('../system/head.php');
            echo $tools->displayError(_t('You cannot write in a closed topic'), '<a href="' . $tools->rewriteUrl($th1['text'], $th1['id']) . '">' . _t('Back') . '</a>');
            require('../system/end.php');
            exit;
        }

        if ($type1['user_id'] == $systemUser->id) {
            require('../system/head.php');
            echo $tools->displayError('You can not reply your message', '<a href="' . $tools->rewriteUrl($th1['text'], $th1['id']) . '">' . _t('Back') . '</a>');
            require('../system/end.php');
            exit;
        }

        $shift = ($config['timeshift'] + $systemUser->getConfig()->timeshift) * 3600;
        $vr = date("d.m.Y / H:i", $type1['time'] + $shift);
        $msg = isset($_POST['msg']) ? trim($_POST['msg']) : '';
        $txt = isset($_POST['txt']) ? intval($_POST['txt']) : false;

        if (!empty($_POST['citata'])) {
            // Если была цитата, форматируем ее и обрабатываем
            $citata = isset($_POST['citata']) ? trim($_POST['citata']) : '';
            $citata = $container->get('bbcode')->notags($citata);
            $citata = preg_replace('#\[c\](.*?)\[/c\]#si', '', $citata);
            $citata = $tools->substring($citata, 300);
            $tp = date("d.m.Y H:i", $type1['time']);
            $msg = '[c][url=' . $config['homeurl'] . '/forum/index.php?act=post&id=' . $type1['id'] . ']#[/url] ' . $type1['from'] . ' ([time]' . $tp . "[/time])\n" . $citata . '[/c]' . $msg;
        } elseif (isset($_POST['txt'])) {
            // Если был ответ, обрабатываем реплику
            switch ($txt) {
                case 2:
                    $repl = $type1['from'] . ', ' . _t('I am glad to answer you') . ', ';
                    break;

                case 3:
                    $repl = $type1['from'] . ', ' . _t('respond to Your message') . ' ([url=' . $config['homeurl'] . '/forum/index.php?act=post&id=' . $type1['id'] . ']' . $vr . '[/url]): ';
                    break;

                default :
                    $repl = $type1['from'] . ', ';
            }
            $msg = $repl . ' ' . $msg;
        }

        //Обрабатываем ссылки
        $msg = preg_replace_callback('~\\[url=(http://.+?)\\](.+?)\\[/url\\]|(http://(www.)?[0-9a-zA-Z\.-]+\.[0-9a-zA-Z]{2,6}[0-9a-zA-Z/\?\.\~&amp;_=/%-:#]*)~', 'forum_link', $msg);

        if (isset($_POST['submit'])
            && isset($_POST['token'])
            && isset($_SESSION['token'])
            && $_POST['token'] == $_SESSION['token']
        ) {
            if (empty($_POST['msg'])) {
                require('../system/head.php');
                echo $tools->displayError(_t('You have not entered the message'), '<a href="index.php?act=say&amp;id=' . $th . (isset($_GET['cyt']) ? '&amp;cyt' : '') . '">' . _t('Repeat') . '</a>');
                require('../system/end.php');
                exit;
            }

            // Проверяем на минимальную длину
            if (mb_strlen($msg) < 4) {
                require('../system/head.php');
                echo $tools->displayError(_t('Text is too short'), '<a href="' . $tools->rewriteUrl($resText, $id) . '">' . _t('Back') . '</a>');
                require('../system/end.php');
                exit;
            }

            // Проверяем, не повторяется ли сообщение?
            $req = $db->query("SELECT `text` FROM `forum` WHERE `user_id` = '" . $systemUser->id . "' AND `type` = 'm' ORDER BY `time` DESC LIMIT 1");

            if ($req->rowCount()) {
                $res = $req->fetch();

                if ($msg == $res['text']) {
                    require('../system/head.php');
                    echo $tools->displayError(_t('Message already exists'), '<a href="index.php?id=' . $th . '&amp;start=' . $start . '">' . _t('Back') . '</a>');
                    require('../system/end.php');
                    exit;
                }
            }

            // Удаляем фильтр, если он был
            if (isset($_SESSION['fsort_id']) && $_SESSION['fsort_id'] == $th) {
                unset($_SESSION['fsort_id']);
                unset($_SESSION['fsort_users']);
            }

            unset($_SESSION['token']);

            /** @var Johncms\Environment $env */
            $env = App::getContainer()->get('env');

            // Добавляем сообщение в базу
            $db->prepare('
              INSERT INTO `forum` SET
              `refid` = ?,
              `type` = \'m\',
              `time` = ?,
              `user_id` = ?,
              `from` = ?,
              `ip` = ?,
              `ip_via_proxy` = ?,
              `soft` = ?,
              `text` = ?,
              `edit` = \'\',
              `curators` = \'\'
            ')->execute([
                $th,
                time(),
                $systemUser->id,
                $systemUser->name,
                $env->getIp(),
                $env->getIpViaProxy(),
                $env->getUserAgent(),
                $msg,
            ]);

            $fadd = $db->lastInsertId();

            // Обновляем время топика
            $db->exec("UPDATE `forum`
                SET `time` = '" . time() . "'
                WHERE `id` = '$th'
            ");

            // Обновляем статистику юзера
            $db->exec("UPDATE `users` SET
                `postforum`='" . ($systemUser->postforum + 1) . "',
                `lastpost` = '" . time() . "'
                WHERE `id` = '" . $systemUser->id . "'
            ");

            // Вычисляем, на какую страницу попадает добавляемый пост
            $page = $set_forum['upfp'] ? 1 : ceil($db->query("SELECT COUNT(*) FROM `forum` WHERE `type` = 'm' AND `refid` = '$th'" . ($systemUser->rights >= 7 ? '' : " AND `close` != '1'"))->fetchColumn() / $kmess);
            $link_topic = $tools->rewriteUrl($th1['text'], $th, $page) . '#' . $fadd;

            ///////////////////////////////////////////////
            //module notification forum
            ///////////////////////////////////////////////
            $req = $db->query("SELECT DISTINCT `forum`.`user_id`, `forum`.`from`, `users`.`rights` FROM `forum` INNER JOIN `users` ON `forum`.`user_id` = `users`.`id` WHERE `forum`.`type` = 'm' AND `forum`.`user_id` != '" . $systemUser->id . "' AND `forum`.`user_id` = '" . $type1['user_id'] . "' AND `forum`.`refid` = '$th' ORDER BY `forum`.`id` ASC LIMIT 20;");
            $link_topic = $tools->rewriteUrl($th1['text'], $th, $page) . '#' . $fadd;
            $listUserName = [];
            $listUserName[] = '[url=' . $config->homeurl . '/profile/?user=' . $systemUser->id . ']' . $tools->displayUsername($systemUser->name, $systemUser->rights, true) . '[/url]';
            $msg_no_new = '[url=' . $config->homeurl . '/profile/?user=' . $systemUser->id . ']' . $tools->displayUsername($systemUser->name, $systemUser->rights, true) . '[/url] ' . _t('has written in topic that you interested') . ': [url=' . $link_topic . ']' . strip_tags($th1['text']) . '[/url]';

            $countUser = $req->rowCount();
            if ($countUser) {
                $userid = [];
                $username = [];
                $userrights = [];
                while ($res = $req->fetch()) {
                    $userid[] = $res['user_id'];
                    $username[] = $res['from'];
                    $userrights[] = $res['rights'];
                }
                for ($i = $countUser-1; $i >= 0; $i--) {
                    $check_notice_exist = $db->query("SELECT * FROM `cms_mail` WHERE `sys` = 1 AND `sys_ext` = '$th' AND `them` = 'Forum notification' AND `from_id` = '" . $userid[$i] . "'")->rowCount();
                    if ($check_notice_exist) {
                        $msg_no = implode(', ', $listUserName) . ' ' . _t('has written in topic that you interested') . ': [url=' . $link_topic . ']' . strip_tags($th1['text']) . '[/url]';
                        $db->exec("UPDATE `cms_mail` SET `text` = " . $db->quote($msg_no) . ", `time` = '" . time() . "', `read` = '0' WHERE `from_id` = '" . $userid[$i] . "' AND `sys` = '1' AND `sys_ext` = '$th' AND `them` = 'Forum notification';");
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
                             $userid[$i],
                             $msg_no_new,
                             $th,
                             time()
                            ]);
                    }
                    if ($i >= $countUser - 5)
                        $listUserName[] = '[url=' . $config->homeurl . '/profile/?user=' . $userid[$i] . ']' . $tools->displayUsername($username[$i], $userrights[$i], true) . '[/url]';
                    if ($i == $countUser - 6)
                        $listUserName[] = '...';
                }
            }
            //module mentions users
            $msg = strip_tags(trim($_POST['msg']));
            if (preg_match_all('#(\s|^)\@([a-z\d\-\@\*\(\)\?\!\~\_\=\[\]\.]{2,15})#i', $msg, $list)) {
                $list = array_unique($list[2]); //get username in @username and remove duplicate values
                $max_users = count($list) > 10 ? 10 : count($list);
                $msg_no = '[url=' . $config->homeurl . '/profile/?user=' . $systemUser->id . ']' . $tools->displayUsername($systemUser->name, $systemUser->rights, true) . '[/url] ' . _t('has mentions you in a topic') . ': [url=' . $link_topic . ']' . strip_tags($th1['text']) . '[/url]';
                for ($i=0; $i < $max_users; $i++) {
                    $username = $db->quote(trim($list[$i]));
                    if ($username == $systemUser->name) continue;
                    $req = $db->query("SELECT `id` FROM `users` WHERE `name_lat` = " . $username . " LIMIT 1;");
                    if ($req->rowCount() == 0) continue;
                    else {
                        $user = $req->fetch();
                        $db->prepare('INSERT INTO `cms_mail` SET
                         `user_id` = ?, 
                         `from_id` = ?, 
                         `them`=\'You tagged\',
                         `text` = ?,
                         `sys`=\'1\',
                         `sys_ext` = ?,
                         `time` = ?
                         ')->execute([
                             $systemUser->id,
                             $user['id'],
                             $msg_no,
                             $fadd,
                             time()
                            ]);

                    }
                }
            }


            if (isset($_POST['addfiles'])) {
                header("Location: index.php?id=$fadd&act=addfile");
            } else {
                header('Location: ' . $link_topic);
            }
            exit;
        } else {
            $textl = _t('Forum');
            require('../system/head.php');
            $qt = " $type1[text]";
            $msg_pre = $tools->checkout($msg, 1, 1);
            $msg_pre = $tools->smilies($msg_pre, $systemUser->rights ? 1 : 0);
            $msg_pre = preg_replace('#\[c\](.*?)\[/c\]#si', '<div class="quote">\1</div>', $msg_pre);
            echo '<div class="phdr"><b>' . _t('Topic') . ':</b> ' . $th1['text'] . '</div>';
            $qt = str_replace("<br>", "\r\n", $qt);
            $qt = trim(preg_replace('#\[c\](.*?)\[/c\]#si', '', $qt));
            $qt = $tools->checkout($qt, 0, 2);

            if (!empty($_POST['msg']) && !isset($_POST['submit'])) {
                echo '<div class="list1">' . $tools->displayUser($systemUser, ['iphide' => 1, 'header' => '<span class="gray">(' . $tools->displayDate(time()) . ')</span>', 'body' => $msg_pre]) . '</div>';
            }

            echo '<form name="form" action="index.php?act=say&amp;id=' . $id . '&amp;start=' . $start . (isset($_GET['cyt']) ? '&amp;cyt' : '') . '" method="post"><div class="gmenu">';

            if (isset($_GET['cyt'])) {
                // Форма с цитатой
                echo '<p><b>' . $type1['from'] . '</b> <span class="gray">(' . $vr . ')</span></p>' .
                    '<p><h3>' . _t('Quote') . '</h3>' .
                    '<textarea rows="' . $systemUser->getConfig()->fieldHeight . '" name="citata">' . (empty($_POST['citata']) ? $qt : $tools->checkout($_POST['citata'])) . '</textarea>' .
                    '<br /><small>' . _t('Only allowed 200 characters, other text will be cropped.') . '</small></p>';
            } else {
                // Форма с репликой
                echo '<p><h3>' . _t('Appeal') . '</h3>' .
                    '<input type="radio" value="0" ' . (!$txt ? 'checked="checked"' : '') . ' name="txt" />&#160;<b>' . $type1['from'] . '</b>,<br />' .
                    '<input type="radio" value="2" ' . ($txt == 2 ? 'checked="checked"' : '') . ' name="txt" />&#160;<b>' . $type1['from'] . '</b>, ' . _t('I am glad to answer you') . ',<br />' .
                    '<input type="radio" value="3" ' . ($txt == 3 ? 'checked="checked"' : '') . ' name="txt" />&#160;<b>' . $type1['from'] . '</b>, ' . _t('respond to Your message') . ' (<a href="index.php?act=post&amp;id=' . $type1['id'] . '">' . $vr . '</a>):</p>';
            }

            echo '<p><h3>' . _t('Message') . '</h3>';
            echo '</p><p>' . $container->get('bbcode')->buttons('form', 'msg');
            echo '<textarea rows="' . $systemUser->getConfig()->fieldHeight . '" name="msg">' . (empty($_POST['msg']) ? '' : $tools->checkout($_POST['msg'])) . '</textarea></p>' .
                '<p><input type="checkbox" name="addfiles" value="1" ' . (isset($_POST['addfiles']) ? 'checked="checked" ' : '') . '/> ' . _t('Add File');

            $token = mt_rand(1000, 100000);
            $_SESSION['token'] = $token;
            echo '</p><p><input type="submit" name="submit" value="' . _t('Send') . '" style="width: 107px; cursor: pointer;"/> ' .
                ($set_forum['preview'] ? '<input type="submit" value="' . _t('Preview') . '" style="width: 107px; cursor: pointer;"/>' : '') .
                '<input type="hidden" name="token" value="' . $token . '"/>' .
                '</p></div></form>';
        }

        echo '<div class="phdr"><a href="../help/?act=smileys">' . _t('Smilies') . '</a></div>' .
            '<p><a href="index.php?id=' . $type1['refid'] . '&amp;start=' . $start . '">' . _t('Back') . '</a></p>';
        break;

    default:
        require('../system/head.php');
        echo $tools->displayError(_t('Topic has been deleted or does not exists'), '<a href="index.php">' . _t('Forum') . '</a>');
        require('../system/end.php');
}
