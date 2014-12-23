<?php

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class USRefs {
  private static $initiated = false;

  public static function init() {
    if ( ! self::$initiated ) {
      self::init_hooks();
    }
  }

  /**
  * Initializes WordPress hooks
  */
  private static function init_hooks() {
    self::$initiated = true;

    //Hook our function , wi_create_backup(), into the action wi_create_daily_backup
    add_action( 'usrefs_get_program', array( 'USRefs', 'update_program' ) );
  }

  private static function _table() {
    // create the table
    global $wpdb;

    return $wpdb->prefix . 'usrefs';

  }

  /**
  * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
  * @static
  */
  public static function plugin_activation() {
    // create the table
    global $wpdb;

    $table_name = self::_table();

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
      id mediumint(11) NOT NULL AUTO_INCREMENT,
      time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
      url varchar(255) DEFAULT '' NOT NULL,
      code varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT '' NOT NULL,
      title tinytext not null,
      description text not null,
      home VARCHAR(255),
      away VARCHAR(255),
      location VARCHAR(255),
      court VARCHAR(10),
      ref_team VARCHAR(255),
      ref_name VARCHAR(255),
      ref_code VARCHAR(32),
      ref_posted_at datetime,
      UNIQUE KEY id (id),
      PRIMARY KEY pk (code)
    ) $charset_collate;";

    require_once( ABSPATH .'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'usrefs_db_version', USREF__DB_VERSION );

    // add the cron job
    $timestamp = wp_next_scheduled( 'usrefs_get_program' );

    if( $timestamp == false ){
      wp_schedule_event( time(), 'hourly', 'usrefs_get_program' );
    }

  }

  /**
  * Removes all connection options
  * @static
  */
  public static function plugin_deactivation( ) {
    // create the table
    global $wpdb;

    $table_name = self::_table();

    $sql = "DROP TABLE $table_name;";

    $wpdb->get_var( $sql );

    wp_clear_scheduled_hook( 'usrefs_get_program' );
  }

  private static function _get_teams($item) {
    list ($date, $title) = preg_split('/:\s+/', $item->get_title(), 2);
    return preg_split('/\s+-\s+/', $title, 2);
  }

  private static function _get_location($item) {
    $info = preg_split('/,\s+/', $item->get_description());
    return str_replace('Speellocatie: ', '', $info[3]);
  }

  private static function _can_ref_game($home, $away, $item) {
    return preg_match('/US (D|H)S\s?[\d]+$/', $home);
  }

  public static function update_program() {
    // create the table
    global $wpdb;

    $table_name = self::_table();

    //$url = 'http://www.volleybal.nl/application/handlers/export.php?format=rss&type=team&programma=3208DS+1&iRegionId=9000';
    $url = 'http://www.volleybal.nl/application/handlers/export.php?format=rss&type=vereniging&programma=3208&iRegionId=3000';

    $feed = new SimplePie();
    $feed->set_feed_url($url);
    $feed->init();

    foreach($feed->get_items() as $key=>$item) {
      list ($home, $away) = self::_get_teams($item);
      if (self::_can_ref_game($home, $away, $item)) {
        $wpdb->replace(
          $table_name,
          array(
            'time' => $item->get_date( 'Y-m-d h:i:s' ),
            'url' => $item->get_link(),
            'code' => $item->get_id(),
            'title' => $item->get_title(),
            'description' => $item->get_description(),
            'home' => $home,
            'away' => $away,
            'location' => self::_get_location($item),
            'court' => 'Onbekend'
          )
        );
      }
    }
  }
}
