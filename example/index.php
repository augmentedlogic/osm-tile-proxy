<?php

require_once(__DIR__ . '/../src/TileProxy.php');
require_once(__DIR__ . '/../src/MapStyle.php');

$style_osm = new \com\augmentedlogic\OsmTileProxy\MapStyle("osm");
$style_osm->setMirrors(array("http://a.tile.openstreetmap.org", "http://b.tile.openstreetmap.org", "http://c.tile.openstreetmap.org"));

//$style_osm->setEffectModulate(100, 50, 100);
//$style_osm->setEffectSepia(90);
//$style_osm->setEffectNegate();

$tileproxy = new \com\augmentedlogic\OsmTileProxy\TileProxy();
$tileproxy->addStyle($style_osm);
$tileproxy->setLogLevel(\com\augmentedlogic\OsmTileProxy\TileProxy::LOGLEVEL_OFF);
$tileproxy->handle();

