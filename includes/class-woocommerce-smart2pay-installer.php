<?php

/**
 * Checks if plugin is up-to-date
 *
 * @link       http://www.smart2pay.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Smart2pay
 * @subpackage Woocommerce_Smart2pay/includes
 */

/**
 * Checks if plugin is up-to-date
 *
 * This class defines all code necessary to check plugin versioning
 *
 * @since      1.0.0
 * @package    Woocommerce_Smart2pay
 * @subpackage Woocommerce_Smart2pay/includes
 * @author     Smart2Pay <support@smart2pay.com>
 */
class Woocommerce_Smart2pay_Installer
{
    const VERSION_OPTION = 'wc_smart2pay_version';

    private static $_updater_err = array();

    /** @var array DB updates that need to be run */
    private static $db_updates = array(
        // '{version}' => 'update_script.php' // update script in {Plugin_dir}/includes/updates
        '1.2.0' => 'update-to-1.2.0.php',
    );

    public static function init()
    {
        add_action( 'admin_init', array( __CLASS__, 'check_version' ), 5 );
    }

    private static function _updater_has_errors()
    {
        return (empty( self::$_updater_err )?false:true);
    }

    private static function _reset_updater_errors()
    {
        self::$_updater_err = array();
    }

    /**
     * Woocommerce_Smart2pay_Admin_Notices might not be available at this point, so send 'error' or 'update' as $type
     *
     * @param string $message Message body
     * @param string $type Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR = 'error', Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS = 'update';
     */
    private static function _add_updater_error( $message, $type = 'error' )
    {
        self::$_updater_err[] = array(
            'notice_type' => $type,
            'message' => $message,
        );
    }

    public static function check_version()
    {
        // Make sure we can save notices as install action is executed before we have all things inited in plugin
        require_once WC_S2P_PLUGIN_DIR . 'includes/class-woocommerce-smart2pay-admin-notices-later.php';

        if( !($current_version = get_option( self::VERSION_OPTION, false )) )
        {
            $install_failed = false;
            if( !self::install() )
                $install_failed = true;

            if( $install_failed )
                Woocommerce_Smart2pay_Admin_Notices_Later::add_notice( 'install_failed' );
            else
                Woocommerce_Smart2pay_Admin_Notices_Later::add_notice( 'install_success' );
        }

        if( $current_version == WC_s2p()->get_version() )
            return true;

        foreach( self::$db_updates as $version => $updater )
        {
            if( !@file_exists( WC_S2P_PLUGIN_DIR.'includes/updates/'.$updater ) )
                continue;

            if( empty( $current_version )
             or version_compare( $current_version, $version, '<' ) )
            {
                self::_reset_updater_errors();

                include( WC_S2P_PLUGIN_DIR.'includes/updates/'.$updater );

                if( self::_updater_has_errors()
                and !empty( self::$_updater_err ) )
                {
                    foreach( self::$_updater_err as $error_arr )
                    {
                        Woocommerce_Smart2pay_Admin_Notices_Later::add_notice( 'custom_notice', $error_arr );
                    }

                    return false;
                }
            }
        }

        self::update_version();

        return true;
    }

    /**
     * Update WC version to current.
     */
    private static function update_version()
    {
        delete_option( self::VERSION_OPTION );
        add_option( self::VERSION_OPTION, WC_s2p()->get_version() );
    }

    public static function install()
    {
        if( !self::create_tables()
         or !self::create_pages()
         or !self::populate_tables() )
            return false;

        // Flush rules after install
        flush_rewrite_rules();

        return true;
    }

