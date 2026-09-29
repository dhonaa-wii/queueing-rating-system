<?php

namespace Database\Seeders;

use App\Models\TerminalType;
use Illuminate\Database\Seeder;

/**
 * User-directed 2026-09-07: room_terminals are no longer created with a
 * type-based Lead/Evaluation split (EventActivationService::start() now
 * assigns every seat the EVALUATION type uniformly) — flow control follows
 * the Admin-designated Lead/Chair panelist wherever they log in instead
 * (attempt_panel_assignments.is_lead, TerminalConnectionService::isLead()).
 * The LEAD row/can_control_flow column are kept only so historical
 * attempt_panel_participations rows created before this change still
 * resolve their terminal_type_id.
 */
class TerminalTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'LEAD', 'name' => 'Lead', 'can_control_flow' => true, 'can_verify_payment' => true, 'can_evaluate' => true],
            ['code' => 'EVALUATION', 'name' => 'Evaluation', 'can_control_flow' => false, 'can_verify_payment' => false, 'can_evaluate' => true],
        ];

        foreach ($types as $type) {
            TerminalType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
