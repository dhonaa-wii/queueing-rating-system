<?php

namespace App\Services;

use App\Exceptions\DuplicatePaymentReferenceException;
use App\Models\CategoryPaymentType;
use App\Models\PaymentStatus;
use App\Models\PaymentVerification;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\TerminalConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for a category's configured payment types and an
 * attempt's per-type verification state (2026-09-20 rework — this used to be
 * a single payment_required flag with one payment_verifications row per
 * attempt; a category can now require several named payments, e.g. a
 * defense fee and a documentation fee, each verified independently against
 * its own reference number). Every caller that needs to know "has this
 * group paid" or "verify/resolve this payment" should go through here
 * rather than querying PaymentVerification directly, so the
 * VERIFIED-or-RESOLVED "satisfied" rule and the multi-type gate stay in one
 * place.
 */
class PaymentVerificationService
{
    private const SATISFIED_CODES = ['VERIFIED', 'RESOLVED'];

    public function isRequired(PresentationCategory $category): bool
    {
        return (bool) ($category->categoryPaymentSetting?->payment_required ?? false);
    }

    /**
     * @return Collection<int, CategoryPaymentType>
     */
    public function typesFor(PresentationCategory $category): Collection
    {
        return $category->relationLoaded('categoryPaymentTypes')
            ? $category->categoryPaymentTypes
            : $category->categoryPaymentTypes()->get();
    }

    /**
     * The one place that decides whether a group has satisfied every
     * configured payment for its category — VERIFIED (a Lead checked it
     * live) and RESOLVED (an Admin encoded the reference number after a
     * referral/defer) both count; a category with no types configured, or
     * with payment not required at all, is trivially satisfied.
     */
    public function allSatisfied(PresentationCategory $category, PresentationAttempt $attempt): bool
    {
        return $this->summaryFor($category, $attempt)['allSatisfied'];
    }

    /**
     * Per-type status plus an aggregate label/badge for the compact
     * "Current Group" displays (tablet, public schedule) that only have room
     * for one badge. $attempt->paymentVerifications should be eager-loaded
     * (with paymentStatus) by the caller to avoid N+1 across a list.
     *
     * @return array{required: bool, types: Collection, allSatisfied: bool, anyReferred: bool, label: string, badgeClass: string}
     */
    public function summaryFor(PresentationCategory $category, PresentationAttempt $attempt): array
    {
        if (! $this->isRequired($category)) {
            return [
                'required' => false,
                'types' => collect(),
                'allSatisfied' => true,
                'anyReferred' => false,
                'label' => 'Not Required',
                'badgeClass' => 'badge-muted-tint',
            ];
        }

        $types = $this->typesFor($category);
        $verifications = $attempt->relationLoaded('paymentVerifications')
            ? $attempt->paymentVerifications
            : $attempt->paymentVerifications()->with('paymentStatus')->get();

        $rows = $types->map(function (CategoryPaymentType $type) use ($verifications) {
            $verification = $verifications->firstWhere('category_payment_type_id', $type->id);
            $code = $verification?->paymentStatus?->code;

            return [
                'type' => $type,
                'verification' => $verification,
                'code' => $code,
                'statusName' => $verification?->paymentStatus?->name,
                'satisfied' => in_array($code, self::SATISFIED_CODES, true),
                'referred' => $code === 'REFERRED_TO_ADMIN',
            ];
        });

        $allSatisfied = $types->isEmpty() || $rows->every(fn ($row) => $row['satisfied']);
        $anyReferred = $rows->contains('referred', true);
        $verifiedCount = $rows->where('satisfied', true)->count();

        $label = match (true) {
            $types->isEmpty() => 'Not Configured',
            $allSatisfied => 'Verified',
            $anyReferred => 'Referred to Admin',
            $verifiedCount > 0 => "{$verifiedCount}/{$types->count()} Verified",
            default => 'Not Checked',
        };

        $badgeClass = match (true) {
            $allSatisfied => 'badge-success-tint',
            $anyReferred => 'badge-danger-tint',
            $verifiedCount > 0 => 'badge-info-tint',
            default => 'badge-muted-tint',
        };

        return [
            'required' => true,
            'types' => $rows,
            'allSatisfied' => $allSatisfied,
            'anyReferred' => $anyReferred,
            'label' => $label,
            'badgeClass' => $badgeClass,
        ];
    }

    /**
     * The Lead/Chair verifies one payment type from the room-session
     * tablet, typing in the reference number shown on the group's receipt.
     *
     * @throws DuplicatePaymentReferenceException if the reference number is
     *         already recorded against another payment anywhere in the system.
     */
    public function verifyType(PresentationAttempt $attempt, CategoryPaymentType $type, string $referenceNumber, int $performedByUserId, ?TerminalConnection $connection = null): PaymentVerification
    {
        $verification = PaymentVerification::firstOrNew([
            'presentation_attempt_id' => $attempt->id,
            'category_payment_type_id' => $type->id,
        ]);

        $this->assertNotDuplicate($referenceNumber, $verification->exists ? $verification->id : null);

        if (! $verification->exists) {
            $verification->initially_checked_by = $performedByUserId;
            $verification->checked_at = now();
        }

        $verification->payment_status_id = PaymentStatus::where('code', 'VERIFIED')->firstOrFail()->id;
        $verification->terminal_connection_id = $connection?->id;
        $this->persist($verification, $referenceNumber);

        return $verification;
    }

