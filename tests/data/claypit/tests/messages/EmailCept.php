<?php

declare(strict_types=1);

// @env email
$I = new MessageGuy($scenario);
$I->wantTo('Test emails');
$I->expect('emails are sent');
