<?php

require_once dirname(__FILE__).'/api/listid.php';
require_once dirname(__FILE__).'/shared/genpack.php';

$ids = uupListIds();
if(isset($ids['error'])) {
    consoleLogger('ERROR: '.$ids['error']);
    exit(1);
}

$builds = $ids['builds'];
consoleLogger('Found '.count($builds).' builds, checking for missing packs...');

foreach($builds as $val) {
    if(uupApiPacksExist($val['uuid'])) continue;

    generatePack($val['uuid']);
}
