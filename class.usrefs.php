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
    add_action( 'wp_head', array('USRefs', 'inject_styles_and_scripts' ) );
    // filters
    add_filter( 'the_content', array( 'USRefs', 'show_games' ) );
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
      time_str varchar(255) DEFAULT '' NOT NULL,
      timestamp int(11) NOT NULL,
      url varchar(255) DEFAULT '' NOT NULL,
      code varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT '' NOT NULL,
      code_human varchar(255) DEFAULT '' NOT NULL,
      code_link varchar(255) DEFAULT '' NOT NULL,
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

  public static function inject_styles_and_scripts() {
    $output = '
    <style type="text/css">
    .game-form {
      display: none;
    }
    .game-form td {
      vertical-align: middle;
    }
    .game-form .btn {
      margin-top: 0;
    }
    </style>';

    $output .= '
    <script type="text/javascript">
    jQuery(document).ready(function () {
      jQuery(".game-register").on("click", function (e) {
        var $form = jQuery(this).parent().parent().next();
        $form.toggle("slow");
        $form.find("input.form-control:first").focus();
        return false;
      });
    });
    </script>';

    print $output;
  }

  public static function show_games($content) {
    // create the table
    global $wpdb;

    $table_name = self::_table();

    $results = $wpdb->get_results(
      "SELECT * FROM $table_name WHERE `time` > NOW() AND ref_posted_at IS NULL ORDER BY `time`",
      OBJECT
    );

    $output = array();
    $output[] = '<table id="games-table" class="table table-striped table-condensed">';

    $old_date = '';
    foreach($results as $result) {
      list ($date, $time) = preg_split('/\s/', $result->time, 2);
      if ($date != $old_date) {
        $i18n_date = date_i18n('l j F Y', strtotime($date));
        $output[] = '<tr><td colspan="4"><h3>'. $i18n_date .'</h3></td></tr>';
        $old_date = $date;
      }
      $output[] = '<tr class="game-info">';
      $output[] = '<td>'. $time .'</td>';
      $output[] = sprintf(
        '<td><a href="%s" target="_blank">%s - %s</a></td>',
        $result->code_link, $result->home, $result->away
      );
      $output[] = '<td>'. $result->location .'</td>';
      $output[] = '<td><a href="#" class="game-register">inschrijven &gt;</a></td>';
      $output[] = '</tr>';
      $output[] = '<tr class="game-form"><td colspan="3">
      <form class="form-inline">
      <input type="hidden" name="id" value="'. $form->id .'" />
      <div class="form-group">
      <label class="sr-only" for="team">Team</label>
      <input type="text" class="form-control" name="team" placeholder="Team">
      </div>
      <div class="form-group">
      <label class="sr-only" for="naam">Naam</label>
      <input type="text" class="form-control" name="naam" placeholder="Naam">
      </div>
      <div class="form-group">
      <label class="sr-only" for="code">Relatiecode</label>
      <input type="text" class="form-control" name="naam" placeholder="Relatiecode">
      </div>
      </td><td>
      <button type="submit" class="btn btn-primary btn-sm">inschrijven ></button>
      </form>
      </td></tr>';
    }

    $output[] = '</table>';

    return str_replace('[usrefs]', implode("\n", $output), $content);
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

  private static function _get_code($item) {
    $info = preg_split('/,\s+/', $item->get_description());
    return str_replace('Wedstrijd: ', '', $info[0]);
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
        $code = self::_get_code($item);
        $wpdb->replace(
          $table_name,
          array(
            'time' => $item->get_date( 'Y-m-d H:i:s' ),
            'time_str' => $item->get_date( 'Y-m-d H:i:s' ),
            'timestamp' => $item->get_date( 'U' ),
            'url' => $item->get_link(),
            'code' => $code,
            'code_human' => $code,
            'code_link' => $item->get_id(),
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
