<?php

namespace App\Filament\Widgets;

use App\Models\SendLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SendsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Envíos de los últimos 30 días';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $data = $this->getEmailsPerDay();

        return [
            'datasets' => [
                [
                    'label' => 'Exitosos',
                    'data' => $data['successful'],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
                [
                    'label' => 'Fallidos',
                    'data' => $data['failed'],
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderColor' => 'rgb(239, 68, 68)',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function getEmailsPerDay(): array
    {
        $successful = [];
        $failed = [];
        $labels = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('d/m');

            $successful[] = SendLog::whereDate('created_at', $date)
                ->successful()
                ->count();

            $failed[] = SendLog::whereDate('created_at', $date)
                ->failed()
                ->count();
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
            'labels' => $labels,
        ];
    }
}
