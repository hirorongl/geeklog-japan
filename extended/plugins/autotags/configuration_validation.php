<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Autotags                                                              |
// +---------------------------------------------------------------------------+
// | configuration_validation.php                                              |
// |                                                                           |
// | List of validation rules for the Links plugin configurations                          |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2007-2010 by the following authors:                         |
// |                                                                           |
// | Authors: Akeda Bagus       - admin AT gedex DOT web DOT id                |
// |          Tom Homer         - tomhomer AT gmail DOT com                    |
// +---------------------------------------------------------------------------+
// |                                                                           |
// | This program is free software; you can redistribute it and/or             |
// | modify it under the terms of the GNU General Public License               |
// | as published by the Free Software Foundation; either version 2            |
// | of the License, or (at your option) any later version.                    |
// |                                                                           |
// | This program is distributed in the hope that it will be useful,           |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
// | GNU General Public License for more details.                              |
// |                                                                           |
// | You should have received a copy of the GNU General Public License         |
// | along with this program; if not, write to the Free Software Foundation,   |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.           |
// |                                                                           |
// +---------------------------------------------------------------------------+

if (strpos(strtolower($_SERVER['PHP_SELF']), 'configuration_validation.php') !== false) {
    die('This file can not be used on its own!');
}

// General Autotag Settings
$_CONF_VALIDATE['autotags']['link_in_menu'] = array('rule' => 'boolean');
$_CONF_VALIDATE['autotags']['allow_php'] = array('rule' => 'boolean');

// Autotag Usage Permissions
$_CONF_VALIDATE['autotags']['default_autotag_permissions[0]'] = array(
    'rule' => array('inList', array(0, 2), true)
);
$_CONF_VALIDATE['autotags']['default_autotag_permissions[1]'] = array(
    'rule' => array('inList', array(0, 2), true)
);
$_CONF_VALIDATE['autotags']['default_autotag_permissions[2]'] = array(
    'rule' => array('inList', array(0, 2), true)
);
$_CONF_VALIDATE['autotags']['default_autotag_permissions[3]'] = array(
    'rule' => array('inList', array(0, 2), true)
);

?>
