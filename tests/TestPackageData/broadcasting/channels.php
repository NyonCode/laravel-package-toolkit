<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('test-package.room.{roomId}', fn ($user, string $roomId) => true);
