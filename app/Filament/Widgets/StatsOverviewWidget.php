<?php

namespace App\Filament\Widgets;

use App\Models\Bounce;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\SendLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalSentToday = SendLog::today()->count();
        $totalSentThisMonth = SendLog::thisMonth()->count();

        $successRateToday = $totalSentToday > 0
            ? round((SendLog::today()->successful()->count() / $totalSentToday) * 100, 1)
            : 0;

        $activeDomains = Domain::where('is_active', true)->count();
        $activeMailboxes = Mailbox::where('is_active', true)->count();
        $bouncesToday = Bounce::whereDate('created_at', today())->count();

        return [
            Stat::make('Correos Enviados Hoy', $totalSentToday)
                ->description('Total de envíos del día')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('success')
                ->chart($this->getLastSevenDaysChart()),

            Stat::make('Tasa de Éxito Hoy', $successRateToday . '%')
                ->description('Correos entregados exitosamente')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($successRateToday >= 95 ? 'success' : ($successRateToday >= 85 ? 'warning' : 'danger')),

            Stat::make('Dominios Activos', $activeDomains)
                ->description("$activeMailboxes buzones activos")
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Rebotes Hoy', $bouncesToday)
                ->description('Correos rebotados')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($bouncesToday > 10 ? 'danger' : 'gray'),
        ];
    }

    protected function getLastSevenDaysChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $data[] = SendLog::whereDate('created_at', $date)->count();
        }
        return $data;
    }
}
