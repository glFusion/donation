<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     donation
 * @version     v0.0.2
 * @since       v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Donation;


/**
 * Class to provide admin and user-facing menus.
 * @package donation
 */
class Menu
{
    /**
     * Create the administrator menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function Admin($view='')
    {
        global $_CONF, $LANG_ADMIN, $LANG_DON;

        USES_lib_admin();

        $retval = '';
        $menu_arr = array(
            array(
                'url' => DON_ADMIN_URL . '/index.php',
                'text' => $LANG_DON['campaigns'],
                'active' => $view == 'campaigns' ? true : false,
            ),
            array(
                'url' => DON_ADMIN_URL . '/index.php?editcampaign=x',
                'text' => $LANG_DON['new_campaign'],
                'active' => $view == 'editcampaign' ? true : false,
            ),
            array(
                'url' => DON_ADMIN_URL . '/index.php?editdonation=x',
                'text' => $LANG_DON['new_donation'],
                'active' => $view == 'editdonation' ? true : false,
            ),
            array(
                'url' => $_CONF['site_admin_url'],
                'text' => $LANG_ADMIN['admin_home'],
            ),
        );

        $T = new \Template(DON_PI_PATH . '/templates');
        $T->set_file('page', 'admin.thtml');
        $T->set_var(array(
            'header'    => $LANG_DON['don_mgr'],
            'pi_url'    => DON_URL,
            'pi_icon'   => plugin_geticon_donation(),
            'plugin'    => Config::PI_NAME,
            'version'   => Config::get('pi_version'),
        ) );
        $T->parse('output','page');
        $retval .= $T->finish($T->get_var('output'));
        $retval .= ADMIN_createMenu(
            $menu_arr,
            '',
            plugin_geticon_donation()
        );
        return $retval;
    }

}

