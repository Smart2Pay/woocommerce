<?php

    /** @var Woocommerce_Smart2pay_Installer $this */

    global $wpdb;

    $old_show_errors = $wpdb->hide_errors();

    if( !($wpdb->query( "ALTER TABLE `{$wpdb->prefix}smart2pay_transactions` ADD `use_3dsecure` TINYINT(2) NOT NULL DEFAULT '0' AFTER `environment`;" )) )
    {
        $this::_add_updater_error( 'Error while upgrading: Couldn\'t add 3D secure field in transactions table.' );
        return;
    }

    $wpdb->show_errors( $old_show_errors );
