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

namespace Johncms;

use Interop\Container\ContainerInterface;
use Zend\Stdlib\ArrayUtils;

class UserFactory
{
    /**
     * @var \PDO
     */
    private $db;

    /**
     * @var Environment
     */
    private $env;

    private $userData;


    public function __invoke(ContainerInterface $container)
    {
        $this->db = $container->get(\PDO::class);
        $this->env = $container->get('env');
        $this->userData = $this->authorize();

        return new User($this->userData, User::ARRAY_AS_PROPS);
    }

    /**
     * Авторизация пользователя и получение его данных из базы
     */
    private function authorize()
    {
        $user_id = false;
        $user_ssid = false;

        if (isset($_SESSION['uid']) && isset($_SESSION['ussid'])) {
            // Авторизация по сессии
            $user_id = intval($_SESSION['uid']);
            $user_ssid = $_SESSION['ussid'];
        } elseif (isset($_COOKIE['cuid']) && isset($_COOKIE['cussid'])) {
            // Авторизация по COOKIE
            $user_id = abs(intval(base64_decode(trim($_COOKIE['cuid']))));
            $_SESSION['uid'] = $user_id;
            $user_ssid = trim($_COOKIE['cussid']);
            $_SESSION['ussid'] = $user_ssid;
        }

        if ($user_id && $user_ssid) {
            $req = $this->db->query('SELECT * FROM `users` WHERE `id` = ' . $user_id);

            if ($req->rowCount()) {
                $userData = $req->fetch();
                $permit = $userData['failed_login'] < 3
                || $userData['failed_login'] > 2
                && $userData['ip'] == $this->env->getIp()
                && $userData['browser'] == $this->env->getUserAgent()
                    ? true
                    : false;

                if ($permit && strlen($user_ssid) >= 25 && strlen($userData['ssid']) == 60 && password_verify($user_ssid, $userData['ssid'])) {
                    // Проверяем на бан
                    $userData['ban'] = $this->banCheck($userData['id']);

                    // Если есть бан, обнуляем привилегии
                    if (!empty($userData['ban'])) {
                        $userData['rights'] = 0;
                    }

                    // Фиксируем историю IP
                    if ($userData['ip'] != $this->env->getIp() || $userData['ip_via_proxy'] != $this->env->getIpViaProxy()) {
                        $this->ipHistory($userData);
                    }

                    return $userData;
                } else {
                    if ($userData['failed_login'] > 9) {
                        $this->db->exec("INSERT INTO `cms_ban_ip` (`ip1`, `ip2`, `ban_type`, `who`, `reason`, `date`) VALUES ('" . $this->env->getIp() . "', '" . $this->env->getIp() . "', 1, 'admin', 'Exploit cookie', " . time() . ")" . ($this->env->getIpViaProxy() ? ", ('" . $this->env->getIpViaProxy() . "', '" . $this->env->getIpViaProxy() . "', 1, 'admin', 'Exploit cookie', " . time() . ")" : "") . "; ");
                        $userData['failed_login'] = 2;
                    }
                    // Если авторизация не прошла
                    $this->db->query("UPDATE `users` SET `failed_login` = '" . ($userData['failed_login'] + 1) . "' WHERE `id` = " . $userData['id']);

                    $this->userUnset();
                }
            } else {
                // Если пользователь не существует
                $this->userUnset();
            }
        }

        return $this->userTemplate();
    }

    /**
     * Проверка на бан
     *
     * @param int $userId
     * @return array
     */
    private function banCheck($userId)
    {
        $ban = [];
        $req = $this->db->query("SELECT * FROM `cms_ban_users` WHERE `user_id` = " . $userId . " AND `ban_time` > '" . time() . "'");

        while ($res = $req->fetch()) {
            $ban[$res['ban_type']] = 1;
        }

        return $ban;
    }

    /**
     * Фиксация истории IP адресов пользователя
     *
     * @param array $userData
     */
    private function ipHistory(array $userData)
    {
        // Удаляем из истории текущий адрес (если есть)
        $this->db->exec("DELETE FROM `cms_users_iphistory`
          WHERE `user_id` = '" . $userData['id'] . "'
          AND `ip` = '" . $this->env->getIp() . "'
          AND `ip_via_proxy` = '" . $this->env->getIpViaProxy() . "'
          LIMIT 1
        ");

        // Вставляем в историю предыдущий адрес IP
        $this->db->exec("INSERT INTO `cms_users_iphistory` SET
          `user_id` = '" . $userData['id'] . "',
          `ip` = '" . $userData['ip'] . "',
          `ip_via_proxy` = '" . $userData['ip_via_proxy'] . "',
          `time` = '" . $userData['lastdate'] . "'
        ");

        // Обновляем текущий адрес в таблице `users`
        $this->db->exec("UPDATE `users` SET
          `ip` = '" . $this->env->getIp() . "',
          `ip_via_proxy` = '" . $this->env->getIpViaProxy() . "'
          WHERE `id` = '" . $userData['id'] . "'
        ");
    }

    private function userTemplate()
    {
        $template = [
            'id'            => 0,
            'name'          => '',
            'name_lat'      => '',
            'password'      => '',
            'rights'        => 0,
            'failed_login'  => 0,
            'imname'        => '',
            'sex'           => '',
            'komm'          => 0,
            'postforum'     => 0,
            'postguest'     => 0,
            'yearofbirth'   => 0,
            'datereg'       => 0,
            'lastdate'      => 0,
            'mail'          => '',
            'icq'           => '',
            'skype'         => '',
            'jabber'        => '',
            'www'           => '',
            'about'         => '',
            'live'          => '',
            'mibile'        => '',
            'status'        => '',
            'ip'            => '',
            'ip_via_proxy'  => '',
            'browser'       => '',
            'preg'          => '',
            'regadm'        => '',
            'mailvis'       => '',
            'dayb'          => '',
            'monthb'        => '',
            'sestime'       => '',
            'total_on_site' => '',
            'lastpost'      => '',
            'rest_code'     => '',
            'rest_time'     => '',
            'movings'       => '',
            'place'         => '',
            'set_user'      => '',
            'set_forum'     => '',
            'set_mail'      => '',
            'karma_plus'    => '',
            'karma_minus'   => '',
            'karma_time'    => '',
            'karma_off'     => '',
            'comm_count'    => '',
            'comm_old'      => '',
            'smileys'       => '',
            'ban'           => [],
        ];

        return $template;
    }

    /**
     * Уничтожаем данные авторизации юзера
     */
    private function userUnset()
    {
        unset($_SESSION['uid']);
        unset($_SESSION['ussid']);
        setcookie('cuid', '');
        setcookie('cussid', '');
    }
}