    /**
     * An Admin encodes a group's payment reference number(s) — e.g. after a
     * panelist referred a concern, or simply because a deferred group's
     * payment was never checked before it was pulled from the queue — and
     * marks those types resolved before reinserting the group. Keyed by
     * category_payment_type_id => reference number; a blank/omitted entry
     * is left untouched, so an Admin can resolve one type now and the rest
     * on a later visit. Returns how many types were actually resolved, so
     * the caller can tell an empty submission apart from a real one.
     *
     * @param  array<int, string>  $referenceNumbers
     *
     * @throws DuplicatePaymentReferenceException if any entered reference
     *         number repeats another one in this same submission, or is
     *         already recorded against another payment anywhere in the
     *         system — nothing is saved when this is thrown.
     */
    public function resolveByAdmin(PresentationAttempt $attempt, PresentationCategory $category, array $referenceNumbers, ?string $remarks, int $performedByUserId): int
    {
        $types = $this->typesFor($category);
        $now = now();
        $resolvedStatusId = PaymentStatus::where('code', 'RESOLVED')->firstOrFail()->id;

        $entries = [];
        foreach ($types as $type) {
            $referenceNumber = trim((string) ($referenceNumbers[$type->id] ?? ''));

            if ($referenceNumber !== '') {
                $entries[] = [$type, $referenceNumber];
            }
        }

        // Two different types in the same submission can't share one
        // reference number either — checked up front so a same-batch
        // collision fails the whole submission with no partial save.
        $seenHashes = [];
        foreach ($entries as [, $referenceNumber]) {
            $hash = $this->hashFor($referenceNumber);

            if (isset($seenHashes[$hash])) {
                throw new DuplicatePaymentReferenceException("Reference number \"{$referenceNumber}\" is entered more than once in this submission.");
            }

            $seenHashes[$hash] = true;
        }

        return DB::transaction(function () use ($entries, $attempt, $resolvedStatusId, $now, $remarks, $performedByUserId) {
            $resolvedCount = 0;

            foreach ($entries as [$type, $referenceNumber]) {
                $verification = PaymentVerification::firstOrNew([
                    'presentation_attempt_id' => $attempt->id,
                    'category_payment_type_id' => $type->id,
                ]);

                $this->assertNotDuplicate($referenceNumber, $verification->exists ? $verification->id : null);

                if (! $verification->exists) {
                    $verification->initially_checked_by = $performedByUserId;
                    $verification->checked_at = $now;
                }

                $verification->payment_status_id = $resolvedStatusId;
                $verification->resolved_by = $performedByUserId;
                $verification->resolved_at = $now;
                $verification->remarks = $remarks;
                $this->persist($verification, $referenceNumber);
                $resolvedCount++;
            }

            return $resolvedCount;
        });
    }

    /**
     * Normalized (trimmed, whitespace-collapsed, uppercased) so "12345",
     * " 12345 ", and "12345" typed by two different panelists all collide,
     * while remaining meaningless on its own — the real value only ever
     * lives in receipt_code_encrypted.
     */
    private function hashFor(string $referenceNumber): string
    {
        $normalized = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $referenceNumber)));

        return hash('sha256', $normalized);
    }

    /**
     * @throws DuplicatePaymentReferenceException
     */
    private function assertNotDuplicate(string $referenceNumber, ?int $excludeVerificationId): void
    {
        $conflict = PaymentVerification::where('receipt_code_hash', $this->hashFor($referenceNumber))
            ->when($excludeVerificationId, fn ($query) => $query->where('id', '!=', $excludeVerificationId))
            ->with('presentationAttempt.researchGroup', 'categoryPaymentType')
            ->first();

        if (! $conflict) {
            return;
        }

        $group = $conflict->presentationAttempt?->researchGroup?->group_reference ?? 'another group';
        $type = $conflict->categoryPaymentType?->name ?? 'a payment';

        throw new DuplicatePaymentReferenceException("Reference number \"{$referenceNumber}\" is already recorded for {$group} — {$type}.");
    }

    /**
     * Sets the encrypted value + its lookup hash and saves, converting the
     * unique-index backstop (a genuine race between two simultaneous
     * submissions of the same number) into the same exception
     * assertNotDuplicate() throws, rather than a raw SQL error.
     *
     * @throws DuplicatePaymentReferenceException
     */
    private function persist(PaymentVerification $verification, string $referenceNumber): void
    {
        $verification->receipt_code_encrypted = $referenceNumber;
        $verification->receipt_code_hash = $this->hashFor($referenceNumber);

        try {
            $verification->save();
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new DuplicatePaymentReferenceException("Reference number \"{$referenceNumber}\" is already recorded for another payment.");
            }

            throw $e;
        }
    }
}
