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

use Zend\Stdlib\ArrayObject;

/**
 * Class User
 *
 * @package Johncms
 *
 * @property $id
 * @property $name
 * @property $name_lat
 * @property $password
 * @property $rights
 * @property $failed_login
 * @property $imname
 * @property $sex
 * @property $komm
 * @property $postforum
 * @property $postguest
 * @property $yearofbirth
 * @property $datereg
 * @property $lastdate
 * @property $mail
 * @property $icq
 * @property $skype
 * @property $jabber
 * @property $www
 * @property $about
 * @property $live
 * @property $mibile
 * @property $status
 * @property $ip
 * @property $ip_via_proxy
 * @property $browser
 * @property $preg
 * @property $regadm
 * @property $mailvis
 * @property $dayb
 * @property $monthb
 * @property $sestime
 * @property $total_on_site
 * @property $lastpost
 * @property $rest_code
 * @property $rest_time
 * @property $movings
 * @property $place
 * @property $set_user
 * @property $set_forum
 * @property $set_mail
 * @property $karma_plus
 * @property $karma_minus
 * @property $karma_time
 * @property $karma_off
 * @property $comm_count
 * @property $comm_old
 * @property $smileys
 * @property $ban
 */
class User extends ArrayObject
{
    private $userConfigObject;

    /**
     * User constructor.
     *
     * @param array $input
     */
    public function __construct(array $input)
    {
        parent::__construct($input, parent::ARRAY_AS_PROPS);
    }

    /**
     * User validation
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->offsetGet('id') > 0
            && $this->offsetGet('preg') == 1
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get User config
     *
     * @return UserConfig
     */
    public function getConfig()
    {
        if (null === $this->userConfigObject) {
            $this->userConfigObject = new UserConfig($this);
        }

        return $this->userConfigObject;
    }
}
