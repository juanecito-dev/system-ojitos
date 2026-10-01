<?php

use Illuminate\Support\Facades\Schedule;

// Tareas de cada día (en el servidor: un cron que corra «php artisan schedule:run» cada minuto)
Schedule::command('ojitos:revisar')->dailyAt('03:15')->withoutOverlapping();