    /**
     * Create pages that the plugin relies on, storing page id's in variables.
     */
    public static function create_pages()
    {
        include_once( WC()->plugin_path() . '/includes/admin/wc-admin-functions.php' );

        $wc_s2p = WC_s2p();

        $pages = apply_filters( 'woocommerce_smart2pay_create_pages', array(
            'smart2pay_return' => array(
                'name'    => WC_s2p()->_x( $wc_s2p::PAGE_SLUG_RETURN, 'Page slug' ),
                'title'   => WC_s2p()->_x( 'Payment Return Page', 'Page title' ),
                'content' => '['.$wc_s2p::SHORTCODE_RETURN.']'
            ),
        ) );

        foreach ( $pages as $key => $page )
        {
            $page_parent = '';
            if( !empty( $page['parent'] ) )
                $page_parent = wc_get_page_id( $page['parent'] );

            if( !($page_id = wc_create_page( esc_sql( $page['name'] ), 'woocommerce_' . $key . '_page_id', $page['title'], $page['content'],  $page_parent )) )
                return false;
        }

        delete_transient( 'woocommerce_cache_excluded_uris' );

        return true;
    }

    public static function delete_tables()
    {
        global $wpdb;

        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_transactions;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_method_settings;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_method;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_method_countries;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_logs;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_country;" );
    }

