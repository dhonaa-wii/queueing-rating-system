{{--
    Expects: $panelName (string, blank in the builder — a template has no
    real panelist), $panelRoleLabel ('Chair' / 'Member N'),
    $adviserName (string, blank when the group recorded none).

    Shared sign-off block for all three renderings of the sheet — the
    Evaluation Library builder's canvas, the room-session live panel, and
    the Reports & Analytics read-only view — so the printed footer can't
    drift between them, the same way paper-styles.blade.php already keeps
    the rest of the paper in sync.
--}}
<div class="eval-paper-signoff">
    <div class="eval-signoff-line">
        <div class="eval-signoff-name">{{ $panelName }}</div>
        <div class="eval-signoff-role">{{ $panelRoleLabel }}</div>
    </div>
    <div class="eval-signoff-line">
        <div class="eval-signoff-name">{{ $adviserName }}</div>
        <div class="eval-signoff-role">Technical Adviser</div>
    </div>
</div>
