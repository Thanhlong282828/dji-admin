<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Domain\Models\Candidate;

final class CandidateLifecycleService
{
    public function archive(int $companyId, int $candidateId, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($companyId, $candidateId, $actorId, $reason): void {
            $candidate = Candidate::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($candidateId);
            if ($candidate->trashed()) {
                return;
            }
            $candidate->update(['status' => 'archived']);
            $candidate->delete();
            activity('candidate')->performedOn($candidate)->causedBy($actorId)->event('archived')
                ->withProperties(['company_id' => $companyId, 'reason' => $reason])->log('Candidate archived');
        }, 3);
    }

    public function restore(int $companyId, int $candidateId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $candidateId, $actorId): void {
            $candidate = Candidate::withTrashed()->where('company_id', $companyId)->lockForUpdate()->findOrFail($candidateId);
            $conflict = Candidate::query()
                ->where('company_id', $companyId)
                ->whereKeyNot($candidateId)
                ->where(static fn ($query) => $query->where('email', $candidate->email)->orWhere('profile_fingerprint', $candidate->profile_fingerprint))
                ->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['candidate' => 'Không thể khôi phục vì có hồ sơ đang hoạt động trùng email hoặc fingerprint.']);
            }
            $candidate->restore();
            $candidate->update(['status' => 'active', 'merged_into_id' => null]);
            activity('candidate')->performedOn($candidate)->causedBy($actorId)->event('restored')
                ->withProperties(['company_id' => $companyId])->log('Candidate restored');
        }, 3);
    }

    public function merge(int $companyId, int $primaryId, int $secondaryId, int $actorId): void
    {
        if ($primaryId === $secondaryId) {
            throw ValidationException::withMessages(['secondary_candidate_id' => 'Không thể merge Candidate vào chính nó.']);
        }

        DB::transaction(function () use ($companyId, $primaryId, $secondaryId, $actorId): void {
            $ids = [$primaryId, $secondaryId];
            sort($ids);
            $locked = Candidate::withTrashed()->where('company_id', $companyId)->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            $primary = $locked->get($primaryId);
            $secondary = $locked->get($secondaryId);
            if (! $primary instanceof Candidate || ! $secondary instanceof Candidate || $primary->trashed() || $secondary->trashed()) {
                throw ValidationException::withMessages(['candidate' => 'Hai Candidate phải đang hoạt động và thuộc cùng Company.']);
            }

            $primaryRequisitions = DB::table('applications')->where('candidate_id', $primaryId)->pluck('recruitment_requisition_id');
            $conflicts = DB::table('applications')->where('candidate_id', $secondaryId)->whereIn('recruitment_requisition_id', $primaryRequisitions)->exists();
            if ($conflicts) {
                throw ValidationException::withMessages(['candidate' => 'Hai Candidate có Application trùng Requisition; merge đã bị chặn để không mất dữ liệu.']);
            }

            DB::table('applications')->where('candidate_id', $secondaryId)->update(['candidate_id' => $primaryId, 'updated_at' => now()]);
            $secondary->update(['status' => 'merged', 'merged_into_id' => $primaryId]);
            $secondary->delete();
            activity('candidate')->performedOn($primary)->causedBy($actorId)->event('merged')
                ->withProperties(['company_id' => $companyId, 'secondary_candidate_id' => $secondaryId])->log('Candidates merged');
        }, 3);
    }
}
