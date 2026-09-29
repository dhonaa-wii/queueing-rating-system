<?php

namespace Database\Seeders;

use App\Models\PanelAssignmentKind;
use Illuminate\Database\Seeder;

class PanelAssignmentKindSeeder extends Seeder
{
    public function run(): void
    {
        $kinds = [
            ['code' => 'ASSIGNED_PANELIST', 'name' => 'Assigned Panelist'],
            ['code' => 'BACKUP_PANELIST', 'name' => 'Backup Panelist'],
        ];

        foreach ($kinds as $kind) {
            PanelAssignmentKind::updateOrCreate(['code' => $kind['code']], $kind);
        }
    }
}
