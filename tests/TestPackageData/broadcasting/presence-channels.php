<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('test-package.presence', fn ($user) => ['id' => 1]);
