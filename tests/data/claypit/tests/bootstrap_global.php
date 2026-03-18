<?php

declare(strict_types=1);

@unlink(\Codeception\Configuration::outputDir() . 'order.txt');
$fh = fopen(\Codeception\Configuration::outputDir() . 'order.txt', 'a');
fwrite($fh, 'B');
