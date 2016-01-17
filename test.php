#!/usr/bin/php

<?php

$result = shell_exec('./gcsr.php http://www.fititnt.org urls_test.txt');
echo $result;