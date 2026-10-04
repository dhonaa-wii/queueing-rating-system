{{--
    Expects: $adviserName (string, '' when the group recorded none),
    $isTitleProposal, $remarksSpanned (true when the first researcher row's
    Remarks cell already spans this row).

    The technical adviser listed under the researchers like a member, labelled
    Adviser (user-directed 2026-10-04) — not scored, so the Individual cell
    stays blank. Shared by the builder canvas, the room-session live panel and
    Reports' read-only sheets.
--}}
@if ($adviserName !== '')
    <tr class="eval-adviser-row">
        <td>{{ $adviserName }} (Adviser)</td>
        @unless ($isTitleProposal)
            <td class="eval-info-value"></td>
        @endunless
        @unless ($remarksSpanned)
            <td></td>
        @endunless
    </tr>
@endif
