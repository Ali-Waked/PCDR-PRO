<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Models\Complaint;
use App\Models\User;
use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateComplaintPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(CmsAccess::class)->allows($user, 'complaints.update')
            && $this->canViewComplaint($user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var list<string> $priorities */
        $priorities = array_values((array) config('complaints.priorities', []));

        return [
            'priority' => ['required', 'string', Rule::in($priorities)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'priority.in' => (string) __('website.cms.complaint_invalid_transition'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['priority' => (string) __('cms_priority')];
    }

    private function canViewComplaint(User $user): bool
    {
        $access = app(CmsAccess::class);

        if ($access->allows($user, 'complaints.view_all')) {
            return true;
        }

        if (! $access->allows($user, 'complaints.view_assigned')) {
            return false;
        }

        $complaint = $this->route('complaint');

        if ($complaint instanceof Complaint) {
            return (int) $complaint->assigned_to === (int) $user->id;
        }

        if (is_numeric($complaint)) {
            return Complaint::query()->whereKey((int) $complaint)->where('assigned_to', $user->id)->exists();
        }

        return false;
    }
}
