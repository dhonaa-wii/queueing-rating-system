<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AccountStatusSeeder::class,
            PresentationModeSeeder::class,
            CategoryStatusSeeder::class,
            QueueStrategySeeder::class,
            EventDateStatusSeeder::class,
            RoomUseStatusSeeder::class,
            AttemptTypeSeeder::class,
            PresentationStatusSeeder::class,
            QueueAdjustmentTypeSeeder::class,
            AdjustmentReasonSeeder::class,
            PanelAssignmentKindSeeder::class,
            PanelAssignmentStatusSeeder::class,
            SubstitutionStatusSeeder::class,
            EventStatusSeeder::class,
            RoomSessionStatusSeeder::class,
            TerminalTypeSeeder::class,
            ConnectionStatusSeeder::class,
            TimerStatusSeeder::class,
            PresentationActionTypeSeeder::class,
            PaymentStatusSeeder::class,
            FormVersionStatusSeeder::class,
            ScoringMethodSeeder::class,
            CriterionScopeSeeder::class,
            SubmissionStatusSeeder::class,
            PresentationOutcomeSeeder::class,
            DecisionStatusSeeder::class,
            RequirementStatusSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
