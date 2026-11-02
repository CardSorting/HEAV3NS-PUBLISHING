<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class PdoMysqlndWarningWidget extends Widget
{
    protected static ?int $sort = 0;

    protected string $view = 'filament.widgets.pdo-mysqlnd-warning-widget';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return !extension_loaded('mysqlnd') || !checkMysqlndForPDO();
    }

    public function getViewData(): array
    {
        return [
            'hasMysqlnd' => extension_loaded('mysqlnd'),
            'pdoMysqlnd' => checkMysqlndForPDO(),
        ];
    }

    protected function checkMysqlndForPDO(): bool
    {
        try {
            $info = new \PDO('mysql:host='.config('database.connections.mysql.host'), config('database.connections.mysql.username'), config('database.connections.mysql.password'));
            return str_contains($info->getAttribute(\PDO::ATTR_CLIENT_VERSION), 'mysqlnd');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
