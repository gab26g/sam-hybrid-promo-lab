<?php
require __DIR__.'/config.php';
if ($argc!==3) {fwrite(STDERR,"Usage: php prepare-campaign.php CAMP-001 PROMO15\n");exit(2);}
$count=(new Lab\CampaignPreparer((new Lab\Database())->connect()))->prepare($argv[1],$argv[2]);
echo "$count new requests prepared; existing complete selection preserved.\n";
