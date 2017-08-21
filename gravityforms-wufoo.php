<?php
/*
Plugin Name: Gravity Forms + Wufoo Forms
Plugin URI: https://www.tylersteinhaus.com
Description: A simple plugin that allows you to pass data to Wufoo forms from your Gravity Form
Version: 1.0
Author: Tyler Steinhaus
Author URI: https://tylersteinhaus.com

------------------------------------------------------------------------
Copyright 2012-2016 Rocketgenius Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define( 'GF_WUFOO_ADDON_VERSION', '1.0' );

add_action( 'gform_loaded', array( 'GF_Wufoo_AddOn_Bootstrap', 'load' ), 5 );

class GF_Wufoo_AddOn_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( 'class-gfwufooaddon.php' );

        GFAddOn::register( 'GFWufooAddOn' );
    }

}

function gf_wufoo_addon() {
    return GFWufooAddOn::get_instance();
}