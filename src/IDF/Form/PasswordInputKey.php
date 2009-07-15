<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');

/**
 * Check the validity of a confirmation key to reset a password.
 *
 */
class IDF_Form_PasswordInputKey extends Pluf_Form
{
    public function initFields($extra=array())
    {
        $this->fields['key'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Your verification key'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'size' => 50,
                                                                    ),
                                            ));
    }

    /**
     * Validate the key.
     */
    public function clean_key()
    {
        $this->cleaned_data['key'] = trim($this->cleaned_data['key']);
        $error = __('We are sorry but this validation key is not valid. Maybe you should directly copy/paste it from your validation email.');
        if (false === ($cres=self::checkKeyHash($this->cleaned_data['key']))) {
            throw new Pluf_Form_Invalid($error);
        }
        $guser = new Pluf_User();
        $sql = new Pluf_SQL('email=%s AND id=%s', 
                            array($cres[0], $cres[1]));
        if ($guser->getCount(array('filter' => $sql->gen())) != 1) {
            throw new Pluf_Form_Invalid($error);
        }
        if ((time() - $cres[2]) > 86400) {
            throw new Pluf_Form_Invalid(__('Sorry, but this verification key has expired, please restart the password recovery sequence. For security reasons, the verification key is only valid 24h.'));
        }
        return $this->cleaned_data['key'];
    }

    /**
     * Save the model in the database.
     *
     * @param bool Commit in the database or not. If not, the object
     *             is returned but not saved in the database.
     * @return string Url to redirect to the form.
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save an invalid form.'));
        }
        return Pluf_HTTP_URL_urlForView('IDF_Views::passwordRecovery',
                                        array($this->cleaned_data['key']));
    }

    /**
     * Return false or an array with the email, id and timestamp.
     *
     * This is a static function to be reused by other forms.
     *
     * @param string Confirmation key
     * @return mixed Either false or array(email, id, timestamp)
     */
    public static function checkKeyHash($key)
    {
        $hash = substr($key, 0, 2);
        $encrypted = substr($key, 2);
        if ($hash != substr(md5(Pluf::f('secret_key').$encrypted), 0, 2)) {
            return false;
        }
        $cr = new Pluf_Crypt(md5(Pluf::f('secret_key')));
        $f = split(':', $cr->decrypt($encrypted), 3);
        if (count($f) != 3) {
            return false;
        }
        return $f;
    }
}