    public static function create_tables()
    {
        global $wpdb;

        $wpdb->hide_errors();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $collate = '';

        if( $wpdb->has_cap( 'collation' ) )
        {
            if( ! empty($wpdb->charset) )
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            if( ! empty($wpdb->collate) )
                $collate .= " COLLATE $wpdb->collate";
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_transactions (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        method_id int(11) NOT NULL DEFAULT '0',
                        payment_id int(11) NOT NULL DEFAULT '0',
                        order_id int(11) NOT NULL DEFAULT '0',
                        site_id int(11) NOT NULL DEFAULT '0',
                        environment varchar(20) DEFAULT NULL,
                        extra_data text DEFAULT NULL,
                        amount decimal(10,2) NOT NULL DEFAULT '0',
                        currency varchar(3) DEFAULT NULL COMMENT 'Currency ISO 3',
                        surcharge_amount decimal(10,2) NOT NULL DEFAULT '0',
                        surcharge_currency varchar(3) DEFAULT NULL COMMENT 'Currency ISO 3',
                        surcharge_percent decimal(6,2) NOT NULL DEFAULT '0',
                        surcharge_amount_percent decimal(10,2) NOT NULL DEFAULT '0',
                        surcharge_total_amount decimal(10,2) NOT NULL DEFAULT '0',
                        payment_status tinyint(2) NOT NULL DEFAULT '0' COMMENT 'Status received from server',
                        last_update datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                        created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                        PRIMARY KEY (id),
                        KEY method_id (method_id),
                        KEY payment_id (payment_id),
                        KEY order_id (order_id)
                    ) $collate COMMENT='Transactions run trough Smart2Pay';" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_method_settings (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        method_id int(11) NOT NULL DEFAULT '0',
                        environment varchar(50) default NULL,
                        enabled tinyint(2) NOT NULL DEFAULT '0',
                        surcharge_percent decimal(6,2) NOT NULL DEFAULT '0.00',
                        surcharge_amount decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount of surcharge',
                        surcharge_currency varchar(3) DEFAULT NULL COMMENT 'ISO 3 currency code of fixed surcharge amount',
                        priority tinyint(4) NOT NULL DEFAULT '10' COMMENT '1 means first',
                        last_update datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                        configured datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                        PRIMARY KEY (id),
                        KEY method_id (method_id),
                        KEY enabled (enabled),
                        KEY environment (environment)
                    ) $collate COMMENT='Smart2Pay method configurations';" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_method (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        method_id int(11) NOT NULL default 0,
                        environment varchar(50) default NULL,
                        display_name varchar(255) default NULL,
                        description text,
                        logo_url varchar(255) default NULL,
                        guaranteed tinyint(2) default 0,
                        active tinyint(2) default 0,
                        PRIMARY KEY (id),
                        KEY method_id (method_id),
                        KEY active (active),
                        KEY environment (environment)
                    ) $collate;" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_method_countries (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        country_code varchar(3) default NULL,
                        method_id int(11) default 0,
                        PRIMARY KEY (id),
                        KEY country_code (country_code),
                        KEY method_id (method_id)
                    ) $collate;" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_logs (
                        log_id int(11) NOT NULL AUTO_INCREMENT,
                        order_id int(11) NOT NULL default '0',
                        environment varchar(50) default NULL,
                        log_type varchar(255) default NULL,
                        log_data text default NULL,
                        log_source_file varchar(255) default NULL,
                        log_source_file_line int(11) NOT NULL default '0',
                        log_created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                        PRIMARY KEY (log_id),
                        KEY order_id (order_id),
                        KEY environment (environment),
                        KEY log_type (log_type)
                    ) $collate;" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_country (
                        country_id int(11) NOT NULL AUTO_INCREMENT,
                        code varchar(3) default NULL,
                        name varchar(100) default NULL,
                        PRIMARY KEY (country_id),
                        KEY code (code)
                    ) $collate;" )) )
        {
            self::delete_tables();
            return false;
        }

        return true;
    }

    public static function populate_tables()
    {
        global $wpdb;

        $wpdb->hide_errors();

        $wpdb->query( "TRUNCATE {$wpdb->prefix}smart2pay_country;" );

        if( !($wpdb->query( "INSERT INTO {$wpdb->prefix}smart2pay_country (`code`, `name`) VALUES
            ('AD', 'Andorra'),
            ('AE', 'United Arab Emirates'),
            ('AF', 'Afghanistan'),
            ('AG', 'Antigua and Barbuda'),
            ('AI', 'Anguilla'),
            ('AL', 'Albania'),
            ('AM', 'Armenia'),
            ('AN', 'Netherlands Antilles'),
            ('AO', 'Angola'),
            ('AQ', 'Antarctica'),
            ('AR', 'Argentina'),
            ('AS', 'American Samoa'),
            ('AT', 'Austria'),
            ('AU', 'Australia'),
            ('AW', 'Aruba'),
            ('AZ', 'Azerbaijan'),
            ('BA', 'Bosnia & Herzegowina'),
            ('BB', 'Barbados'),
            ('BD', 'Bangladesh'),
            ('BE', 'Belgium'),
            ('BF', 'Burkina Faso'),
            ('BG', 'Bulgaria'),
            ('BH', 'Bahrain'),
            ('BI', 'Burundi'),
            ('BJ', 'Benin'),
            ('BM', 'Bermuda'),
            ('BN', 'Brunei Darussalam'),
            ('BO', 'Bolivia'),
            ('BR', 'Brazil'),
            ('BS', 'Bahamas'),
            ('BT', 'Bhutan'),
            ('BV', 'Bouvet Island'),
            ('BW', 'Botswana'),
            ('BY', 'Belarus (formerly known as Byelorussia)'),
            ('BZ', 'Belize'),
            ('CA', 'Canada'),
            ('CC', 'Cocos (Keeling) Islands'),
            ('CD', 'Congo, Democratic Republic of the (formerly Zalre)'),
            ('CF', 'Central African Republic'),
            ('CG', 'Congo'),
            ('CH', 'Switzerland'),
            ('CI', 'Ivory Coast (Cote d''Ivoire)'),
            ('CK', 'Cook Islands'),
            ('CL', 'Chile'),
            ('CM', 'Cameroon'),
            ('CN', 'China'),
            ('CO', 'Colombia'),
            ('CR', 'Costa Rica'),
            ('CU', 'Cuba'),
            ('CV', 'Cape Verde'),
            ('CX', 'Christmas Island'),
            ('CY', 'Cyprus'),
            ('CZ', 'Czech Republic'),
            ('DE', 'Germany'),
            ('DJ', 'Djibouti'),
            ('DK', 'Denmark'),
            ('DM', 'Dominica'),
            ('DO', 'Dominican Republic'),
            ('DZ', 'Algeria'),
            ('EC', 'Ecuador'),
            ('EE', 'Estonia'),
            ('EG', 'Egypt'),
            ('EH', 'Western Sahara'),
            ('ER', 'Eritrea'),
            ('ES', 'Spain'),
            ('ET', 'Ethiopia'),
            ('FI', 'Finland'),
            ('FJ', 'Fiji Islands'),
            ('FK', 'Falkland Islands (Malvinas)'),
            ('FM', 'Micronesia, Federated States of'),
            ('FO', 'Faroe Islands'),
            ('FR', 'France'),
            ('FX', 'France, Metropolitan'),
            ('GA', 'Gabon'),
            ('GB', 'United Kingdom'),
            ('GD', 'Grenada'),
            ('GE', 'Georgia'),
            ('GF', 'French Guiana'),
            ('GH', 'Ghana'),
            ('GI', 'Gibraltar'),
            ('GL', 'Greenland'),
            ('GM', 'Gambia'),
            ('GN', 'Guinea'),
            ('GP', 'Guadeloupe'),
            ('GQ', 'Equatorial Guinea'),
            ('GR', 'Greece'),
            ('GS', 'South Georgia and the South Sandwich Islands'),
            ('GT', 'Guatemala'),
            ('GU', 'Guam'),
            ('GW', 'Guinea-Bissau'),
            ('GY', 'Guyana'),
            ('HK', 'Hong Kong'),
            ('HM', 'Heard and McDonald Islands'),
            ('HN', 'Honduras'),
            ('HR', 'Croatia (local name: Hrvatska)'),
            ('HT', 'Haiti'),
            ('HU', 'Hungary'),
            ('ID', 'Indonesia'),
            ('IE', 'Ireland'),
            ('IL', 'Israel'),
            ('IN', 'India'),
            ('IO', 'British Indian Ocean Territory'),
            ('IQ', 'Iraq'),
            ('IR', 'Iran, Islamic Republic of'),
            ('IS', 'Iceland'),
            ('IT', 'Italy'),
            ('JM', 'Jamaica'),
            ('JO', 'Jordan'),
            ('JP', 'Japan'),
            ('KE', 'Kenya'),
            ('KG', 'Kyrgyzstan'),
            ('KH', 'Cambodia (formerly Kampuchea)'),
            ('KI', 'Kiribati'),
            ('KM', 'Comoros'),
            ('KN', 'Saint Kitts (Christopher) and Nevis'),
            ('KP', 'Korea, Democratic People''s Republic of (North Korea)'),
            ('KR', 'Korea, Republic of (South Korea)'),
            ('KW', 'Kuwait'),
            ('KY', 'Cayman Islands'),
            ('KZ', 'Kazakhstan'),
            ('LA', 'Lao People''s Democratic Republic (formerly Laos)'),
            ('LB', 'Lebanon'),
            ('LC', 'Saint Lucia'),
            ('LI', 'Liechtenstein'),
            ('LK', 'Sri Lanka'),
            ('LR', 'Liberia'),
            ('LS', 'Lesotho'),
            ('LT', 'Lithuania'),
            ('LU', 'Luxembourg'),
            ('LV', 'Latvia'),
            ('LY', 'Libyan Arab Jamahiriya'),
            ('MA', 'Morocco'),
            ('MC', 'Monaco'),
            ('MD', 'Moldova, Republic of'),
            ('MG', 'Madagascar'),
            ('MH', 'Marshall Islands'),
            ('MK', 'Macedonia, the Former Yugoslav Republic of'),
            ('ML', 'Mali'),
            ('MM', 'Myanmar (formerly Burma)'),
            ('MN', 'Mongolia'),
            ('MO', 'Macao (also spelled Macau)'),
            ('MP', 'Northern Mariana Islands'),
            ('MQ', 'Martinique'),
            ('MR', 'Mauritania'),
            ('MS', 'Montserrat'),
            ('MT', 'Malta'),
            ('MU', 'Mauritius'),
            ('MV', 'Maldives'),
            ('MW', 'Malawi'),
            ('MX', 'Mexico'),
            ('MY', 'Malaysia'),
            ('MZ', 'Mozambique'),
            ('NA', 'Namibia'),
            ('NC', 'New Caledonia'),
            ('NE', 'Niger'),
            ('NF', 'Norfolk Island'),
            ('NG', 'Nigeria'),
            ('NI', 'Nicaragua'),
            ('NL', 'Netherlands'),
            ('NO', 'Norway'),
            ('NP', 'Nepal'),
            ('NR', 'Nauru'),
            ('NU', 'Niue'),
            ('NZ', 'New Zealand'),
            ('OM', 'Oman'),
            ('PA', 'Panama'),
            ('PE', 'Peru'),
            ('PF', 'French Polynesia'),
            ('PG', 'Papua New Guinea'),
            ('PH', 'Philippines'),
            ('PK', 'Pakistan'),
            ('PL', 'Poland'),
            ('PM', 'St Pierre and Miquelon'),
            ('PN', 'Pitcairn Island'),
            ('PR', 'Puerto Rico'),
            ('PT', 'Portugal'),
            ('PW', 'Palau'),
            ('PY', 'Paraguay'),
            ('QA', 'Qatar'),
            ('RE', 'Reunion'),
            ('RO', 'Romania'),
            ('RU', 'Russian Federation'),
            ('RW', 'Rwanda'),
            ('SA', 'Saudi Arabia'),
            ('SB', 'Solomon Islands'),
            ('SC', 'Seychelles'),
            ('SD', 'Sudan'),
            ('SE', 'Sweden'),
            ('SG', 'Singapore'),
            ('SH', 'St Helena'),
            ('SI', 'Slovenia'),
            ('SJ', 'Svalbard and Jan Mayen Islands'),
            ('SK', 'Slovakia'),
            ('SL', 'Sierra Leone'),
            ('SM', 'San Marino'),
            ('SN', 'Senegal'),
            ('SO', 'Somalia'),
            ('SR', 'Suriname'),
            ('ST', 'Sco Tom'),
            ('SU', 'Union of Soviet Socialist Republics'),
            ('SV', 'El Salvador'),
            ('SY', 'Syrian Arab Republic'),
            ('SZ', 'Swaziland'),
            ('TC', 'Turks and Caicos Islands'),
            ('TD', 'Chad'),
            ('TF', 'French Southern and Antarctic Territories'),
            ('TG', 'Togo'),
            ('TH', 'Thailand'),
            ('TJ', 'Tajikistan'),
            ('TK', 'Tokelau'),
            ('TM', 'Turkmenistan'),
            ('TN', 'Tunisia'),
            ('TO', 'Tonga'),
            ('TP', 'East Timor'),
            ('TR', 'Turkey'),
            ('TT', 'Trinidad and Tobago'),
            ('TV', 'Tuvalu'),
            ('TW', 'Taiwan, Province of China'),
            ('TZ', 'Tanzania, United Republic of'),
            ('UA', 'Ukraine'),
            ('UG', 'Uganda'),
            ('UM', 'United States Minor Outlying Islands'),
            ('US', 'United States of America'),
            ('UY', 'Uruguay'),
            ('UZ', 'Uzbekistan'),
            ('VA', 'Holy See (Vatican City State)'),
            ('VC', 'Saint Vincent and the Grenadines'),
            ('VE', 'Venezuela'),
            ('VG', 'Virgin Islands (British)'),
            ('VI', 'Virgin Islands (US)'),
            ('VN', 'Viet Nam'),
            ('VU', 'Vanautu'),
            ('WF', 'Wallis and Futuna Islands'),
            ('WS', 'Samoa'),
            ('XO', 'West Africa'),
            ('YE', 'Yemen'),
            ('YT', 'Mayotte'),
            ('ZA', 'South Africa'),
            ('ZM', 'Zambia'),
            ('ZW', 'Zimbabwe'),
            ('PS', 'Palestinian Territory'),
            ('ME', 'Montenegro'),
            ('RS', 'Serbia');"
        )) )
            return false;

        return true;
    }

}
