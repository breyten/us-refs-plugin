<?php
# Silence is golden.
require_once( 'class.simplepie.php' );

$url = 'http://localhost:8888/us/feed/';
$feed = new SimplePie();
$feed->set_feed_url($url);
$feed->init();

foreach($feed->get_items() as $key=>$item) {
  $info = array(
    'time' => $item->get_date(),
    'url' => $item->get_link(),
    'code' => $item->get_id(),
    'title' => $item->get_title(),
    'description' => $item->get_description()
  );

  print_r($info);
}
