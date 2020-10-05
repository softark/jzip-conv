#!/bin/bash
php ./zipconv.php
YM=$(date -d '1 months ago' '+%y%m')
mysql --user=root --password=isa563rigami zipdata < ../outputs/updates/update_${YM}.sql